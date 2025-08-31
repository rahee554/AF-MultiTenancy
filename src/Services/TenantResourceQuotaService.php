<?php

namespace ArtflowStudio\Tenancy\Services;

use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use ArtflowStudio\Tenancy\Events\TenantQuotaExceeded;
use ArtflowStudio\Tenancy\Events\TenantQuotaWarning;

class TenantResourceQuotaService
{
    protected $cachePrefix = 'tq_';
    protected $defaultCacheTtl = 3600; // 1 hour

    /**
     * Default quota limits
     */
    protected $defaultQuotas = [
        'storage_mb' => 1000,
        'users' => 100,
        'monthly_bandwidth_gb' => 100,
        'api_calls_per_day' => 10000,
        'monthly_emails' => 1000,
        'cron_jobs' => 10,
        'webhooks' => 25,
        'database_size_mb' => 1000,
        'file_storage_mb' => 5000,
    ];

    /**
     * Check all quotas for a tenant
     */
    public function checkQuotas(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \Exception("Tenant not found: {$tenantId}");
        }

        $quotas = $this->getTenantQuotas($tenantId);
        $usage = $this->getCurrentUsage($tenantId);
        $results = [];

        foreach ($quotas as $resource => $limit) {
            $currentUsage = $usage[$resource] ?? 0;
            $percentage = $limit > 0 ? ($currentUsage / $limit) * 100 : 0;
            
            $status = 'ok';
            if ($percentage >= 100) {
                $status = 'exceeded';
                event(new TenantQuotaExceeded($tenant, $resource, $currentUsage, $limit));
            } elseif ($percentage >= 85) {
                $status = 'warning';
                event(new TenantQuotaWarning($tenant, $resource, $currentUsage, $limit));
            }

            $results[$resource] = [
                'current' => $currentUsage,
                'limit' => $limit,
                'percentage' => round($percentage, 2),
                'status' => $status,
                'available' => max(0, $limit - $currentUsage),
            ];
        }

        return $results;
    }

    /**
     * Get tenant quotas from settings JSON
     */
    public function getTenantQuotas(string $tenantId): array
    {
        $cacheKey = $this->cachePrefix . "limits_{$tenantId}";
        
        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return $this->defaultQuotas;
            }

            $settings = json_decode($tenant->settings ?? '{}', true);
            return array_merge($this->defaultQuotas, $settings['quotas'] ?? []);
        });
    }

    /**
     * Set tenant quotas in settings JSON
     */
    public function setTenantQuotas(string $tenantId, array $quotas): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \Exception("Tenant not found: {$tenantId}");
        }

        $settings = json_decode($tenant->settings ?? '{}', true);
        $settings['quotas'] = array_merge($this->defaultQuotas, $quotas);
        
        $tenant->update(['settings' => json_encode($settings)]);
        
        // Clear cache
        $cacheKey = $this->cachePrefix . "limits_{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Get current usage for tenant
     */
    public function getCurrentUsage(string $tenantId): array
    {
        $cacheKey = $this->cachePrefix . "usage_{$tenantId}";
        
        return Cache::remember($cacheKey, 900, function () use ($tenantId) { // 15 minutes
            // Get usage from tenant settings or calculate
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return [];
            }

            $settings = json_decode($tenant->settings ?? '{}', true);
            return $settings['current_usage'] ?? [
                'storage_mb' => 0,
                'users' => 0,
                'monthly_bandwidth_gb' => 0,
                'api_calls_per_day' => 0,
                'monthly_emails' => 0,
                'cron_jobs' => 0,
                'webhooks' => 0,
                'database_size_mb' => 0,
                'file_storage_mb' => 0,
            ];
        });
    }

    /**
     * Check if tenant can perform an action
     */
    public function canPerformAction(string $tenantId, string $resource, int $amount = 1): bool
    {
        $quotas = $this->checkQuotas($tenantId);
        
        if (!isset($quotas[$resource])) {
            return true; // No quota set for this resource
        }
        
        $quota = $quotas[$resource];
        return ($quota['current'] + $amount) <= $quota['limit'];
    }

    /**
     * Increment usage for a resource
     */
    public function incrementUsage(string $tenantId, string $resource, int $amount = 1): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        $settings = json_decode($tenant->settings ?? '{}', true);
        $currentUsage = $settings['current_usage'] ?? [];
        
        $currentUsage[$resource] = ($currentUsage[$resource] ?? 0) + $amount;
        $settings['current_usage'] = $currentUsage;
        
        $tenant->update(['settings' => json_encode($settings)]);
        
        // Clear usage cache
        $cacheKey = $this->cachePrefix . "usage_{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Get quota summary for tenant
     */
    public function getQuotaSummary(string $tenantId): array
    {
        $quotas = $this->checkQuotas($tenantId);
        
        $summary = [
            'total_quotas' => count($quotas),
            'exceeded' => 0,
            'warning' => 0,
            'ok' => 0,
            'overall_status' => 'ok',
        ];

        foreach ($quotas as $quota) {
            switch ($quota['status']) {
                case 'exceeded':
                    $summary['exceeded']++;
                    $summary['overall_status'] = 'exceeded';
                    break;
                case 'warning':
                    $summary['warning']++;
                    if ($summary['overall_status'] !== 'exceeded') {
                        $summary['overall_status'] = 'warning';
                    }
                    break;
                default:
                    $summary['ok']++;
            }
        }

        return $summary;
    }

    /**
     * Get quota recommendations
     */
    public function getQuotaRecommendations(string $tenantId): array
    {
        $quotas = $this->checkQuotas($tenantId);
        $recommendations = [];

        foreach ($quotas as $resource => $quota) {
            if ($quota['percentage'] > 80) {
                $recommended = ceil($quota['limit'] * 1.5);
                $recommendations[] = [
                    'resource' => $resource,
                    'current_limit' => $quota['limit'],
                    'recommended_limit' => $recommended,
                    'reason' => "Usage is at {$quota['percentage']}%",
                    'priority' => $quota['percentage'] > 95 ? 'high' : 'medium',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Reset monthly usage counters
     */
    public function resetMonthlyUsage(string $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        $settings = json_decode($tenant->settings ?? '{}', true);
        $currentUsage = $settings['current_usage'] ?? [];
        
        // Reset monthly counters
        $currentUsage['monthly_bandwidth_gb'] = 0;
        $currentUsage['monthly_emails'] = 0;
        
        $settings['current_usage'] = $currentUsage;
        $tenant->update(['settings' => json_encode($settings)]);
        
        // Clear cache
        Cache::forget($this->cachePrefix . "usage_{$tenantId}");
    }

    /**
     * Get tenant settings (including quotas and other settings)
     */
    public function getTenantSettings(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return [];
        }

        return json_decode($tenant->settings ?? '{}', true);
    }

    /**
     * Update tenant settings
     */
    public function updateTenantSettings(string $tenantId, array $settings): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \Exception("Tenant not found: {$tenantId}");
        }

        $currentSettings = json_decode($tenant->settings ?? '{}', true);
        $newSettings = array_merge($currentSettings, $settings);
        
        $tenant->update(['settings' => json_encode($newSettings)]);
        
        // Clear related caches
        Cache::forget($this->cachePrefix . "limits_{$tenantId}");
        Cache::forget($this->cachePrefix . "usage_{$tenantId}");
    }
}
