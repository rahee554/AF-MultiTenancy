<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ArtflowStudio\Tenancy\Models\Tenant;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     * This middleware handles tenant status validation after stancl/tenancy initialization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a central domain
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        if (in_array($currentDomain, $centralDomains)) {
            // Central domain routes are allowed (admin, API, etc.)
            return $next($request);
        }

        // Get the tenant - stancl/tenancy should have already initialized it
        $tenant = tenant();
        
        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        // Check tenant status
        switch ($tenant->status ?? 'active') {
            case 'blocked':
                return response()->view('tenancy::errors.tenant-blocked', ['tenant' => $tenant], 403);
                
            case 'suspended':
                return response()->view('tenancy::errors.tenant-suspended', ['tenant' => $tenant], 503);
                
            case 'inactive':
                return response()->view('tenancy::errors.tenant-inactive', ['tenant' => $tenant], 503);
        }

        // Update last_accessed_at for active tenants
        if ($tenant->status === 'active') {
            $tenant->update(['last_accessed_at' => now()]);
        }

        return $next($request);
    }
}
