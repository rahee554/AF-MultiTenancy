<?php

namespace ArtflowStudio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\Database\Models\Tenant;
use ArtflowStudio\Tenancy\Services\CachedTenantResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Early Tenant Identification Middleware
 * 
 * Identifies tenant as early as possible in the request lifecycle
 * with caching and multiple resolution strategies
 */
class EarlyIdentificationMiddleware extends IdentificationMiddleware
{
    protected $cachedResolver;
    protected $domainResolver;
    protected $pathResolver;

    public function __construct(
        CachedTenantResolver $cachedResolver,
        DomainTenantResolver $domainResolver,
        PathTenantResolver $pathResolver
    ) {
        $this->cachedResolver = $cachedResolver;
        $this->domainResolver = $domainResolver;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Handle incoming request with early tenant identification
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        try {
            // Attempt early identification
            $tenant = $this->identifyTenantEarly($request);
            
            if (!$tenant) {
                return $this->handleTenantNotFound($request);
            }

            // Initialize tenant context immediately
            tenancy()->initialize($tenant);
            
            // Log successful identification
            $this->logIdentification($tenant, $startTime, $request);
            
            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('Early tenant identification failed', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return $this->handleIdentificationError($request, $e);
        }
    }

    /**
     * Identify tenant using multiple strategies
     */
    protected function identifyTenantEarly(Request $request): ?Tenant
    {
        // Strategy 1: Cached domain lookup (fastest)
        $tenant = $this->identifyByCachedDomain($request);
        if ($tenant) {
            return $tenant;
        }

        // Strategy 2: Direct domain lookup
        $tenant = $this->identifyByDomain($request);
        if ($tenant) {
            // Cache for next time
            $this->cacheIdentification($request, $tenant);
            return $tenant;
        }

        // Strategy 3: Path-based identification
        $tenant = $this->identifyByPath($request);
        if ($tenant) {
            // Cache for next time
            $this->cacheIdentification($request, $tenant);
            return $tenant;
        }

        // Strategy 4: Subdomain extraction
        $tenant = $this->identifyBySubdomain($request);
        if ($tenant) {
            // Cache for next time
            $this->cacheIdentification($request, $tenant);
            return $tenant;
        }

        return null;
    }

    /**
     * Identify tenant using cached domain lookup
     */
    protected function identifyByCachedDomain(Request $request): ?Tenant
    {
        try {
            $domain = $request->getHost();
            return $this->cachedResolver->resolve($domain);
        } catch (\Exception $e) {
            Log::debug('Cached domain identification failed', [
                'domain' => $request->getHost(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Identify tenant by domain
     */
    protected function identifyByDomain(Request $request): ?Tenant
    {
        try {
            return $this->domainResolver->resolve($request);
        } catch (\Exception $e) {
            Log::debug('Domain identification failed', [
                'domain' => $request->getHost(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Identify tenant by path
     */
    protected function identifyByPath(Request $request): ?Tenant
    {
        try {
            return $this->pathResolver->resolve($request);
        } catch (\Exception $e) {
            Log::debug('Path identification failed', [
                'path' => $request->path(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Identify tenant by subdomain extraction
     */
    protected function identifyBySubdomain(Request $request): ?Tenant
    {
        try {
            $host = $request->getHost();
            $parts = explode('.', $host);
            
            if (count($parts) < 3) {
                return null; // No subdomain
            }
            
            $subdomain = $parts[0];
            
            // Skip common non-tenant subdomains
            $skipSubdomains = ['www', 'api', 'admin', 'mail', 'ftp'];
            if (in_array($subdomain, $skipSubdomains)) {
                return null;
            }
            
            // Try to find tenant by subdomain
            $tenant = Tenant::where('id', $subdomain)->first();
            
            if ($tenant) {
                Log::debug('Tenant identified by subdomain', [
                    'subdomain' => $subdomain,
                    'tenant_id' => $tenant->id
                ]);
            }
            
            return $tenant;
            
        } catch (\Exception $e) {
            Log::debug('Subdomain identification failed', [
                'host' => $request->getHost(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cache successful identification
     */
    protected function cacheIdentification(Request $request, Tenant $tenant): void
    {
        try {
            $domain = $request->getHost();
            $cacheKey = "tenant_domain:{$domain}";
            
            Cache::put($cacheKey, [
                'tenant_id' => $tenant->id,
                'identified_at' => now()->toISOString(),
                'method' => 'early_identification'
            ], config('tenancy.cache.ttl', 3600));
            
        } catch (\Exception $e) {
            Log::warning('Failed to cache tenant identification', [
                'domain' => $request->getHost(),
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle case when tenant is not found
     */
    protected function handleTenantNotFound(Request $request): Response
    {
        Log::warning('Tenant not found for request', [
            'url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'path' => $request->path(),
            'ip' => $request->ip()
        ]);

        // Check if this is API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'The requested tenant could not be identified.',
                'code' => 'TENANT_NOT_FOUND'
            ], 404);
        }

        // For web requests, you might want to redirect to a landing page
        // or show a custom 404 page
        return response()->view('errors.tenant-not-found', [
            'domain' => $request->getHost()
        ], 404);
    }

    /**
     * Handle identification errors
     */
    protected function handleIdentificationError(Request $request, \Exception $e): Response
    {
        Log::error('Tenant identification error', [
            'url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Check if this is API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Identification failed',
                'message' => 'An error occurred while identifying the tenant.',
                'code' => 'IDENTIFICATION_ERROR'
            ], 500);
        }

        // For web requests, show error page
        return response()->view('errors.tenant-identification-error', [
            'domain' => $request->getHost(),
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
        ], 500);
    }

    /**
     * Log successful identification
     */
    protected function logIdentification(Tenant $tenant, float $startTime, Request $request): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('Tenant identified successfully', [
            'tenant_id' => $tenant->id,
            'domain' => $request->getHost(),
            'duration_ms' => $duration,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);

        // Track identification metrics if needed
        $this->trackIdentificationMetrics($tenant, $duration, $request);
    }

    /**
     * Track identification metrics
     */
    protected function trackIdentificationMetrics(Tenant $tenant, float $duration, Request $request): void
    {
        try {
            $metricsKey = "tenant_metrics:{$tenant->id}";
            $metrics = Cache::get($metricsKey, [
                'identifications' => 0,
                'total_duration' => 0,
                'last_identification' => null
            ]);
            
            $metrics['identifications']++;
            $metrics['total_duration'] += $duration;
            $metrics['last_identification'] = now()->toISOString();
            $metrics['average_duration'] = round($metrics['total_duration'] / $metrics['identifications'], 2);
            
            Cache::put($metricsKey, $metrics, 3600); // Store for 1 hour
            
        } catch (\Exception $e) {
            Log::debug('Failed to track identification metrics', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get identification metrics for tenant
     */
    public function getIdentificationMetrics(string $tenantId): array
    {
        $metricsKey = "tenant_metrics:{$tenantId}";
        return Cache::get($metricsKey, [
            'identifications' => 0,
            'total_duration' => 0,
            'average_duration' => 0,
            'last_identification' => null
        ]);
    }

    /**
     * Clear identification cache for domain
     */
    public function clearIdentificationCache(string $domain): bool
    {
        $cacheKey = "tenant_domain:{$domain}";
        return Cache::forget($cacheKey);
    }

    /**
     * Warm up identification cache
     */
    public function warmUpIdentificationCache(): int
    {
        $warmedCount = 0;
        
        try {
            // Get all domains and warm up cache
            $domains = \Stancl\Tenancy\Database\Models\Domain::with('tenant')->get();
            
            foreach ($domains as $domain) {
                $cacheKey = "tenant_domain:{$domain->domain}";
                
                Cache::put($cacheKey, [
                    'tenant_id' => $domain->tenant->id,
                    'identified_at' => now()->toISOString(),
                    'method' => 'warmup'
                ], config('tenancy.cache.ttl', 3600));
                
                $warmedCount++;
            }
            
            Log::info("Identification cache warmed up", [
                'domains_cached' => $warmedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to warm up identification cache', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $warmedCount;
    }
}
