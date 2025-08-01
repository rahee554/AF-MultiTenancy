<?php

namespace ArtflowStudio\Tenancy\Database;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

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
            // Use raw SQL for better performance
            $connection = $this->database();
            $connection->unprepared("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Optimize the database for performance
            $connection->unprepared("USE `{$database}`");
            $connection->unprepared("SET SESSION sql_mode='TRADITIONAL'");
            $connection->unprepared("SET SESSION innodb_flush_log_at_trx_commit=2");
            $connection->unprepared("SET SESSION innodb_buffer_pool_size=268435456"); // 256MB
            
            // Cache the result
            static::$databaseExistenceCache[$database] = true;
            
            return true;
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
        
        // Add performance optimizations
        $config['options'] = array_merge($config['options'] ?? [], [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='TRADITIONAL', innodb_flush_log_at_trx_commit=2",
        ]);
        
        // Add connection pooling simulation
        $config['pool'] = [
            'min_connections' => 1,
            'max_connections' => 10,
            'idle_timeout' => 30,
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
