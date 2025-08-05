<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AssetBypassMiddleware
{
    /**
     * Handle an incoming request and completely bypass tenancy for assets
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Completely bypass for any asset-related requests
        $path = $request->path();
        
        // Check for asset file extensions
        $assetExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), $assetExtensions)) {
            return $next($request);
        }

        // Check for asset directories
        $assetDirs = ['build', 'assets', 'css', 'js', 'images', 'img', 'fonts', 'media', 'storage', 'vendor'];
        
        foreach ($assetDirs as $dir) {
            if (str_starts_with($path, $dir . '/') || str_contains($path, '/' . $dir . '/')) {
                return $next($request);
            }
        }

        // If not an asset, continue with tenant middleware
        $tenantMiddleware = app(\ArtflowStudio\Tenancy\Http\Middleware\SimpleTenantMiddleware::class);
        return $tenantMiddleware->handle($request, $next);
    }
}
