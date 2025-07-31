<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Tenant Maintenance Mode Service
 * 
 * Enhanced implementation of tenant-specific maintenance mode
 * Based on: https://tenancyforlaravel.com/docs/v3/tenant-maintenance-mode
 */
class TenantMaintenanceMode
{
    protected $cachePrefix = 'tenant_maintenance';
    protected $cacheTtl = 300; // 5 minutes

    /**
     * Enable maintenance mode for a tenant
     */
    public function enableForTenant(string $tenantId, array $options = []): bool
    {
        try {
            $maintenanceData = [
                'enabled' => true,
                'enabled_at' => now()->toISOString(),
                'message' => $options['message'] ?? 'This tenant is temporarily unavailable for maintenance.',
                'allowed_ips' => $options['allowed_ips'] ?? $this->getDefaultAllowedIps(),
                'bypass_key' => $options['bypass_key'] ?? $this->generateBypassKey(),
                'redirect_url' => $options['redirect_url'] ?? null,
                'retry_after' => $options['retry_after'] ?? 3600, // 1 hour
                'admin_contact' => $options['admin_contact'] ?? null,
            ];

            $cacheKey = $this->getCacheKey($tenantId);
            
            Cache::put($cacheKey, $maintenanceData, $this->cacheTtl * 12); // 1 hour cache
            
            // Also store in tenant data for persistence
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $tenant->update([
                    'data' => array_merge($tenant->data ?? [], [
                        'maintenance_mode' => $maintenanceData
                    ])
                ]);
            }

            Log::info('TenantMaintenanceMode: Enabled for tenant', [
                'tenant_id' => $tenantId,
                'message' => $maintenanceData['message'],
                'allowed_ips' => $maintenanceData['allowed_ips']
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('TenantMaintenanceMode: Failed to enable maintenance mode', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Disable maintenance mode for a tenant
     */
    public function disableForTenant(string $tenantId): bool
    {
        try {
            $cacheKey = $this->getCacheKey($tenantId);
            Cache::forget($cacheKey);

            // Remove from tenant data
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $data = $tenant->data ?? [];
                unset($data['maintenance_mode']);
                $tenant->update(['data' => $data]);
            }

            Log::info('TenantMaintenanceMode: Disabled for tenant', [
                'tenant_id' => $tenantId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('TenantMaintenanceMode: Failed to disable maintenance mode', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if tenant is in maintenance mode
     */
    public function isInMaintenanceMode(string $tenantId): bool
    {
        try {
            $cacheKey = $this->getCacheKey($tenantId);
            $maintenanceData = Cache::get($cacheKey);

            // If not in cache, check tenant data
            if (!$maintenanceData) {
                $tenant = Tenant::find($tenantId);
                $maintenanceData = $tenant->data['maintenance_mode'] ?? null;
                
                // Re-cache if found
                if ($maintenanceData) {
                    Cache::put($cacheKey, $maintenanceData, $this->cacheTtl);
                }
            }

            return $maintenanceData && ($maintenanceData['enabled'] ?? false);

        } catch (\Exception $e) {
            Log::error('TenantMaintenanceMode: Error checking maintenance mode', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if request should bypass maintenance mode
     */
    public function shouldBypassMaintenance(Request $request, string $tenantId): bool
    {
        $maintenanceData = $this->getMaintenanceData($tenantId);
        
        if (!$maintenanceData) {
            return true;
        }

        // Check IP whitelist
        $clientIp = $request->ip();
        $allowedIps = $maintenanceData['allowed_ips'] ?? [];
        
        if (in_array($clientIp, $allowedIps)) {
            Log::debug('TenantMaintenanceMode: Bypassing due to allowed IP', [
                'tenant_id' => $tenantId,
                'client_ip' => $clientIp
            ]);
            return true;
        }

        // Check bypass key
        $bypassKey = $request->get('bypass_key') ?? $request->header('X-Bypass-Key');
        $validBypassKey = $maintenanceData['bypass_key'] ?? null;
        
        if ($bypassKey && $bypassKey === $validBypassKey) {
            Log::debug('TenantMaintenanceMode: Bypassing due to valid bypass key', [
                'tenant_id' => $tenantId
            ]);
            return true;
        }

        return false;
    }

    /**
     * Generate maintenance mode response
     */
    public function generateMaintenanceResponse(Request $request, string $tenantId): Response
    {
        $maintenanceData = $this->getMaintenanceData($tenantId);
        
        // If redirect URL is specified, redirect
        if (!empty($maintenanceData['redirect_url'])) {
            return redirect($maintenanceData['redirect_url']);
        }

        // Generate maintenance page
        $view = $this->getMaintenanceView($maintenanceData);
        
        $response = response($view, 503);
        
        // Add Retry-After header
        if (!empty($maintenanceData['retry_after'])) {
            $response->header('Retry-After', $maintenanceData['retry_after']);
        }

        return $response;
    }

    /**
     * Get maintenance data for tenant
     */
    protected function getMaintenanceData(string $tenantId): ?array
    {
        $cacheKey = $this->getCacheKey($tenantId);
        $maintenanceData = Cache::get($cacheKey);

        if (!$maintenanceData) {
            try {
                $tenant = Tenant::find($tenantId);
                $maintenanceData = $tenant->data['maintenance_mode'] ?? null;
                
                if ($maintenanceData) {
                    Cache::put($cacheKey, $maintenanceData, $this->cacheTtl);
                }
            } catch (\Exception $e) {
                Log::error('TenantMaintenanceMode: Error getting maintenance data', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        return $maintenanceData;
    }

    /**
     * Generate maintenance view
     */
    protected function getMaintenanceView(array $maintenanceData): string
    {
        $viewName = config('tenancy.maintenance_mode.view', 'tenancy::maintenance');
        
        if (View::exists($viewName)) {
            return view($viewName, $maintenanceData)->render();
        }

        // Default maintenance page
        return $this->getDefaultMaintenanceView($maintenanceData);
    }

    /**
     * Get default maintenance view
     */
    protected function getDefaultMaintenanceView(array $maintenanceData): string
    {
        $message = $maintenanceData['message'] ?? 'This site is temporarily unavailable for maintenance.';
        $adminContact = $maintenanceData['admin_contact'] ?? null;
        $retryAfter = $maintenanceData['retry_after'] ?? 3600;
        
        $estimatedTime = $retryAfter > 0 ? date('H:i', time() + $retryAfter) : 'soon';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Maintenance Mode</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 2rem; background: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; text-align: center; background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .icon { font-size: 4rem; margin-bottom: 1rem; }
                h1 { color: #495057; margin-bottom: 1rem; }
                p { color: #6c757d; line-height: 1.6; margin-bottom: 1rem; }
                .contact { margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='icon'>🔧</div>
                <h1>Maintenance Mode</h1>
                <p>{$message}</p>
                <p>We expect to be back online around <strong>{$estimatedTime}</strong>.</p>
                " . ($adminContact ? "<div class='contact'><p>Need assistance? Contact: <strong>{$adminContact}</strong></p></div>" : "") . "
            </div>
        </body>
        </html>";
    }

    /**
     * Get cache key for tenant maintenance mode
     */
    protected function getCacheKey(string $tenantId): string
    {
        return "{$this->cachePrefix}:{$tenantId}";
    }

    /**
     * Get default allowed IPs
     */
    protected function getDefaultAllowedIps(): array
    {
        return config('tenancy.maintenance_mode.allowed_ips', ['127.0.0.1', '::1']);
    }

    /**
     * Generate bypass key
     */
    protected function generateBypassKey(): string
    {
        return config('tenancy.maintenance_mode.bypass_key', 'secret_' . uniqid());
    }

    /**
     * Get all tenants in maintenance mode
     */
    public function getTenantsInMaintenance(): array
    {
        $tenants = [];
        
        try {
            Tenant::chunk(50, function ($tenantBatch) use (&$tenants) {
                foreach ($tenantBatch as $tenant) {
                    if ($this->isInMaintenanceMode($tenant->id)) {
                        $tenants[] = [
                            'id' => $tenant->id,
                            'data' => $tenant->data,
                            'maintenance_data' => $this->getMaintenanceData($tenant->id)
                        ];
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('TenantMaintenanceMode: Error getting tenants in maintenance', [
                'error' => $e->getMessage()
            ]);
        }

        return $tenants;
    }
}
