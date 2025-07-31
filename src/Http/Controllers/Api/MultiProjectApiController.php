<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Services\MultiProjectDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Multi-Project Tenant API Controller
 * 
 * Provides API endpoints for managing tenants across multiple projects
 * and feeding data to centralized dashboards
 */
class MultiProjectApiController extends Controller
{
    protected TenantService $tenantService;
    protected MultiProjectDashboardService $dashboardService;

    public function __construct(
        TenantService $tenantService,
        MultiProjectDashboardService $dashboardService
    ) {
        $this->tenantService = $tenantService;
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get all tenants with project context
     */
    public function getAllTenants(Request $request): JsonResponse
    {
        $projectId = config('artflow-tenancy.project.id');
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = Tenant::with(['domains'])
            ->when($search, function ($q) use ($search) {
                return $q->where('name', 'like', "%{$search}%")
                         ->orWhere('id', 'like', "%{$search}%")
                         ->orWhereHas('domains', function ($domainQuery) use ($search) {
                             $domainQuery->where('domain', 'like', "%{$search}%");
                         });
            });

        $tenants = $query->paginate($perPage);

        // Add project context to each tenant
        $tenants->getCollection()->transform(function ($tenant) use ($projectId) {
            $tenant->project_id = $projectId;
            $tenant->project_name = config('artflow-tenancy.project.name');
            $tenant->primary_domain = $tenant->domains->first()?->domain;
            $tenant->total_domains = $tenant->domains->count();
            
            // Add metrics if available
            $tenant->metrics = $this->getTenantMetrics($tenant);
            
            return $tenant;
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
            'project' => [
                'id' => $projectId,
                'name' => config('artflow-tenancy.project.name'),
                'environment' => app()->environment(),
            ]
        ]);
    }

    /**
     * Get aggregated statistics for all tenants
     */
    public function getAggregatedStats(): JsonResponse
    {
        $cacheKey = 'tenant_aggregated_stats_' . config('artflow-tenancy.project.id');
        
        $stats = Cache::remember($cacheKey, 300, function () {
            return [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'total_domains' => DB::table('domains')->count(),
                'tenants_by_status' => Tenant::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
                'recent_tenants' => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
                'project_id' => config('artflow-tenancy.project.id'),
                'project_name' => config('artflow-tenancy.project.name'),
                'last_updated' => now()->toISOString(),
            ];
        });

        // Add real-time queue stats if Horizon is enabled
        if (config('artflow-tenancy.integrations.horizon.enabled')) {
            $stats['queue_stats'] = $this->getQueueStats();
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get tenant details by ID with full context
     */
    public function getTenantDetails(string $id): JsonResponse
    {
        $tenant = Tenant::with(['domains'])->findOrFail($id);
        
        $details = [
            'tenant' => $tenant,
            'project_id' => config('artflow-tenancy.project.id'),
            'project_name' => config('artflow-tenancy.project.name'),
            'metrics' => $this->getTenantMetrics($tenant),
            'health_check' => $this->getTenantHealthCheck($tenant),
            'recent_activity' => $this->getTenantRecentActivity($tenant),
        ];

        return response()->json([
            'success' => true,
            'data' => $details
        ]);
    }

    /**
     * Sync tenant data to centralized dashboard
     */
    public function syncToDashboard(Request $request): JsonResponse
    {
        if (!config('artflow-tenancy.dashboard.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard sync is not enabled'
            ], 400);
        }

        try {
            $result = $this->dashboardService->syncAllTenants();
            
            return response()->json([
                'success' => true,
                'message' => 'Sync completed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'queues' => $this->checkQueueHealth(),
            'storage' => $this->checkStorageHealth(),
            'memory_usage' => $this->getMemoryUsage(),
            'tenant_databases' => $this->checkTenantDatabases(),
            'project_id' => config('artflow-tenancy.project.id'),
            'timestamp' => now()->toISOString(),
        ];

        $overallHealth = collect($health)->except(['project_id', 'timestamp', 'memory_usage'])
            ->every(fn($status) => $status['status'] === 'healthy');

        return response()->json([
            'success' => true,
            'data' => [
                'overall_status' => $overallHealth ? 'healthy' : 'unhealthy',
                'details' => $health
            ]
        ]);
    }

    /**
     * Get real-time metrics for monitoring
     */
    public function getRealTimeMetrics(): JsonResponse
    {
        $metrics = [
            'timestamp' => now()->toISOString(),
            'project_id' => config('artflow-tenancy.project.id'),
            'active_connections' => DB::table('information_schema.processlist')
                ->where('db', 'like', config('tenancy.database.prefix') . '%')
                ->count(),
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'queue_size' => $this->getQueueSize(),
            'failed_jobs' => $this->getFailedJobsCount(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get tenant-specific metrics
     */
    protected function getTenantMetrics(Tenant $tenant): array
    {
        $cacheKey = "tenant_metrics_{$tenant->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenant) {
            return [
                'database_size' => $this->getTenantDatabaseSize($tenant),
                'last_activity' => $this->getTenantLastActivity($tenant),
                'total_requests' => $this->getTenantRequestCount($tenant),
                'error_rate' => $this->getTenantErrorRate($tenant),
            ];
        });
    }

    /**
     * Additional helper methods for health checks and metrics
     */
    protected function checkDatabaseHealth(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    protected function checkCacheHealth(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $result = Cache::get('health_check');
            return ['status' => $result === 'ok' ? 'healthy' : 'unhealthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueueHealth(): array
    {
        try {
            $size = Queue::size();
            return [
                'status' => 'healthy',
                'queue_size' => $size,
                'message' => "Queue is operational with {$size} pending jobs"
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    protected function checkStorageHealth(): array
    {
        try {
            $disk = disk_free_space('/');
            $total = disk_total_space('/');
            $usage = (($total - $disk) / $total) * 100;
            
            return [
                'status' => $usage < 90 ? 'healthy' : 'warning',
                'usage_percentage' => round($usage, 2),
                'free_space' => $disk,
                'total_space' => $total
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    protected function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
        ];
    }

    protected function checkTenantDatabases(): array
    {
        $tenants = Tenant::take(10)->get();
        $healthy = 0;
        $unhealthy = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () {
                    DB::connection('tenant')->getPdo();
                });
                $healthy++;
            } catch (\Exception $e) {
                $unhealthy++;
            }
        }

        return [
            'status' => $unhealthy === 0 ? 'healthy' : 'warning',
            'healthy_count' => $healthy,
            'unhealthy_count' => $unhealthy,
            'total_checked' => $healthy + $unhealthy
        ];
    }

    // Additional helper methods would be implemented here...
    protected function getQueueStats(): array { return []; }
    protected function getQueueSize(): int { return 0; }
    protected function getFailedJobsCount(): int { return 0; }
    protected function getTenantDatabaseSize(Tenant $tenant): int { return 0; }
    protected function getTenantLastActivity(Tenant $tenant): ?string { return null; }
    protected function getTenantRequestCount(Tenant $tenant): int { return 0; }
    protected function getTenantErrorRate(Tenant $tenant): float { return 0.0; }
    protected function getTenantHealthCheck(Tenant $tenant): array { return []; }
    protected function getTenantRecentActivity(Tenant $tenant): array { return []; }
}
