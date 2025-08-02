<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CentralDomainMiddleware
{
    /**
     * Handle an incoming request for central domain routes.
     * This middleware ensures the request is coming from a central domain and prevents tenant initialization.
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
        if (!in_array($currentDomain, $centralDomains)) {
            // Not a central domain - should be handled by tenant middleware
            abort(403, 'Access denied. This route is only available on central domains.');
        }

        // Ensure no tenant context is active for central domain
        if (app()->bound('tenant')) {
            // Clear any tenant context to prevent conflicts
            app()->forgetInstance('tenant');
        }

        // Add header to indicate this is a central domain request
        $request->headers->set('X-Central-Domain', 'true');

        return $next($request);
    }
}
