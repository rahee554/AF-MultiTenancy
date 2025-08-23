<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;

/**
 * Tenant Maintenance Mode Middleware
 * 
 * Intercepts requests to tenants in maintenance mode and shows maintenance page
 */
class TenantMaintenanceMiddleware
{
    protected TenantMaintenanceMode $maintenanceService;

    public function __construct(TenantMaintenanceMode $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check if tenant context is active
        if (!function_exists('tenant') || !tenant()) {
            return $next($request);
        }

        $tenantId = tenant()->id;
        
        // Check if tenant is in maintenance mode
        if (!$this->maintenanceService->isInMaintenanceMode($tenantId)) {
            return $next($request);
        }

        // Check if request should bypass maintenance mode
        if ($this->maintenanceService->shouldBypassMaintenance($request, $tenantId)) {
            return $next($request);
        }

        // Generate and return maintenance response
        return $this->maintenanceService->generateMaintenanceResponse($request, $tenantId);
    }
}
