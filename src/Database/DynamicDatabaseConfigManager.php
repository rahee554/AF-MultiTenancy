<?php

namespace ArtflowStudio\Tenancy\Database;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic Database Configuration Manager
 * 
 * Automatically applies optimal database settings without requiring manual
 * database.php modifications. Handles different MySQL versions and configurations.
 */
class DynamicDatabaseConfigManager
{
    /**
     * Apply optimal database configuration dynamically
     */
    public static function applyOptimalConfiguration(): void
    {
        $connection = Config::get('database.default', 'mysql');
        
        if ($connection === 'mysql') {
            static::configureMySQLConnection();
        }
    }
    
    /**
     * Configure MySQL connection with optimal settings
     */
    protected static function configureMySQLConnection(): void
    {
        $connectionName = Config::get('database.default');
        $currentConfig = Config::get("database.connections.{$connectionName}", []);
        
        // Get optimal PDO options without conflicting with existing ones
        $optimalOptions = static::getOptimalMySQLOptions();
        
        // Merge with existing options, prioritizing existing ones
        $existingOptions = $currentConfig['options'] ?? [];
        $mergedOptions = array_merge($optimalOptions, $existingOptions);
        
        // Update the configuration
        Config::set("database.connections.{$connectionName}.options", $mergedOptions);
        
        // Clear any existing connections to apply new settings
        DB::purge($connectionName);
    }
    
    /**
     * Get optimal MySQL PDO options
     */
    protected static function getOptimalMySQLOptions(): array
    {
        return array_filter([
            // Basic SSL configuration (skip if null to avoid conflicts)
            
            // Performance optimizations - ENSURE PROPER TYPES - DISABLED FOR NOW
            // \PDO::ATTR_PERSISTENT => false, // Disabled to prevent conflicts
            // \PDO::ATTR_EMULATE_PREPARES => false, // Disabled to prevent conflicts
            // \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Disabled to prevent conflicts
            
            // Security settings - DISABLED FOR NOW
            // \PDO::MYSQL_ATTR_LOCAL_INFILE => false, // Disabled to prevent conflicts
            
            // Connection settings - ENSURE INTEGER TYPES - DISABLED FOR NOW
            // \PDO::ATTR_TIMEOUT => 5, // Disabled to prevent conflicts
            // \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, // Disabled to prevent conflicts
            // \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // Disabled to prevent conflicts
            
            // Session-level MySQL settings (safe to use)
            \PDO::MYSQL_ATTR_INIT_COMMAND => static::getMySQLInitCommand(), // String
        ]);
    }
    
    /**
     * Get MySQL initialization command with session-level variables only
     */
    protected static function getMySQLInitCommand(): string
    {
        // Only use session-level variables, not global ones
        $commands = [
            "SET SESSION sql_mode='TRADITIONAL'",
            "SET SESSION autocommit=1",
            "SET SESSION time_zone='+00:00'",
        ];
        
        return implode(', ', $commands);
    }
    
    /**
     * Apply tenant-specific database optimizations
     */
    public static function applyTenantOptimizations(string $databaseName): void
    {
        try {
            $connection = DB::connection();
            
            // Apply tenant-specific optimizations that don't require GLOBAL privileges
            $optimizations = [
                "USE `{$databaseName}`",
                "SET SESSION sql_mode='TRADITIONAL'",
                "SET SESSION autocommit=1",
                "SET SESSION innodb_lock_wait_timeout=10", // Session-level timeout
            ];
            
            foreach ($optimizations as $sql) {
                try {
                    $connection->unprepared($sql);
                } catch (\Exception $e) {
                    // Log but don't fail on optimization errors
                    logger()->warning("Database optimization failed: {$sql}", [
                        'error' => $e->getMessage(),
                        'database' => $databaseName,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Don't fail tenant creation if optimizations fail
            logger()->warning("Tenant database optimizations failed", [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check if current MySQL user has required privileges
     */
    public static function checkMySQLPrivileges(): array
    {
        try {
            $privileges = DB::select("SHOW GRANTS FOR CURRENT_USER()");
            $hasGlobal = false;
            $hasSuper = false;
            
            foreach ($privileges as $privilege) {
                $grant = $privilege->{'Grants for ' . DB::select("SELECT CURRENT_USER() as user")[0]->user} ?? '';
                
                if (strpos($grant, 'ALL PRIVILEGES') !== false || strpos($grant, 'SUPER') !== false) {
                    $hasSuper = true;
                }
                
                if (strpos($grant, '*.*') !== false) {
                    $hasGlobal = true;
                }
            }
            
            return [
                'has_global_privileges' => $hasGlobal,
                'has_super_privileges' => $hasSuper,
                'can_set_global_variables' => $hasSuper,
                'privileges' => $privileges,
            ];
        } catch (\Exception $e) {
            return [
                'has_global_privileges' => false,
                'has_super_privileges' => false,
                'can_set_global_variables' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get safe MySQL configuration for current user privileges
     */
    public static function getSafeMySQLConfig(): array
    {
        $privileges = static::checkMySQLPrivileges();
        
        $config = [
            'options' => static::getOptimalMySQLOptions(),
        ];
        
        if (!$privileges['can_set_global_variables']) {
            // Remove any global variable settings if user doesn't have SUPER privileges
            $config['options'][\PDO::MYSQL_ATTR_INIT_COMMAND] = static::getMySQLInitCommand();
        }
        
        return $config;
    }
    
    /**
     * Initialize optimal database configuration on application boot
     */
    public static function initialize(): void
    {
        try {
            // Apply configuration without modifying files
            static::applyOptimalConfiguration();
            
            logger()->info('Dynamic database configuration applied successfully');
        } catch (\Exception $e) {
            logger()->warning('Failed to apply dynamic database configuration', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
