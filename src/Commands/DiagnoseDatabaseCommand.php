<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Database\DynamicDatabaseConfigManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DiagnoseDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:diagnose
                            {--fix : Attempt to fix common configuration issues}
                            {--test-connection : Test database connection with current settings}';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose and fix common database configuration issues in multi-tenant setup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Diagnosing AF-MultiTenancy Database Configuration...');
        
        // Test basic connection
        $this->testBasicConnection();
        
        // Check MySQL privileges
        $this->checkMySQLPrivileges();
        
        // Check current configuration
        $this->checkCurrentConfiguration();
        
        // Test tenant creation if requested
        if ($this->option('test-connection')) {
            $this->testTenantConnection();
        }
        
        // Apply fixes if requested
        if ($this->option('fix')) {
            $this->applyFixes();
        }
        
        $this->info('✅ Diagnosis complete. Check output above for any issues.');
        
        return 0;
    }
    
    /**
     * Test basic database connection
     */
    protected function testBasicConnection(): void
    {
        $this->line('');
        $this->info('📊 Testing Basic Database Connection...');
        
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            $this->info('✅ Database connection successful');
            $this->line("   Database: " . $connection->getDatabaseName());
            $this->line("   Driver: " . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            $this->line("   Version: " . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION));
            
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed');
            $this->error("   Error: " . $e->getMessage());
        }
    }
    
    /**
     * Check MySQL user privileges
     */
    protected function checkMySQLPrivileges(): void
    {
        $this->line('');
        $this->info('🔐 Checking MySQL User Privileges...');
        
        try {
            $privileges = DynamicDatabaseConfigManager::checkMySQLPrivileges();
            
            if (isset($privileges['error'])) {
                $this->warn('⚠️  Could not check privileges: ' . $privileges['error']);
                return;
            }
            
            $this->line('Global Privileges: ' . ($privileges['has_global_privileges'] ? '✅ Yes' : '❌ No'));
            $this->line('SUPER Privileges: ' . ($privileges['has_super_privileges'] ? '✅ Yes' : '❌ No'));
            $this->line('Can Set Global Variables: ' . ($privileges['can_set_global_variables'] ? '✅ Yes' : '❌ No'));
            
            if (!$privileges['can_set_global_variables']) {
                $this->warn('⚠️  User cannot set global MySQL variables. Some optimizations will be limited.');
                $this->line('   This is normal and safe - session-level optimizations will be used instead.');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check privileges: ' . $e->getMessage());
        }
    }
    
    /**
     * Check current database configuration
     */
    protected function checkCurrentConfiguration(): void
    {
        $this->line('');
        $this->info('⚙️  Checking Current Database Configuration...');
        
        $connection = Config::get('database.default');
        $config = Config::get("database.connections.{$connection}");
        
        $this->line("Default Connection: {$connection}");
        $this->line("Driver: " . ($config['driver'] ?? 'Not set'));
        $this->line("Host: " . ($config['host'] ?? 'Not set'));
        $this->line("Database: " . ($config['database'] ?? 'Not set'));
        
        // Check PDO options
        $options = $config['options'] ?? [];
        $this->line("PDO Options: " . count($options) . " configured");
        
        $criticalOptions = [
            \PDO::ATTR_ERRMODE => 'Error Mode',
            \PDO::ATTR_PERSISTENT => 'Persistent Connections',
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'Init Command',
        ];
        
        foreach ($criticalOptions as $option => $name) {
            if (isset($options[$option])) {
                $this->line("   ✅ {$name}: Configured");
            } else {
                $this->line("   ⚠️  {$name}: Not configured");
            }
        }
    }
    
    /**
     * Test tenant database operations
     */
    protected function testTenantConnection(): void
    {
        $this->line('');
        $this->info('🏢 Testing Tenant Database Operations...');
        
        try {
            // Create a test database name
            $testDb = 'af_test_' . time();
            
            $this->line("Creating test database: {$testDb}");
            DB::unprepared("CREATE DATABASE IF NOT EXISTS `{$testDb}`");
            $this->info('✅ Test database created');
            
            // Test optimizations
            $this->line('Testing database optimizations...');
            DynamicDatabaseConfigManager::applyTenantOptimizations($testDb);
            $this->info('✅ Optimizations applied');
            
            // Clean up
            DB::unprepared("DROP DATABASE IF EXISTS `{$testDb}`");
            $this->info('✅ Test database cleaned up');
            
        } catch (\Exception $e) {
            $this->error('❌ Tenant database test failed');
            $this->error("   Error: " . $e->getMessage());
            
            // Provide specific help for common errors
            if (strpos($e->getMessage(), 'GLOBAL variable') !== false) {
                $this->line('');
                $this->warn('💡 Solution: This error occurs when trying to set global MySQL variables.');
                $this->warn('   The system will automatically use session-level variables instead.');
                $this->warn('   This is safe and normal for most hosting environments.');
            }
        }
    }
    
    /**
     * Apply automatic fixes
     */
    protected function applyFixes(): void
    {
        $this->line('');
        $this->info('🔧 Applying Automatic Fixes...');
        
        try {
            // Initialize dynamic configuration
            DynamicDatabaseConfigManager::initialize();
            $this->info('✅ Dynamic database configuration applied');
            
            // Get safe configuration for current user
            $safeConfig = DynamicDatabaseConfigManager::getSafeMySQLConfig();
            $this->info('✅ Safe MySQL configuration generated');
            
            $this->line('   Configuration optimized for your MySQL user privileges');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to apply fixes: ' . $e->getMessage());
        }
    }
}
