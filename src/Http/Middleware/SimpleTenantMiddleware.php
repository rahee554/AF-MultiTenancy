<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Http\Response;

class SimpleTenantMiddleware
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
        // Skip tenancy for assets (CSS, JS, images, etc.) - COMPLETELY bypass
        if ($this->shouldSkipTenancy($request)) {
            return $next($request);
        }

        // Skip for any path that starts with known asset directories
        $path = $request->path();
        if ($this->isCompleteAssetPath($path)) {
            return $next($request);
        }

        // Initialize tenancy by domain
        $initializeTenancy = app(InitializeTenancyByDomain::class);
        $preventAccess = app(PreventAccessFromCentralDomains::class);
        
        // First, initialize tenancy
        return $initializeTenancy->handle($request, function ($request) use ($next, $preventAccess) {
            // Then prevent access from central domains
            return $preventAccess->handle($request, function ($request) use ($next) {
                // Check if tenant is active
                return $this->checkTenantStatus($request, $next);
            });
        });
    }

    /**
     * Check if this is a complete asset path that should bypass tenancy entirely
     */
    protected function isCompleteAssetPath(string $path): bool
    {
        // Bypass any path containing 'assets', 'build', 'css', 'js'
        $bypasses = ['assets', 'build', 'css', 'js', 'images', 'img', 'fonts', 'media', 'storage', 'vendor'];
        
        foreach ($bypasses as $bypass) {
            if (str_contains($path, $bypass)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine if tenancy should be skipped for this request
     */
    protected function shouldSkipTenancy(Request $request): bool
    {
        $path = $request->path();
        
        // Skip for asset paths
        if ($this->isAssetPath($path)) {
            return true;
        }

        // Skip for API routes that shouldn't have tenancy
        if (str_starts_with($path, 'api/') && !str_starts_with($path, 'api/tenant/')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the request path is for assets
     */
    protected function isAssetPath(string $path): bool
    {
        // Common asset patterns
        $assetPatterns = [
            '/\.css(\?.*)?$/',
            '/\.js(\?.*)?$/', 
            '/\.png(\?.*)?$/',
            '/\.jpg(\?.*)?$/',
            '/\.jpeg(\?.*)?$/',
            '/\.gif(\?.*)?$/',
            '/\.svg(\?.*)?$/',
            '/\.ico(\?.*)?$/',
            '/\.woff(\?.*)?$/',
            '/\.woff2(\?.*)?$/',
            '/\.ttf(\?.*)?$/',
            '/\.eot(\?.*)?$/',
            '/\.map(\?.*)?$/',
        ];

        foreach ($assetPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        // Check for asset directories
        $assetDirectories = [
            'build/',
            'assets/',
            'css/',
            'js/',
            'images/',
            'img/',
            'fonts/',
            'media/',
            'storage/',
            'vendor/',
        ];

        foreach ($assetDirectories as $dir) {
            if (str_starts_with($path, $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if tenant is active and handle inactive tenants
     */
    protected function checkTenantStatus(Request $request, Closure $next)
    {
        // Get current tenant
        $tenant = tenant();
        
        if (!$tenant) {
            return $next($request);
        }

        // Check if tenant has status column and if it's active
        if (isset($tenant->status)) {
            if ($tenant->status !== 'active') {
                // Return custom error page for inactive tenant
                return $this->renderInactiveTenantPage($tenant);
            }
        }

        return $next($request);
    }

    /**
     * Render inactive tenant error page
     */
    protected function renderInactiveTenantPage($tenant)
    {
        $content = $this->getInactiveTenantPageContent($tenant);
        
        return new Response($content, 503, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Get the content for inactive tenant page
     */
    protected function getInactiveTenantPageContent($tenant)
    {
        $tenantName = $tenant->name ?? 'Tenant';
        $status = $tenant->status ?? 'inactive';
        
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Temporarily Unavailable</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            margin: 2rem;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        h1 {
            color: #2d3748;
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .tenant-name {
            color: #667eea;
            font-weight: 700;
        }
        
        p {
            color: #718096;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #c53030;
        }
        
        .status-suspended {
            background: #fef5e7;
            color: #d69e2e;
        }
        
        .status-maintenance {
            background: #e6fffa;
            color: #319795;
        }
        
        .contact-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .contact-info h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .contact-info p {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        @media (max-width: 640px) {
            .container {
                margin: 1rem;
                padding: 2rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>Service Temporarily Unavailable</h1>
        <p>
            The service for <span class="tenant-name">' . htmlspecialchars($tenantName) . '</span> 
            is currently not available.
        </p>
        
        <div class="status-badge status-' . strtolower($status) . '">
            Status: ' . ucfirst($status) . '
        </div>
        
        <div class="contact-info">
            <h3>What does this mean?</h3>
            <p>• The service may be undergoing maintenance</p>
            <p>• The account may be temporarily suspended</p>
            <p>• Please contact support for more information</p>
        </div>
        
        <div class="contact-info">
            <h3>Need Help?</h3>
            <p>Please contact your system administrator or support team for assistance.</p>
        </div>
    </div>
</body>
</html>';
    }
}
