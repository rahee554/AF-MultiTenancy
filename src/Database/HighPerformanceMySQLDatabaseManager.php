<?php

namespace ArtflowStudio\Tenancy\Database;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use ArtflowStudio\Tenancy\Database\DynamicDatabaseConfigManager;

/**
 * High-performance MySQL Database Manager
 * Optimized for multi-tenant applications with heavy load
 */
class HighPerformanceMySQLDatabaseManager extends MySQLDatabaseManager
{
    /** @var array Cache of database connections */
    protected static $connectionCache = [];
    
    /** @var array Cache of database existence checks */
    protected static $databaseExistenceCache = [];
    
    /** @var int Cache TTL in seconds */
    protected $cacheTtl = 300; // 5 minutes

    /**
     * Create database with optimized settings
     */
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $database = $tenant->database()->getName();
        
        // Check cache first
        if (isset(static::$databaseExistenceCache[$database])) {
            return static::$databaseExistenceCache[$database];
        }

        try {
            // Use parent method for compatibility but with optimizations
            $result = parent::createDatabase($tenant);
            
            if ($result) {
                // Apply safe tenant-specific optimizations after creation
                DynamicDatabaseConfigManager::applyTenantOptimizations($database);
                
                // Cache the result
                static::$databaseExistenceCache[$database] = true;
            }
            
            return $result;
        } catch (\Exception $e) {
            static::$databaseExistenceCache[$database] = false;
            throw $e;
        }
    }

    /**
     * Check if database exists with caching
     */
    public function databaseExists(string $name): bool
    {
        // Check cache first
        if (isset(static::$databaseExistenceCache[$name])) {
            return static::$databaseExistenceCache[$name];
        }

        try {
            $exists = parent::databaseExists($name);
            
            // Cache the result
            static::$databaseExistenceCache[$name] = $exists;
            
            return $exists;
        } catch (\Exception $e) {
            // Cache negative result
            static::$databaseExistenceCache[$name] = false;
            return false;
        }
    }

    /**
     * Get optimized database connection config
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $config = parent::makeConnectionConfig($baseConfig, $databaseName);
        
        // ENSURE tenant connection is properly configured
        // The parent method should handle all the PDO options correctly
        // We just need to ensure the database name is set correctly
        $config['database'] = $databaseName;
        
        // Add pool metadata for monitoring/documentation
        $config['pool'] = [
            'min_connections' => 1,
            'max_connections' => 10,
            'idle_timeout' => 30,
            'max_lifetime' => 3600,
        ];
        
        return $config;
    }

    /**
     * Clear connection cache (useful for testing)
     */
    public static function clearCache(): void
    {
        static::$connectionCache = [];
        static::$databaseExistenceCache = [];
    }

    /**
     * Get cache statistics
     */
    public static function getCacheStats(): array
    {
        return [
            'connection_cache_size' => count(static::$connectionCache),
            'database_existence_cache_size' => count(static::$databaseExistenceCache),
            'cached_databases' => array_keys(static::$databaseExistenceCache),
        ];
    }
}
