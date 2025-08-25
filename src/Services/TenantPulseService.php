<?php

namespace ArtflowStudio\Tenancy\Services;

use Laravel\Pulse\Facades\Pulse;
use Stancl\Tenancy\Facades\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Models\Tenant;

class TenantPulseService
{
    /**
     * Record tenant-specific metrics
     */
    public function recordTenantMetric(string $type, string $key, $value, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return;
        }

        $tenant = $this->getTenantInfo($tenantId);
        $tenantTag = "tenant_{$tenant['name']}_{$tenantId}";

        Pulse::record($type, $key, $value, [$tenantTag, 'tenant_global']);
    }

    /**
     * Record tenant database metrics
     */
    public function recordDatabaseMetrics(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return;
        }

        try {
            $tenant = $this->getTenantInfo($tenantId);
            $tenantTag = "tenant_{$tenant['name']}_{$tenantId}";

            // Database connection metrics
            $connections = DB::getConnections();
            foreach ($connections as $name => $connection) {
                $this->recordTenantMetric('db_connection_count', $name, 1, $tenantId);
            }

            // Query count (if in tenant context)
            if (Tenancy::initialized()) {
                $queryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
                $this->recordTenantMetric('db_query_count', 'queries', $queryCount, $tenantId);
            }

        } catch (\Exception $e) {
            // Log error but don't break the application
            logger()->warning("Failed to record tenant database metrics: " . $e->getMessage());
        }
    }

    /**
     * Record tenant cache metrics
     */
    public function recordCacheMetrics(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return;
        }

        try {
            $tenant = $this->getTenantInfo($tenantId);
            
            // Cache hit/miss ratio could be tracked here
            $cacheStore = Cache::getStore();
            $storeName = get_class($cacheStore);
            
            $this->recordTenantMetric('cache_store', 'active', $storeName, $tenantId);

        } catch (\Exception $e) {
            logger()->warning("Failed to record tenant cache metrics: " . $e->getMessage());
        }
    }

    /**
     * Record tenant request metrics
     */
    public function recordRequestMetrics(float $responseTime, int $memoryUsage, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->getCurrentTenantId();
        
        if (!$tenantId) {
            return;
        }

        $this->recordTenantMetric('request_duration', 'response_time', $responseTime, $tenantId);
        $this->recordTenantMetric('memory_usage', 'peak_memory', $memoryUsage, $tenantId);
    }

    /**
     * Get tenant-specific Pulse data
     */
    public function getTenantMetrics(string $tenantId, string $type = null, int $hours = 24): array
    {
        $tenant = $this->getTenantInfo($tenantId);
        $tenantTag = "tenant_{$tenant['name']}_{$tenantId}";

        try {
            $query = DB::connection('mysql')->table('pulse_entries')
                ->where('timestamp', '>=', now()->subHours($hours))
                ->whereJsonContains('tags', $tenantTag);

            if ($type) {
                $query->where('type', $type);
            }

            return $query->orderBy('timestamp', 'desc')->get()->toArray();
        } catch (\Exception $e) {
            logger()->warning("Failed to get tenant metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all tenant metrics summary
     */
    public function getAllTenantsMetrics(int $hours = 24): array
    {
        try {
            $tenants = Tenant::all();
            $metrics = [];

            foreach ($tenants as $tenant) {
                $tenantTag = "tenant_{$tenant->name}_{$tenant->id}";
                
                $tenantMetrics = DB::connection('mysql')->table('pulse_entries')
                    ->where('timestamp', '>=', now()->subHours($hours))
                    ->whereJsonContains('tags', $tenantTag)
                    ->selectRaw('type, COUNT(*) as count, AVG(value) as avg_value, MAX(value) as max_value')
                    ->groupBy('type')
                    ->get()
                    ->keyBy('type')
                    ->toArray();

                $metrics[$tenant->id] = [
                    'tenant' => $tenant,
                    'metrics' => $tenantMetrics
                ];
            }

            return $metrics;
        } catch (\Exception $e) {
            logger()->warning("Failed to get all tenant metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current tenant ID
     */
    private function getCurrentTenantId(): ?string
    {
        return Tenancy::initialized() ? tenant('id') : null;
    }

    /**
     * Get tenant information
     */
    private function getTenantInfo(string $tenantId): array
    {
        static $tenantCache = [];

        if (!isset($tenantCache[$tenantId])) {
            $tenant = Tenant::find($tenantId);
            $tenantCache[$tenantId] = [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'unnamed',
            ];
        }

        return $tenantCache[$tenantId];
    }

    /**
     * Clean old tenant metrics
     */
    public function cleanOldMetrics(int $days = 7): int
    {
        try {
            return DB::connection('mysql')->table('pulse_entries')
                ->where('timestamp', '<', now()->subDays($days))
                ->whereJsonContains('tags', 'tenant_global')
                ->delete();
        } catch (\Exception $e) {
            logger()->warning("Failed to clean old tenant metrics: " . $e->getMessage());
            return 0;
        }
    }
}
