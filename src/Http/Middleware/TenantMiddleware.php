<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ArtflowStudio\Tenancy\Models\Tenant;

class TenantMiddleware
{
    /**
     * Enhanced tenant middleware that properly integrates with stancl/tenancy.
     * 
     * This middleware performs tenant status validation BEFORE stancl/tenancy
     * handles database switching, providing optimal performance and proper
     * integration with the stancl/tenancy ecosystem.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a central domain
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        if (in_array($currentDomain, $centralDomains)) {
            // Business routes are not allowed on central domains
            abort(404, 'Business features are only available on tenant domains.');
        }

        // Get tenant by domain for status checking (without initializing tenancy yet)
        $tenant = $this->getTenantByDomain($currentDomain);
        
        if ($tenant) {
            // Check tenant status BEFORE expensive tenancy initialization
            $status = $tenant->status ?? 'active';
            
            switch ($status) {
                case 'blocked':
                    return response()->view('errors.tenant-blocked', ['tenant' => $tenant], 403);
                    
                case 'suspended':
                    return response()->view('errors.tenant-suspended', ['tenant' => $tenant], 503);
                    
                case 'inactive':
                    return response()->view('errors.tenant-inactive', ['tenant' => $tenant], 503);
                    
                case 'maintenance':
                    return response()->view('errors.tenant-maintenance', ['tenant' => $tenant], 503);
            }
        }

        // If tenant is active or status check passed, let stancl/tenancy handle
        // database switching and tenant initialization via DatabaseTenancyBootstrapper
        // This ensures proper connection management and optimal performance
        
        return $next($request);
    }
    
    /**
     * Get tenant by domain without initializing full tenancy context.
     * This is optimized for status checking only.
     */
    protected function getTenantByDomain(string $domain): ?Tenant
    {
        try {
            return Tenant::query()
                ->whereHas('domains', function ($query) use ($domain) {
                    $query->where('domain', $domain);
                })
                ->first();
        } catch (\Exception $e) {
            // If there's any issue with tenant lookup, let stancl handle it
            return null;
        }
    }
}
