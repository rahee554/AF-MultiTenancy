<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use ArtflowStudio\Tenancy\Services\TenantContextCache;

class SmartDomainResolver
{
    /** @var TenantContextCache */
    protected $cache;
    
    public function __construct()
    {
        $this->cache = new TenantContextCache();
    }
    
    /**
     * Handle an incoming request with intelligent caching and tenant resolution.
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
        
        // Not a central domain - try to resolve tenant with multi-layer caching
        try {
            $tenant = $this->cache->getTenantByDomain($currentDomain);
            
            if ($tenant && $tenant->status === 'active') {
                // Initialize tenancy context using stancl/tenancy's proper method
                tenancy()->initialize($tenant);
                
                // Add performance headers for debugging
                $request->headers->set('X-Tenant-ID', $tenant->id);
                $request->headers->set('X-Tenant-Cache', 'hit');
                
                // Continue with tenant middleware to handle status checks
                $tenantMiddleware = new TenantMiddleware();
                return $tenantMiddleware->handle($request, $next);
                
            } elseif ($tenant && $tenant->status !== 'active') {
                // Tenant exists but is not active
                return $this->handleInactiveTenant($tenant, $currentDomain);
                
            } else {
                // Tenant not found for this domain
                $request->headers->set('X-Tenant-Cache', 'miss');
                return $this->handleTenantNotFound($currentDomain);
            }
        } catch (\Exception $e) {
            // Handle tenant resolution errors gracefully
            return $this->handleTenantError($currentDomain, $e);
        }
    }
    
    /**
     * Handle inactive tenant
     */
    protected function handleInactiveTenant($tenant, string $domain): Response
    {
        $status = $tenant->status ?? 'inactive';
        $statusMessages = [
            'inactive' => 'This tenant is currently inactive.',
            'suspended' => 'This tenant has been suspended.',
            'blocked' => 'Access to this tenant has been blocked.',
        ];
        
        $message = $statusMessages[$status] ?? 'This tenant is not available.';
        
        // Check if custom error pages are configured
        $errorView = config("artflow-tenancy.error_pages.{$status}");
        if ($errorView && view()->exists($errorView)) {
            return response()->view($errorView, [
                'tenant' => $tenant,
                'domain' => $domain,
                'status' => $status,
                'message' => $message,
            ], 403);
        }
        
        // Fallback to simple error response
        abort(403, $message);
    }
    
    /**
     * Handle tenant not found
     */
    protected function handleTenantNotFound(string $domain): Response
    {
        // Check if there's a fallback configuration
        $fallbackRedirect = config('artflow-tenancy.fallback.redirect');
        if ($fallbackRedirect) {
            return redirect($fallbackRedirect);
        }
        
        // Check for custom 404 view
        $notFoundView = config('artflow-tenancy.error_pages.not_found');
        if ($notFoundView && view()->exists($notFoundView)) {
            return response()->view($notFoundView, [
                'domain' => $domain,
                'message' => 'Tenant could not be identified on domain ' . $domain,
            ], 404);
        }
        
        // Fallback to simple 404
        abort(404, 'Tenant could not be identified on domain ' . $domain);
    }
    
    /**
     * Handle tenant resolution errors
     */
    protected function handleTenantError(string $domain, \Exception $e): Response
    {
        // Log the error for debugging
        logger()->error('Tenant resolution error', [
            'domain' => $domain,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        // In production, show a generic error
        if (app()->environment('production')) {
            $message = 'Service temporarily unavailable. Please try again later.';
        } else {
            $message = 'Tenant resolution error: ' . $e->getMessage();
        }
        
        abort(500, $message);
    }
}
