<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Tenancy;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use ArtflowStudio\Tenancy\Services\TenantService;

class SmartDomainResolverMiddleware
{
    protected $tenantService;
    protected $tenancy;

    public function __construct(TenantService $tenantService, Tenancy $tenancy)
    {
        $this->tenantService = $tenantService;
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request with smart domain resolution
     * Priority: 1. Try tenant domain, 2. Fall back to central
     */
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();
        
        Log::debug('SmartDomainResolver: Processing domain', ['domain' => $domain]);

        // Step 1: Try to resolve as tenant domain first
        if ($this->attemptTenantResolution($request, $domain)) {
            Log::info('SmartDomainResolver: Resolved as tenant domain', [
                'domain' => $domain,
                'tenant_id' => tenant('id') ?? 'unknown'
            ]);
            
            // Apply tenant context middleware chain
            return $this->applyTenantMiddleware($request, $next);
        }

        // Step 2: Check if it's a central domain
        if ($this->isCentralDomain($domain)) {
            Log::info('SmartDomainResolver: Resolved as central domain', ['domain' => $domain]);
            
            // Ensure no tenant context is active
            $this->ensureCentralContext();
            
            return $next($request);
        }

        // Step 3: Domain not found in either context
        Log::warning('SmartDomainResolver: Domain not found in tenant or central config', [
            'domain' => $domain,
            'request_uri' => $request->getRequestUri()
        ]);

        // Return 404 or redirect based on configuration
        return $this->handleUnknownDomain($request);
    }

    /**
     * Attempt to resolve domain as tenant
     */
    protected function attemptTenantResolution(Request $request, string $domain): bool
    {
        try {
            // Use cache to avoid repeated database queries
            $cacheKey = "tenant_domain:{$domain}";
            
            return Cache::remember($cacheKey, 300, function () use ($domain) {
                $resolver = app(DomainTenantResolver::class);
                
                try {
                    $tenant = $resolver->resolve($domain);
                    return $tenant !== null;
                } catch (\Exception $e) {
                    Log::debug('SmartDomainResolver: Tenant resolution failed', [
                        'domain' => $domain,
                        'error' => $e->getMessage()
                    ]);
                    return false;
                }
            });
        } catch (\Exception $e) {
            Log::error('SmartDomainResolver: Error during tenant resolution', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Apply tenant middleware chain
     */
    protected function applyTenantMiddleware(Request $request, Closure $next)
    {
        // Initialize tenancy using stancl/tenancy bootstrappers
        try {
            // The tenant should already be identified by the resolver
            // Just ensure bootstrappers are run
            $this->tenancy->initialize(tenant());
            
            // Apply session scoping if this is a web request
            if ($request->hasSession()) {
                $this->scopeSession($request);
            }
            
            return $next($request);
        } catch (\Exception $e) {
            Log::error('SmartDomainResolver: Error applying tenant middleware', [
                'domain' => $request->getHost(),
                'error' => $e->getMessage()
            ]);
            
            // Fall back to central context
            $this->ensureCentralContext();
            return $next($request);
        }
    }

    /**
     * Check if domain is configured as central
     */
    protected function isCentralDomain(string $domain): bool
    {
        $centralDomains = config('artflow-tenancy.central_domains', []);
        
        // Check exact match
        if (in_array($domain, $centralDomains)) {
            return true;
        }
        
        // Check wildcard patterns
        foreach ($centralDomains as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match("/^{$regex}$/", $domain)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Ensure central context (no tenant active)
     */
    protected function ensureCentralContext(): void
    {
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }
    }

    /**
     * Scope session for tenant
     */
    protected function scopeSession(Request $request): void
    {
        if (tenant() && $request->hasSession()) {
            $session = $request->session();
            $tenantId = tenant('id');
            
            // Apply tenant-specific session configuration
            $sessionName = config('session.cookie') . '_tenant_' . $tenantId;
            config(['session.cookie' => $sessionName]);
            
            // Regenerate session ID with tenant scope
            if (!$session->has('_tenant_scoped')) {
                $session->put('_tenant_scoped', true);
                $session->put('_tenant_id', $tenantId);
            }
        }
    }

    /**
     * Handle unknown domain
     */
    protected function handleUnknownDomain(Request $request)
    {
        $defaultAction = config('artflow-tenancy.unknown_domain_action', '404');
        
        switch ($defaultAction) {
            case 'redirect':
                $redirectUrl = config('artflow-tenancy.unknown_domain_redirect', '/');
                return redirect($redirectUrl);
                
            case 'central':
                Log::info('SmartDomainResolver: Unknown domain redirected to central context');
                $this->ensureCentralContext();
                return $next($request);
                
            default:
                abort(404, 'Domain not found');
        }
    }
}
