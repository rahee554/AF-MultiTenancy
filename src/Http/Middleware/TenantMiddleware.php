<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Facades\Tenancy;
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
        
        // Check if tenancy is already initialized by stancl/tenancy
        if (Tenancy::initialized()) {
            $tenant = Tenancy::tenant();
            
            // Our enhancements on top of stancl/tenancy:
            
            // 1. Check tenant status (our custom feature)
            if ($tenant instanceof Tenant) {
                if ($tenant->status === 'blocked') {
                    abort(403, 'This tenant is currently blocked. Please contact support.');
                }
                
                if ($tenant->status === 'inactive') {
                    abort(404, 'Tenant not found.');
                }
            }
            
            // 2. Update last accessed timestamp (our custom feature)
            if ($tenant instanceof Tenant && !$request->isMethod('HEAD')) {
                $tenant->update(['last_accessed_at' => now()]);
            }
            
            // 3. Set view data for templates (our custom feature)
            view()->share('currentTenant', $tenant);
        }
        
        return $next($request);
    }
}
