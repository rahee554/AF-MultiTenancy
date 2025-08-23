<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantResolver;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Cached Tenant Resolver
 * 
 * Implements cached lookup to avoid querying the central database 
 * for tenant resolution on every request.
 * 
 * Based on: https://tenancyforlaravel.com/docs/v3/cached-lookup
 */
class CachedTenantResolver implements TenantResolver
{
    protected $cacheStore;
    protected $cacheTtl;
    protected $cachePrefix;
    
    public function __construct()
    {
        $this->cacheStore = config('tenancy.cached_lookup.cache_store', 'redis');
        $this->cacheTtl = config('tenancy.cached_lookup.cache_ttl', 3600);
        $this->cachePrefix = config('tenancy.cached_lookup.cache_key_prefix', 'tenancy_lookup');
    }

    /**
     * Resolve tenant by domain with caching
     */
    public function resolve(...$args): ?Tenant
    {
        $domain = $args[0] ?? request()->getHost();
        
        if (!$this->isCachedLookupEnabled()) {
            return $this->resolveFresh($domain);
        }

        $cacheKey = $this->getCacheKey($domain);
        
        Log::debug('CachedTenantResolver: Attempting cached lookup', [
            'domain' => $domain,
            'cache_key' => $cacheKey,
            'cache_store' => $this->cacheStore
        ]);

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () use ($domain) {
            Log::debug('CachedTenantResolver: Cache miss, resolving fresh', [
                'domain' => $domain
            ]);
            
            $tenant = $this->resolveFresh($domain);
            
            if ($tenant) {
                Log::info('CachedTenantResolver: Tenant resolved and cached', [
                    'domain' => $domain,
                    'tenant_id' => $tenant->getTenantKey(),
                    'cache_ttl' => $this->cacheTtl
                ]);
            } else {
                Log::debug('CachedTenantResolver: No tenant found for domain', [
                    'domain' => $domain
                ]);
            }
            
            return $tenant;
        });
    }

    /**
     * Resolve tenant fresh from database (no cache)
     */
    protected function resolveFresh(string $domain): ?Tenant
    {
        try {
            $domainModel = Domain::where('domain', $domain)->first();
            
            if (!$domainModel) {
                return null;
            }
            
            return $domainModel->tenant;
            
        } catch (\Exception $e) {
            Log::error('CachedTenantResolver: Error resolving tenant', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get cache key for domain
     */
    protected function getCacheKey(string $domain): string
    {
        return "{$this->cachePrefix}:domain:{$domain}";
    }

    /**
     * Check if cached lookup is enabled
     */
    protected function isCachedLookupEnabled(): bool
    {
        return config('tenancy.cached_lookup.enabled', true);
    }

    /**
     * Clear cache for specific domain
     */
    public function clearCache(string $domain): bool
    {
        $cacheKey = $this->getCacheKey($domain);
        
        Log::info('CachedTenantResolver: Clearing cache for domain', [
            'domain' => $domain,
            'cache_key' => $cacheKey
        ]);
        
        return Cache::store($this->cacheStore)->forget($cacheKey);
    }

    /**
     * Clear all tenant lookup cache
     */
    public function clearAllCache(): bool
    {
        try {
            // For Redis, we can use pattern-based deletion
            if ($this->cacheStore === 'redis') {
                $redis = Cache::store('redis')->getRedis();
                $pattern = "{$this->cachePrefix}:domain:*";
                $keys = $redis->keys($pattern);
                
                if (!empty($keys)) {
                    $redis->del($keys);
                    Log::info('CachedTenantResolver: Cleared all cached lookups', [
                        'cleared_keys_count' => count($keys),
                        'pattern' => $pattern
                    ]);
                }
                
                return true;
            }
            
            // For other cache stores, we'd need to track keys differently
            Log::warning('CachedTenantResolver: Clear all cache not implemented for store', [
                'cache_store' => $this->cacheStore
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('CachedTenantResolver: Error clearing all cache', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Warm cache for all domains
     */
    public function warmCache(): int
    {
        $warmedCount = 0;
        
        try {
            Log::info('CachedTenantResolver: Starting cache warm-up');
            
            Domain::with('tenant')->chunk(100, function ($domains) use (&$warmedCount) {
                foreach ($domains as $domain) {
                    if ($domain->tenant) {
                        $cacheKey = $this->getCacheKey($domain->domain);
                        
                        Cache::store($this->cacheStore)->put(
                            $cacheKey, 
                            $domain->tenant, 
                            $this->cacheTtl
                        );
                        
                        $warmedCount++;
                    }
                }
            });
            
            Log::info('CachedTenantResolver: Cache warm-up completed', [
                'warmed_count' => $warmedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('CachedTenantResolver: Error during cache warm-up', [
                'error' => $e->getMessage(),
                'warmed_count' => $warmedCount
            ]);
        }
        
        return $warmedCount;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $stats = [
                'cache_store' => $this->cacheStore,
                'cache_ttl' => $this->cacheTtl,
                'cache_prefix' => $this->cachePrefix,
                'enabled' => $this->isCachedLookupEnabled(),
                'total_domains' => Domain::count(),
                'cached_domains' => 0,
            ];

            // Count cached domains (Redis only for now)
            if ($this->cacheStore === 'redis') {
                $redis = Cache::store('redis')->getRedis();
                $pattern = "{$this->cachePrefix}:domain:*";
                $keys = $redis->keys($pattern);
                $stats['cached_domains'] = count($keys);
            }

            return $stats;
            
        } catch (\Exception $e) {
            Log::error('CachedTenantResolver: Error getting cache stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage()
            ];
        }
    }
}
