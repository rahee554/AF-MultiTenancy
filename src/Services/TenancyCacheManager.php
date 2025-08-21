<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Cache\Repository;
use Exception;

class TenancyCacheManager
{
    /**
     * Get cache store with automatic Redis fallback
     *
     * @param string|null $store
     * @return Repository
     */
    public static function store(string $store = null): Repository
    {
        // If no store specified, determine best available
        if (!$store) {
            $store = static::getBestCacheStore();
        }

        try {
            return Cache::store($store);
        } catch (Exception $e) {
            // If Redis fails, fallback to database
            if ($store === 'redis') {
                return Cache::store('database');
            }
            
            throw $e;
        }
    }

    /**
     * Determine the best cache store to use
     *
     * @return string
     */
    public static function getBestCacheStore(): string
    {
        // Check if Redis is available
        if (RedisHelper::isAvailable()) {
            return 'redis';
        }

        // Fallback to configured fallback driver or database
        return config('cache.fallback_driver', 'database');
    }

    /**
     * Configure cache driver automatically based on Redis availability
     *
     * @return void
     */
    public static function configureCacheDriver(): void
    {
        $driver = static::getBestCacheStore();
        
        // Update runtime configuration
        Config::set('cache.default', $driver);
        
        // Also set tenancy-specific cache configurations
        if (config('artflow-tenancy.cache.driver') !== $driver) {
            Config::set('artflow-tenancy.cache.driver', $driver);
        }
    }

    /**
     * Get tenant-scoped cache key
     *
     * @param string $key
     * @param string|null $tenantId
     * @return string
     */
    public static function getTenantKey(string $key, string $tenantId = null): string
    {
        if (!$tenantId && function_exists('tenant')) {
            $tenant = tenant();
            $tenantId = $tenant ? $tenant->id : 'central';
        }

        $prefix = config('artflow-tenancy.cache.prefix', 'tenant_');
        return $tenantId ? "{$prefix}{$tenantId}:{$key}" : $key;
    }

    /**
     * Put a value in tenant-scoped cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param string|null $tenantId
     * @return bool
     */
    public static function put(string $key, $value, int $ttl = null, string $tenantId = null): bool
    {
        $ttl = $ttl ?: config('artflow-tenancy.cache.default_ttl', 3600);
        $tenantKey = static::getTenantKey($key, $tenantId);
        
        return static::store()->put($tenantKey, $value, $ttl);
    }

    /**
     * Get a value from tenant-scoped cache
     *
     * @param string $key
     * @param mixed $default
     * @param string|null $tenantId
     * @return mixed
     */
    public static function get(string $key, $default = null, string $tenantId = null)
    {
        $tenantKey = static::getTenantKey($key, $tenantId);
        
        return static::store()->get($tenantKey, $default);
    }

    /**
     * Remove a value from tenant-scoped cache
     *
     * @param string $key
     * @param string|null $tenantId
     * @return bool
     */
    public static function forget(string $key, string $tenantId = null): bool
    {
        $tenantKey = static::getTenantKey($key, $tenantId);
        
        return static::store()->forget($tenantKey);
    }

    /**
     * Remember a value in tenant-scoped cache
     *
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @param string|null $tenantId
     * @return mixed
     */
    public static function remember(string $key, callable $callback, int $ttl = null, string $tenantId = null)
    {
        $ttl = $ttl ?: config('artflow-tenancy.cache.default_ttl', 3600);
        $tenantKey = static::getTenantKey($key, $tenantId);
        
        return static::store()->remember($tenantKey, $ttl, $callback);
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function getStats(): array
    {
        $currentStore = static::getBestCacheStore();
        $stats = [
            'current_store' => $currentStore,
            'redis_available' => RedisHelper::isAvailable(),
            'fallback_enabled' => config('cache.fallback_driver') !== null,
        ];

        if ($currentStore === 'redis' && RedisHelper::isAvailable()) {
            $redisStats = RedisHelper::getStats();
            $stats = array_merge($stats, $redisStats);
        }

        return $stats;
    }

    /**
     * Flush tenant-specific cache
     *
     * @param string|null $tenantId
     * @return bool
     */
    public static function flushTenant(string $tenantId = null): bool
    {
        if (!$tenantId && function_exists('tenant')) {
            $tenant = tenant();
            $tenantId = $tenant ? $tenant->id : null;
        }

        if (!$tenantId) {
            return false;
        }

        $prefix = config('artflow-tenancy.cache.prefix', 'tenant_');
        $pattern = "{$prefix}{$tenantId}:*";

        try {
            $store = static::store();
            
            // For Redis, we can use pattern-based deletion
            if (static::getBestCacheStore() === 'redis' && RedisHelper::isAvailable()) {
                return RedisHelper::flushPattern($pattern);
            }

            // For other stores, we can't efficiently delete by pattern
            // So we just clear the entire cache (not ideal but safe)
            return $store->flush();

        } catch (Exception $e) {
            return false;
        }
    }
}
