<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Illuminate\Support\Facades\Log;

class TenantAuthMiddleware
{
    /**
     * Handle an incoming request for authentication routes
     * CRITICAL: Must properly initialize tenant context for auth routes
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip tenancy initialization completely for assets
        if ($this->isAssetRequest($request)) {
            return $next($request);
        }

        $domain = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // Skip tenant initialization for central domains
        if (in_array($domain, $centralDomains)) {
            Log::info('TenantAuthMiddleware: Central domain detected, skipping tenancy', [
                'domain' => $domain
            ]);
            return $next($request);
        }

        // CRITICAL FIX: For authentication routes, MUST initialize tenancy properly
        // This ensures authentication happens in the tenant database, not central
        try {
            $initializeTenancy = app(InitializeTenancyByDomain::class);
            $preventAccess = app(PreventAccessFromCentralDomains::class);
            
            return $initializeTenancy->handle($request, function ($request) use ($next, $preventAccess, $domain) {
                return $preventAccess->handle($request, function ($request) use ($next, $domain) {
                    // Log successful tenant initialization for debugging
                    if (function_exists('tenant') && tenant()) {
                        Log::info('TenantAuthMiddleware: Successfully initialized tenant context', [
                            'domain' => $domain,
                            'tenant_id' => tenant()->id,
                            'route' => $request->path()
                        ]);
                    }
                    
                    return $next($request);
                });
            });
        } catch (\Exception $e) {
            Log::error('TenantAuthMiddleware: Failed to initialize tenancy', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'route' => $request->path()
            ]);
            
            // If we can't initialize tenancy, we should not proceed with auth
            abort(500, 'Tenant context initialization failed');
        }
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
