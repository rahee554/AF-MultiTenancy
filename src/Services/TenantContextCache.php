<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

/**
 * Multi-layer tenant context caching system
 * 
 * Provides browser cache, memory cache, Redis cache, and database fallback
 * for optimal tenant resolution performance
 */
class TenantContextCache
{
    /** @var int Cache TTL in seconds */
    protected int $cacheTtl = 3600; // 1 hour
    
    /** @var int Browser cache TTL in seconds */
    protected int $browserCacheTtl = 1800; // 30 minutes
    
    /** @var array In-memory cache for current request */
    protected static array $memoryCache = [];
    
    /** @var array Legacy cache for backward compatibility */
    protected static $tenantCache = [];
    protected static $connectionCache = [];
    
    /**
     * Get tenant by domain with multi-layer caching
     */
    public function getTenantByDomain(string $domain): ?Tenant
    {
        // Layer 1: Memory cache (fastest - for same request)
        $memoryKey = "tenant_domain_{$domain}";
        if (isset(static::$memoryCache[$memoryKey])) {
            return static::$memoryCache[$memoryKey];
        }
        
        // Layer 2: Browser cache check (if available in cookie)
        $browserCached = $this->getBrowserCachedTenant($domain);
        if ($browserCached) {
            static::$memoryCache[$memoryKey] = $browserCached;
            return $browserCached;
        }
        
        // Layer 3: Redis cache (fast)
        $redisKey = "af_tenancy:domain:{$domain}";
        try {
            if (Cache::store('redis')->has($redisKey)) {
                $tenantData = Cache::store('redis')->get($redisKey);
                $tenant = $this->hydrateTenantsFromCache($tenantData);
                if ($tenant) {
                    static::$memoryCache[$memoryKey] = $tenant;
                    $this->setBrowserCache($domain, $tenant);
                    return $tenant;
                }
            }
        } catch (\Exception $e) {
            // Redis unavailable, continue to database
        }
        
        // Layer 4: Database cache (Laravel cache - likely database)
        $cacheKey = "tenant_domain_{$domain}";
        $tenant = Cache::remember($cacheKey, $this->cacheTtl, function () use ($domain) {
            return Tenant::whereHas('domains', function ($query) use ($domain) {
                $query->where('domain', $domain);
            })->with('domains')->first();
        });
        
        if ($tenant) {
            // Populate all cache layers
            static::$memoryCache[$memoryKey] = $tenant;
            $this->setBrowserCache($domain, $tenant);
            
            // Store in Redis if available
            try {
                Cache::store('redis')->put($redisKey, $this->serializeTenantForCache($tenant), $this->cacheTtl);
            } catch (\Exception $e) {
                // Redis unavailable, that's okay
            }
        }
        
        return $tenant;
    }
    
    /**
     * Get tenant context from browser cache (cookie)
     */
    protected function getBrowserCachedTenant(string $domain): ?Tenant
    {
        $cookieName = "af_tenant_" . md5($domain);
        $cookieValue = Cookie::get($cookieName);
        
        if (!$cookieValue) {
            return null;
        }
        
        try {
            $data = json_decode($cookieValue, true);
            if (!$data || !isset($data['id'], $data['expires']) || $data['expires'] < time()) {
                return null;
            }
            
            // Validate that tenant still exists and is active
            return Tenant::where('id', $data['id'])
                ->where('status', 'active')
                ->first();
                
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Set browser cache for tenant (cookie)
     */
    protected function setBrowserCache(string $domain, Tenant $tenant): void
    {
        $cookieName = "af_tenant_" . md5($domain);
        $cookieData = json_encode([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'domain' => $domain,
            'expires' => time() + $this->browserCacheTtl,
        ]);
        
        Cookie::queue($cookieName, $cookieData, $this->browserCacheTtl / 60); // Convert to minutes
    }
    
    /**
     * Serialize tenant for cache storage
     */
    protected function serializeTenantForCache(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'database' => $tenant->database,
            'status' => $tenant->status,
            'has_homepage' => $tenant->has_homepage,
            'domains' => $tenant->domains->pluck('domain')->toArray(),
            'cached_at' => time(),
        ];
    }
    
    /**
     * Hydrate tenant from cached data
     */
    protected function hydrateTenantsFromCache(array $data): ?Tenant
    {
        if (!isset($data['id']) || $data['cached_at'] < (time() - $this->cacheTtl)) {
            return null;
        }
        
        // Create tenant instance without hitting database
        $tenant = new Tenant();
        $tenant->id = $data['id'];
        $tenant->name = $data['name'];
        $tenant->database = $data['database'];
        $tenant->status = $data['status'];
        $tenant->has_homepage = $data['has_homepage'];
        $tenant->exists = true;
        
        return $tenant;
    }
    
    /**
     * Clear all cache layers for a tenant
     */
    public function clearTenantCache(string $domain): void
    {
        // Clear memory cache
        $memoryKey = "tenant_domain_{$domain}";
        unset(static::$memoryCache[$memoryKey]);
        
        // Clear Laravel cache
        Cache::forget("tenant_domain_{$domain}");
        
        // Clear Redis cache
        try {
            Cache::store('redis')->forget("af_tenancy:domain:{$domain}");
        } catch (\Exception $e) {
            // Redis unavailable
        }
        
        // Clear browser cache (expire cookie)
        $cookieName = "af_tenant_" . md5($domain);
        Cookie::queue(Cookie::forget($cookieName));
    }
    
    /**
     * Warm up cache for active tenants
     */
    public function warmUpCache(): int
    {
        $tenants = Tenant::where('status', 'active')->with('domains')->get();
        $warmedUp = 0;
        
        foreach ($tenants as $tenant) {
            foreach ($tenant->domains as $domain) {
                try {
                    // Store in Redis
                    $redisKey = "af_tenancy:domain:{$domain->domain}";
                    Cache::store('redis')->put($redisKey, $this->serializeTenantForCache($tenant), $this->cacheTtl);
                    
                    // Store in Laravel cache
                    $cacheKey = "tenant_domain_{$domain->domain}";
                    Cache::put($cacheKey, $tenant, $this->cacheTtl);
                    
                    $warmedUp++;
                } catch (\Exception $e) {
                    // Continue with other tenants
                }
            }
        }
        
        return $warmedUp;
    }
    
    // ======= LEGACY METHODS FOR BACKWARD COMPATIBILITY =======
    
    /**
     * Cache tenant data for faster access (legacy method)
     */
    public static function cacheTenant(Tenant $tenant): void
    {
        $key = $tenant->getTenantKey();
        static::$tenantCache[$key] = [
            'tenant' => $tenant,
            'database_name' => $tenant->getDatabaseName(),
            'cached_at' => time(),
        ];
    }
    
    /**
     * Get cached tenant data (legacy method)
     */
    public static function getCachedTenant(string $tenantKey): ?array
    {
        return static::$tenantCache[$tenantKey] ?? null;
    }
    
    /**
     * Cache database connection info (legacy method)
     */
    public static function cacheConnection(string $tenantKey, array $connectionConfig): void
    {
        static::$connectionCache[$tenantKey] = [
            'config' => $connectionConfig,
            'cached_at' => time(),
        ];
    }
    
    /**
     * Get cached connection config (legacy method)
     */
    public static function getCachedConnection(string $tenantKey): ?array
    {
        $cached = static::$connectionCache[$tenantKey] ?? null;
        
        // Cache for 5 minutes
        if ($cached && (time() - $cached['cached_at']) < 300) {
            return $cached['config'];
        }
        
        return null;
    }
    
    /**
     * Clear all caches (enhanced)
     */
    public static function clearAll(): void
    {
        // Clear legacy caches
        static::$tenantCache = [];
        static::$connectionCache = [];
        
        // Clear new multilayer caches
        static::$memoryCache = [];
        
        // Clear Laravel cache pattern
        if (method_exists(Cache::store(), 'flush')) {
            Cache::flush();
        }
        
        // Clear Redis tenant-specific keys
        try {
            $redis = Redis::connection();
            $keys = $redis->keys('af_tenancy:domain:*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            // Redis unavailable
        }
    }
    
    /**
     * Get cache statistics (enhanced)
     */
    public static function getStats(): array
    {
        $stats = [
            // Legacy stats for backward compatibility
            'tenant_cache_size' => count(static::$tenantCache),
            'connection_cache_size' => count(static::$connectionCache),
            'cached_tenants' => array_keys(static::$tenantCache),
            
            // New multilayer cache stats
            'memory_cache_size' => count(static::$memoryCache),
            'memory_cache_keys' => array_keys(static::$memoryCache),
        ];
        
        // Redis stats
        try {
            $redis = Redis::connection();
            $keys = $redis->keys('af_tenancy:domain:*');
            $stats['redis_cache_size'] = count($keys);
            $stats['redis_cache_keys'] = $keys;
        } catch (\Exception $e) {
            $stats['redis_cache_size'] = 0;
            $stats['redis_cache_error'] = $e->getMessage();
        }
        
        return $stats;
    }
}
