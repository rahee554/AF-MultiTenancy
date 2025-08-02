<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class SmartDomainResolver
{
    /**
     * Handle an incoming request and route it to central or tenant middleware.
     * This middleware acts as a router between central and tenant domains.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        $currentDomain = $request->getHost();
        
        // Add APP_DOMAIN to central domains if set
        $appDomain = config('app.domain') ?? env('APP_DOMAIN');
        if ($appDomain && !in_array($appDomain, $centralDomains)) {
            $centralDomains[] = $appDomain;
        }
        
        // Check if this is a central domain
        if (in_array($currentDomain, $centralDomains)) {
            // Central domain - proceed without tenant initialization
            $request->headers->set('X-Central-Domain', 'true');
            return $next($request);
        }
        
        // Not a central domain - initialize tenancy
        try {
            // Try to resolve tenant by domain using the model directly
            $domain = \ArtflowStudio\Tenancy\Models\Domain::where('domain', $currentDomain)->first();
            
            if ($domain && $domain->tenant) {
                // Initialize tenancy context
                tenancy()->initialize($domain->tenant);
                
                // Check tenant status
                $tenantMiddleware = new TenantMiddleware();
                return $tenantMiddleware->handle($request, $next);
            } else {
                // Tenant not found for this domain
                abort(404, 'Tenant could not be identified on domain ' . $currentDomain);
            }
        } catch (\Exception $e) {
            // Handle tenant resolution errors
            abort(404, 'Tenant could not be identified on domain ' . $currentDomain . '. Error: ' . $e->getMessage());
        }
    }
}
