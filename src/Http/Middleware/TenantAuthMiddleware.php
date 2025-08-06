<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Authentication Middleware
 * 
 * This middleware is designed to work with stancl/tenancy for auth routes.
 * It ensures proper tenant context is maintained during authentication.
 */
class TenantAuthMiddleware
{
    /**
     * Handle an incoming request for authentication routes
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip tenancy for asset requests
        if ($this->isAssetRequest($request)) {
            return $next($request);
        }

        $domain = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // For central domains, skip tenant processing
        if (in_array($domain, $centralDomains)) {
            Log::debug('TenantAuthMiddleware: Central domain detected, skipping tenancy', [
                'domain' => $domain
            ]);
            return $next($request);
        }

        // For tenant domains, we let stancl/tenancy handle the heavy lifting
        // This middleware just ensures logging and handles edge cases
        
        $response = $next($request);
        
        // Log tenant context after processing (if tenant was initialized)
        if (function_exists('tenant') && tenant()) {
            Log::debug('TenantAuthMiddleware: Request processed with tenant context', [
                'domain' => $domain,
                'tenant_id' => tenant('id'),
                'route' => $request->route()?->getName(),
            ]);
        }
        
        return $response;
    }
    /**
     * Check if this is an asset request
     */
    protected function isAssetRequest(Request $request): bool
    {
        $path = $request->path();
        
        // Asset file extensions
        $assetExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), $assetExtensions)) {
            return true;
        }

        // Asset directories
        $assetDirs = ['build', 'assets', 'css', 'js', 'images', 'img', 'fonts', 'media', 'storage', 'vendor'];
        
        foreach ($assetDirs as $dir) {
            if (str_starts_with($path, $dir . '/')) {
                return true;
            }
        }

        return false;
    }
}
