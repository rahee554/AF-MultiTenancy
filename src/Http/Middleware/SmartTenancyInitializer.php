<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class SmartTenancyInitializer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip tenancy initialization for assets, API endpoints, and other excluded paths
        if ($this->shouldSkipTenancy($request)) {
            return $next($request);
        }

        // Initialize tenancy using the domain-based middleware
        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }

    /**
     * Determine if tenancy should be skipped for this request
     */
    protected function shouldSkipTenancy(Request $request): bool
    {
        $path = $request->path();
        
        // Skip for asset files
        if ($this->isAssetPath($path)) {
            return true;
        }

        // Skip for specific routes that should remain central
        $skipPaths = [
            'api/*',
            'storage/*',
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'pulse/*',
            'livewire/*',
            '_ignition/*',
        ];

        foreach ($skipPaths as $skipPath) {
            if ($request->is($skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the path is for an asset file
     */
    protected function isAssetPath(string $path): bool
    {
        // Common asset file extensions
        $assetExtensions = [
            'css', 'js', 'map', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 
            'woff', 'woff2', 'ttf', 'otf', 'eot', 'pdf', 'mp4', 'mp3', 
            'webp', 'webm', 'json', 'xml', 'txt'
        ];

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        return in_array(strtolower($extension), $assetExtensions);
    }
}
