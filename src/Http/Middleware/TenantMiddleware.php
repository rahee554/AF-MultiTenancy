<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArtflowStudio\Tenancy\Models\Tenant;

/**
 * Simple Tenant Middleware that enhances stancl/tenancy
 * 
 * This middleware works ON TOP OF stancl/tenancy's InitializeTenancyByDomain middleware.
 * Use it in a middleware group AFTER stancl's middleware for additional functionality.
 */
class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // This middleware only adds enhancements - stancl/tenancy handles the core logic
        
        // Check if tenancy is initialized by checking if tenant() helper returns a value
        $currentTenant = tenant();
        
        if ($currentTenant) {
            // Our enhancements on top of stancl/tenancy:
            
            // 1. Check tenant status (our custom feature) if using our enhanced tenant model
            if ($currentTenant instanceof Tenant) {
                if (isset($currentTenant->status)) {
                    if ($currentTenant->status === 'blocked') {
                        abort(403, 'This tenant is currently blocked. Please contact support.');
                    }
                    
                    if ($currentTenant->status === 'inactive') {
                        abort(404, 'Tenant not found.');
                    }
                }
            }
            
            // 2. Update last accessed timestamp (our custom feature) if tenant supports it
            if ($currentTenant instanceof Tenant && 
                !$request->isMethod('HEAD') && 
                $currentTenant->getFillable() && 
                in_array('last_accessed_at', $currentTenant->getFillable())) {
                try {
                    $currentTenant->update(['last_accessed_at' => now()]);
                } catch (\Exception $e) {
                    // Silently fail if update doesn't work - don't break the request
                }
            }
               
            // 3. Set view data for templates (our custom feature)
            view()->share('currentTenant', $currentTenant);
        }
        
        return $next($request);
    }
}
