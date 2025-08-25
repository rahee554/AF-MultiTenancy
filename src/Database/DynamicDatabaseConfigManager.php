<?php

namespace ArtflowStudio\Tenancy\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DynamicDatabaseConfigManager
{
    /**
     * Check MySQL user privileges
     */
    public static function checkMySQLPrivileges(): array
    {
        try {
            // Get current user info
            $currentUser = DB::select("SELECT USER() as current_user")[0]->current_user;
            
            // Check grants for current user
            $grants = DB::select("SHOW GRANTS");
            
            $privileges = [
                'has_global_privileges' => false,
                'has_super_privileges' => false,
                'can_set_global_variables' => false,
                'can_create_database' => false,
                'can_drop_database' => false,
                'current_user' => $currentUser,
                'grants' => []
            ];
            
            foreach ($grants as $grant) {
                $grantText = array_values((array)$grant)[0];
                $privileges['grants'][] = $grantText;
                
                // Check for global privileges
                if (stripos($grantText, 'ON *.*') !== false) {
                    $privileges['has_global_privileges'] = true;
                }
                
                // Check for SUPER privileges
                if (stripos($grantText, 'SUPER') !== false) {
                    $privileges['has_super_privileges'] = true;
                    $privileges['can_set_global_variables'] = true;
                }
                
                // Check for ALL PRIVILEGES
                if (stripos($grantText, 'ALL PRIVILEGES') !== false) {
                    $privileges['has_global_privileges'] = true;
                    $privileges['can_create_database'] = true;
                    $privileges['can_drop_database'] = true;
                    if (stripos($grantText, 'ON *.*') !== false) {
                        $privileges['can_set_global_variables'] = true;
                    }
                }
                
                // Check for CREATE privilege
                if (stripos($grantText, 'CREATE') !== false) {
                    $privileges['can_create_database'] = true;
                }
                
                // Check for DROP privilege
                if (stripos($grantText, 'DROP') !== false) {
                    $privileges['can_drop_database'] = true;
                }
            }
            
            return $privileges;
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Apply tenant optimizations
     */
    public static function applyTenantOptimizations(string $database): array
    {
        try {
            $results = [];
            
            // Try to set session-level optimizations (these don't require SUPER)
            $sessionOptimizations = [
                'SET SESSION innodb_flush_log_at_trx_commit = 2',
                'SET SESSION sync_binlog = 0',
                'SET SESSION foreign_key_checks = 1',
                'SET SESSION unique_checks = 1',
                'SET SESSION autocommit = 1'
            ];
            
            foreach ($sessionOptimizations as $optimization) {
                try {
                    DB::statement($optimization);
                    $results[] = ['setting' => $optimization, 'status' => 'success'];
                } catch (\Exception $e) {
                    $results[] = ['setting' => $optimization, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }
            
            return ['status' => 'completed', 'optimizations' => $results];
            
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Initialize dynamic configuration
     */
    public static function initialize(): void
    {
        // Set default connection optimizations for tenant operations
        $defaultConnection = config('database.default', 'mysql');
        $config = config("database.connections.{$defaultConnection}");
        
        if (!$config) {
            return;
        }
        
        // Apply tenant-specific connection options
        $config['options'] = array_merge($config['options'] ?? [], [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
        
        Config::set("database.connections.{$defaultConnection}", $config);
    }

    /**
     * Get safe MySQL configuration
     */
    public static function getSafeMySQLConfig(): array
    {
        return [
            'connection_options' => [
                'mysql' => [
                    'options' => [
                        \PDO::ATTR_EMULATE_PREPARES => false,
                        \PDO::ATTR_STRINGIFY_FETCHES => false,
                        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    ],
                    'strict' => true,
                    'engine' => 'InnoDB',
                ]
            ],
            'session_variables' => [
                'innodb_flush_log_at_trx_commit' => 2,
                'sync_binlog' => 0,
                'foreign_key_checks' => 1,
                'unique_checks' => 1,
                'autocommit' => 1
            ]
        ];
    }

    /**
     * Test tenant database creation
     */
    public static function testTenantDatabaseCreation(string $testDbName): array
    {
        try {
            // Try to create test database
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$testDbName}`");
            
            // Test connection to the new database
            $defaultConnection = config('database.default', 'mysql');
            $testConnection = config("database.connections.{$defaultConnection}");
            
            if (!$testConnection) {
                return ['status' => 'error', 'message' => 'Default database connection not configured'];
            }
            
            $testConnection['database'] = $testDbName;
            
            Config::set('database.connections.test_tenant', $testConnection);
            
            // Test the connection
            $testDb = DB::connection('test_tenant');
            $testDb->getPdo();
            
            // Clean up
            DB::statement("DROP DATABASE IF EXISTS `{$testDbName}`");
            
            return ['status' => 'success', 'message' => 'Test database created and connected successfully'];
            
        } catch (\Exception $e) {
            // Clean up on error
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$testDbName}`");
            } catch (\Exception $cleanupError) {
                // Ignore cleanup errors
            }
            
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
