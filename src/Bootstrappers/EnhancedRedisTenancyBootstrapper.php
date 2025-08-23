<?php

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper as BaseRedisTenancyBootstrapper;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Redis Tenancy Bootstrapper
 * 
 * Extends stancl/tenancy's Redis bootstrapper with:
 * - Multi-database support (different Redis DB per tenant)
 * - Custom prefix patterns
 * - Better error handling and logging
 * - Performance optimizations
 */
class EnhancedRedisTenancyBootstrapper extends BaseRedisTenancyBootstrapper
{
    /**
     * Bootstrap tenancy for Redis with enhanced features
     */
    public function bootstrap(TenantWithDatabase $tenant): void
    {
        try {
            // Calculate tenant-specific Redis database
            $tenantDatabase = $this->calculateTenantRedisDatabase($tenant);
            
            // Calculate tenant-specific prefix
            $tenantPrefix = $this->calculateTenantRedisPrefix($tenant);
            
            Log::debug('Enhanced Redis: Bootstrapping tenant Redis', [
                'tenant_id' => $tenant->getTenantKey(),
                'redis_database' => $tenantDatabase,
                'redis_prefix' => $tenantPrefix
            ]);
            
            // Store original Redis configuration for restoration
            $this->storeOriginalRedisConfig();
            
            // Apply tenant-specific Redis configuration
            $this->applyTenantRedisConfig($tenantDatabase, $tenantPrefix);
            
            // Call parent bootstrap for additional Redis setup
            parent::bootstrap($tenant);
            
            Log::info('Enhanced Redis: Successfully bootstrapped tenant Redis', [
                'tenant_id' => $tenant->getTenantKey(),
                'redis_database' => $tenantDatabase
            ]);
            
        } catch (\Exception $e) {
            Log::error('Enhanced Redis: Failed to bootstrap tenant Redis', [
                'tenant_id' => $tenant->getTenantKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fall back to default Redis configuration
            $this->restoreOriginalRedisConfig();
            throw $e;
        }
    }

    /**
     * Revert Redis configuration to central state
     */
    public function revert(): void
    {
        try {
            Log::debug('Enhanced Redis: Reverting to central Redis configuration');
            
            // Call parent revert first
            parent::revert();
            
            // Restore original Redis configuration
            $this->restoreOriginalRedisConfig();
            
            Log::info('Enhanced Redis: Successfully reverted to central Redis');
            
        } catch (\Exception $e) {
            Log::error('Enhanced Redis: Failed to revert Redis configuration', [
                'error' => $e->getMessage()
            ]);
            
            // Force restore original configuration
            $this->restoreOriginalRedisConfig();
        }
    }

    /**
     * Calculate tenant-specific Redis database number
     */
    protected function calculateTenantRedisDatabase(TenantWithDatabase $tenant): int
    {
        $baseDatabase = config('artflow-tenancy.redis.database_offset', 10);
        $tenantId = $tenant->getTenantKey();
        
        // Use CRC32 hash for consistent database assignment
        $hash = crc32($tenantId);
        $databaseOffset = abs($hash) % config('artflow-tenancy.redis.max_databases', 100);
        
        return $baseDatabase + $databaseOffset;
    }

    /**
     * Calculate tenant-specific Redis prefix
     */
    protected function calculateTenantRedisPrefix(TenantWithDatabase $tenant): string
    {
        $pattern = config('artflow-tenancy.redis.prefix_pattern', 'tenant_{tenant_id}_');
        $tenantId = $tenant->getTenantKey();
        
        return str_replace('{tenant_id}', $tenantId, $pattern);
    }

    /**
     * Store original Redis configuration for restoration
     */
    protected function storeOriginalRedisConfig(): void
    {
        if (!app()->has('redis.original.config')) {
            app()->instance('redis.original.config', [
                'default_database' => config('database.redis.default.database', 0),
                'cache_database' => config('database.redis.cache.database', 0),
                'session_database' => config('database.redis.session.database', 1),
                'cache_prefix' => config('cache.prefix', ''),
            ]);
        }
    }

    /**
     * Apply tenant-specific Redis configuration
     */
    protected function applyTenantRedisConfig(int $database, string $prefix): void
    {
        // Update Redis database configuration
        config([
            'database.redis.default.database' => $database,
            'database.redis.cache.database' => $database,
            'database.redis.session.database' => $database + 1, // Use next DB for sessions
        ]);
        
        // Update cache prefix
        config(['cache.prefix' => $prefix]);
        
        // Clear Redis connection to force reconnection with new config
        $this->reconnectRedisConnections();
    }

    /**
     * Restore original Redis configuration
     */
    protected function restoreOriginalRedisConfig(): void
    {
        if (app()->has('redis.original.config')) {
            $originalConfig = app('redis.original.config');
            
            config([
                'database.redis.default.database' => $originalConfig['default_database'],
                'database.redis.cache.database' => $originalConfig['cache_database'],
                'database.redis.session.database' => $originalConfig['session_database'],
                'cache.prefix' => $originalConfig['cache_prefix'],
            ]);
            
            // Clear Redis connection to force reconnection with original config
            $this->reconnectRedisConnections();
        }
    }

    /**
     * Reconnect Redis connections to apply new configuration
     */
    protected function reconnectRedisConnections(): void
    {
        try {
            // Clear existing Redis connections so they reconnect with new config
            if (app()->bound('redis')) {
                $redis = app('redis');
                if (method_exists($redis, 'purge')) {
                    $redis->purge();
                }
            }
            
            // Clear cache connections
            if (app()->bound('cache')) {
                $cache = app('cache');
                if (method_exists($cache, 'forgetDriver')) {
                    $cache->forgetDriver('redis');
                }
            }
        } catch (\Exception $e) {
            Log::warning('Enhanced Redis: Failed to purge Redis connections', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
