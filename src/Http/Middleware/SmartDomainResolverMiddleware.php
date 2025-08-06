<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Smart Domain Resolver Middleware
 * 
 * This middleware intelligently detects whether the current domain is a central domain
 * or a tenant domain, then applies the appropriate tenancy context automatically.
 * 
 * This allows the same routes (like /login, /dashboard) to work on both:
 * - Central domains (localhost, admin.yoursite.com) → No tenant context
 * - Tenant domains (tenant1.yoursite.com, tenant2.yoursite.com) → Tenant context
 * 
 * Usage: Route::middleware(['central.tenant.web'])
 */
class SmartDomainResolverMiddleware
{
    /**
     * Handle an incoming request with smart domain detection.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentDomain = $request->getHost();
        $centralDomains = $this->getCentralDomains();
        
        // Determine if this is a central or tenant domain
        $isCentralDomain = in_array($currentDomain, $centralDomains);
        
        if ($isCentralDomain) {
            // CENTRAL DOMAIN FLOW
            return $this->handleCentralDomain($request, $next);
        } else {
            // TENANT DOMAIN FLOW  
            return $this->handleTenantDomain($request, $next);
        }
    }
    
    /**
     * Handle request for central domains
     */
    protected function handleCentralDomain(Request $request, Closure $next): Response
    {
        // Ensure no tenant context is active
        if (app()->bound('tenant')) {
            app()->forgetInstance('tenant');
        }
        
        // Add central domain markers
        $request->attributes->set('domain_type', 'central');
        $request->attributes->set('is_central', true);
        $request->attributes->set('is_tenant', false);
        
        // Log central domain access
        Log::info('Central domain access detected', [
            'domain' => $request->getHost(),
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        
        // Share central context with views
        view()->share([
            'domainType' => 'central',
            'isCentral' => true,
            'isTenant' => false,
            'currentTenant' => null,
        ]);
        
        return $next($request);
    }
    
    /**
     * Handle request for tenant domains with full tenancy initialization
     */
    protected function handleTenantDomain(Request $request, Closure $next): Response
    {
        // Initialize tenancy by domain (stancl/tenancy)
        $initializeTenancy = new \Stancl\Tenancy\Middleware\InitializeTenancyByDomain();
        
        return $initializeTenancy->handle($request, function ($request) use ($next) {
            // Prevent access from central domains (stancl/tenancy)
            $preventCentral = new \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains();
            
            return $preventCentral->handle($request, function ($request) use ($next) {
                // Scope sessions per tenant (stancl/tenancy) - CRITICAL for Livewire
                $scopeSessions = new \Stancl\Tenancy\Middleware\ScopeSessions();
                
                return $scopeSessions->handle($request, function ($request) use ($next) {
                    // Apply our tenant enhancements
                    $tenantEnhancements = new \ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware();
                    
                    return $tenantEnhancements->handle($request, function ($request) use ($next) {
                        // Get current tenant after initialization
                        $currentTenant = tenant();
                        
                        // Add tenant domain markers
                        $request->attributes->set('domain_type', 'tenant');
                        $request->attributes->set('is_central', false);
                        $request->attributes->set('is_tenant', true);
                        $request->attributes->set('tenant', $currentTenant);
                        
                        // Log tenant domain access
                        Log::info('Tenant domain access detected', [
                            'domain' => $request->getHost(),
                            'tenant_id' => $currentTenant ? $currentTenant->id : null,
                            'path' => $request->path(),
                            'method' => $request->method(),
                            'ip' => $request->ip(),
                        ]);
                        
                        // Share tenant context with views
                        view()->share([
                            'domainType' => 'tenant',
                            'isCentral' => false,
                            'isTenant' => true,
                            'currentTenant' => $currentTenant,
                        ]);
                        
                        return $next($request);
                    });
                });
            });
        });
    }
    
    /**
     * Get list of central domains from configuration
     */
    protected function getCentralDomains(): array
    {
        $centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);
        
        // Add APP_DOMAIN to central domains if set
        $appDomain = config('app.domain') ?? env('APP_DOMAIN');
        if ($appDomain && !in_array($appDomain, $centralDomains)) {
            $centralDomains[] = $appDomain;
        }
        
        // Add any additional central domains from our config
        $additionalCentral = config('artflow-tenancy.additional_central_domains', []);
        $centralDomains = array_merge($centralDomains, $additionalCentral);
        
        return array_unique($centralDomains);
    }
}
