<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Multi-Project Dashboard Service
 * 
 * Handles synchronization of tenant data across multiple projects
 * to a centralized dashboard for SaaS management
 */
class MultiProjectDashboardService
{
    protected string $dashboardUrl;
    protected string $projectId;
    protected string $apiKey;
    protected string $webhookSecret;

    public function __construct()
    {
        $this->dashboardUrl = config('artflow-tenancy.dashboard.dashboard_url', '');
        $this->projectId = config('artflow-tenancy.project.id', 'default');
        $this->apiKey = config('artflow-tenancy.project.api_key', '');
        $this->webhookSecret = config('artflow-tenancy.dashboard.webhook_secret', '');
    }

    /**
     * Sync all tenant data to the centralized dashboard
     */
    public function syncAllTenants(): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Dashboard service is not properly configured');
        }

        $tenants = Tenant::with(['domains'])->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($tenants as $tenant) {
            try {
                $this->syncTenant($tenant);
                $syncedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ];
                Log::error('Failed to sync tenant to dashboard', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send project summary
        $this->syncProjectSummary();

        return [
            'total_tenants' => $tenants->count(),
            'synced_count' => $syncedCount,
            'error_count' => count($errors),
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Sync a single tenant to the dashboard
     */
    public function syncTenant(Tenant $tenant): bool
    {
        $payload = $this->prepareTenantPayload($tenant);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Project-ID' => $this->projectId,
            'X-Webhook-Signature' => $this->generateSignature($payload),
        ])->timeout(30)->post($this->dashboardUrl . '/api/tenants/sync', $payload);

        if (!$response->successful()) {
            throw new \Exception('Dashboard API error: ' . $response->body());
        }

        return true;
    }

    /**
     * Sync project summary statistics
     */
    public function syncProjectSummary(): bool
    {
        $payload = [
            'project_id' => $this->projectId,
            'project_name' => config('artflow-tenancy.project.name'),
            'environment' => app()->environment(),
            'statistics' => $this->getProjectStatistics(),
            'health_metrics' => $this->getProjectHealthMetrics(),
            'timestamp' => now()->toISOString(),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Project-ID' => $this->projectId,
            'X-Webhook-Signature' => $this->generateSignature($payload),
        ])->timeout(30)->post($this->dashboardUrl . '/api/projects/sync', $payload);

        return $response->successful();
    }

    /**
     * Send real-time event to dashboard
     */
    public function sendRealtimeEvent(string $event, array $data): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'project_id' => $this->projectId,
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Project-ID' => $this->projectId,
                'X-Webhook-Signature' => $this->generateSignature($payload),
            ])->timeout(10)->post($this->dashboardUrl . '/api/events/realtime', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Failed to send realtime event to dashboard', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Prepare tenant data payload for dashboard sync
     */
    protected function prepareTenantPayload(Tenant $tenant): array
    {
        return [
            'project_id' => $this->projectId,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at?->toISOString(),
                'updated_at' => $tenant->updated_at?->toISOString(),
                'last_accessed_at' => $tenant->last_accessed_at?->toISOString(),
                'database_name' => $tenant->getDatabaseName(),
                'settings' => $tenant->settings ?? [],
                'has_homepage' => $tenant->has_homepage,
            ],
            'domains' => $tenant->domains->map(function ($domain) {
                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'created_at' => $domain->created_at?->toISOString(),
                ];
            })->toArray(),
            'metrics' => $this->getTenantMetrics($tenant),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get project-level statistics
     */
    protected function getProjectStatistics(): array
    {
        return Cache::remember('project_stats_' . $this->projectId, 300, function () {
            return [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'tenants_created_today' => Tenant::whereDate('created_at', today())->count(),
                'tenants_created_this_week' => Tenant::where('created_at', '>=', now()->startOfWeek())->count(),
                'tenants_created_this_month' => Tenant::where('created_at', '>=', now()->startOfMonth())->count(),
                'tenants_by_status' => Tenant::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
            ];
        });
    }

    /**
     * Get project health metrics
     */
    protected function getProjectHealthMetrics(): array
    {
        return [
            'database_connections' => $this->checkDatabaseConnections(),
            'queue_health' => $this->checkQueueHealth(),
            'cache_health' => $this->checkCacheHealth(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            ],
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    /**
     * Get tenant-specific metrics
     */
    protected function getTenantMetrics(Tenant $tenant): array
    {
        $cacheKey = "tenant_dashboard_metrics_{$tenant->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenant) {
            $metrics = [
                'database_exists' => false,
                'database_size' => 0,
                'table_count' => 0,
                'last_migration' => null,
                'connection_test' => false,
            ];

            try {
                $tenant->run(function () use (&$metrics) {
                    $connection = app('db')->connection('tenant');
                    $metrics['connection_test'] = true;
                    
                    // Get database size
                    $dbName = $connection->getDatabaseName();
                    $sizeQuery = $connection->select("
                        SELECT 
                            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = ?
                    ", [$dbName]);
                    
                    $metrics['database_size'] = $sizeQuery[0]->size_mb ?? 0;
                    
                    // Get table count
                    $tableCount = $connection->select("
                        SELECT COUNT(*) as count 
                        FROM information_schema.tables 
                        WHERE table_schema = ?
                    ", [$dbName]);
                    
                    $metrics['table_count'] = $tableCount[0]->count ?? 0;
                    $metrics['database_exists'] = true;
                });
            } catch (\Exception $e) {
                Log::warning('Failed to get tenant metrics', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }

            return $metrics;
        });
    }

    /**
     * Generate webhook signature for security
     */
    protected function generateSignature(array $payload): string
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $data, $this->webhookSecret);
    }

    /**
     * Check if the service is properly configured
     */
    protected function isConfigured(): bool
    {
        return !empty($this->dashboardUrl) && 
               !empty($this->apiKey) && 
               !empty($this->webhookSecret);
    }

    /**
     * Helper methods for health checks
     */
    protected function checkDatabaseConnections(): array
    {
        try {
            $centralConnection = app('db')->connection()->getPdo();
            return ['status' => 'healthy', 'connections' => 1];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkQueueHealth(): array
    {
        try {
            $queueSize = app('queue')->size();
            return [
                'status' => 'healthy',
                'size' => $queueSize,
                'driver' => config('queue.default')
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkCacheHealth(): array
    {
        try {
            $testKey = 'dashboard_health_check_' . time();
            app('cache')->put($testKey, 'ok', 10);
            $result = app('cache')->get($testKey);
            app('cache')->forget($testKey);
            
            return [
                'status' => $result === 'ok' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function getDiskUsage(): array
    {
        try {
            $free = disk_free_space('/');
            $total = disk_total_space('/');
            $used = $total - $free;
            $percentage = ($used / $total) * 100;

            return [
                'free_bytes' => $free,
                'total_bytes' => $total,
                'used_bytes' => $used,
                'usage_percentage' => round($percentage, 2),
                'status' => $percentage > 90 ? 'warning' : 'healthy'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return (int) $limit;
        }
    }
}
