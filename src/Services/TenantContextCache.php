<?php

namespace ArtflowStudio\Tenancy\Services;

use Illuminate\Support\Facades\Cache;
use ArtflowStudio\Tenancy\Models\Tenant;

/**
 * Tenant Context Cache Service
 * Caches tenant contexts to reduce database switching overhead
 */
class TenantContextCache
{
    protected static $tenantCache = [];
    protected static $connectionCache = [];
    
    /**
     * Cache tenant data for faster access
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
     * Get cached tenant data
     */
    public static function getCachedTenant(string $tenantKey): ?array
    {
        return static::$tenantCache[$tenantKey] ?? null;
    }
    
    /**
     * Cache database connection info
     */
    public static function cacheConnection(string $tenantKey, array $connectionConfig): void
    {
        static::$connectionCache[$tenantKey] = [
            'config' => $connectionConfig,
            'cached_at' => time(),
        ];
    }
    
    /**
     * Get cached connection config
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
     * Clear all caches
     */
    public static function clearAll(): void
    {
        static::$tenantCache = [];
        static::$connectionCache = [];
    }
    
    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        return [
            'tenant_cache_size' => count(static::$tenantCache),
            'connection_cache_size' => count(static::$connectionCache),
            'cached_tenants' => array_keys(static::$tenantCache),
        ];
    }
}
