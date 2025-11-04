<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Tenancy;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook; // Add this import
use Illuminate\Support\Facades\Log;

class TenantAuthMiddleware
{
    protected $tenancyMiddleware;
    protected $tenancy;
    protected $resolver;

    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
        $this->tenancyMiddleware = new InitializeTenancyByDomain($tenancy, $resolver);
    }

    /**
     * Handle an incoming request for authentication routes
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();
        $centralDomains = config('artflow-tenancy.central_domains', []);

        // Check if this is a central domain
        if (in_array($domain, $centralDomains)) {
            // Central domain - no tenant initialization needed
            Log::info('TenantAuthMiddleware: Central domain detected', [
                'domain' => $domain,
                'path' => $request->path()
            ]);

            $request->attributes->set('is_central', true);
            $request->attributes->set('tenant', null);

            return $next($request);
        }

        // Tenant domain - attempt to initialize tenancy
        try {
            Log::info('TenantAuthMiddleware: Attempting tenant initialization', [
                'domain' => $domain,
                'path' => $request->path()
            ]);

            return $this->tenancyMiddleware->handle($request, function ($req) use ($next, $domain) {
                $tenant = tenant();
                
                if ($tenant) {
                    // Successful tenant initialization
                    Log::info('TenantAuthMiddleware: Tenant initialized successfully', [
                        'tenant_id' => $tenant->id,
                        'domain' => $domain,
                        'path' => $req->path()
                    ]);

                    // Optionally bootstrap additional tenancy setup
                    TenancyBootstrapperHook::bootstrap(); // <-- Add this line here

                    // Add tenant context to request
                    $req->attributes->set('is_central', false);
                    $req->attributes->set('tenant', $tenant);

                    // Share tenant data with views for auth pages
                    view()->share('currentTenant', $tenant);
                    view()->share('tenantDomain', $domain);
                }

                return $next($req);
            });
            
        } catch (TenantCouldNotBeIdentifiedException $e) {
            // Tenant not found - log and continue without tenant context
            Log::warning('TenantAuthMiddleware: Tenant not found', [
                'domain' => $domain,
                'path' => $request->path(),
                'error' => $e->getMessage()
            ]);

            $request->attributes->set('is_central', true);
            $request->attributes->set('tenant', null);
            $request->attributes->set('tenant_lookup_failed', true);

            return $next($request);
            
        } catch (\Exception $e) {
            // Other exceptions - log and continue
            Log::error('TenantAuthMiddleware: Unexpected error during tenant initialization', [
                'domain' => $domain,
                'path' => $request->path(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $request->attributes->set('is_central', true);
            $request->attributes->set('tenant', null);
            $request->attributes->set('initialization_error', true);

            return $next($request);
        }
    }

    /**
     * Check if the current request is for a central domain
     */
    protected function isCentralDomain(string $domain): bool
    {
        $centralDomains = config('artflow-tenancy.central_domains', []);
        return in_array($domain, $centralDomains);
    }
}
