<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Authentication Middleware
 * 
 * This middleware adds logging and tenant-aware authentication features
 * on top of Laravel's default authentication.
 */
class TenantAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Get current tenant (should be initialized by stancl/tenancy middleware)
        $currentTenant = tenant();
        
        if ($currentTenant) {
            // Log authentication attempts for tenant
            Log::channel('tenant')->info('Authentication attempt for tenant', [
                'tenant_id' => $currentTenant->id,
                'domain' => $request->getHost(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            
            // Add tenant context to request for controllers
            $request->attributes->set('tenant', $currentTenant);
            
            // Share tenant data with views
            view()->share('currentTenant', $currentTenant);
        }

        return $next($request);
    }
}
