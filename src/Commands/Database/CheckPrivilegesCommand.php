<?php

namespace ArtflowStudio\Tenancy\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class CheckPrivilegesCommand extends Command
{
    protected $signature = 'tenant:check-privileges 
                            {--connection= : Database connection to check}
                            {--user= : Specific database user to check}
                            {--test-root : Test root user credentials from environment}
                            {--interactive : Run in interactive mode with guidance}';

    protected $description = 'Check database user privileges for tenant operations';

    public function handle()
    {
        $this->info('🔐 Database Privilege Checker');
        $this->info('============================');
        $this->newLine();

        // Handle interactive mode
        if ($this->option('interactive')) {
            return $this->runInteractiveMode();
        }

        $connection = $this->option('connection') ?: config('database.default', 'mysql');
        $specificUser = $this->option('user');

        $this->info("Checking privileges for connection: {$connection}");
        $this->newLine();

        // Check for root user environment variables first
        $this->checkRootUserEnvVars();
        $this->newLine();

        // Test root user if requested
        if ($this->option('test-root')) {
            $this->testRootUserConnection();
            $this->newLine();
        }

        try {
            // Get current connection info
            $config = config("database.connections.{$connection}");
            if (!$config) {
                $this->error("Connection '{$connection}' not found in configuration.");
                return 1;
            }

            $this->displayConnectionInfo($config);
            $this->newLine();

            // Check current user privileges
            $currentUser = $this->getCurrentDatabaseUser($connection);
            $this->info("Current database user: {$currentUser}");
            $this->newLine();

            // Check privileges for current user
            if ($specificUser) {
                $this->checkUserPrivileges($specificUser, $connection);
            } else {
                $this->checkUserPrivileges($currentUser, $connection);
            }

            // List all privileged users
            $this->newLine();
            $this->listPrivilegedUsers($connection);

            return 0;

        } catch (\Exception $e) {
            $this->error("Error checking privileges: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Run interactive mode with guided steps
     */
    private function runInteractiveMode(): int
    {
        $this->info('🎯 Interactive Database Privilege Setup & Checking');
        $this->info('This guide will help you check and configure database privileges for tenancy');
        $this->newLine();

        $actions = [
            'check-current' => 'Check current user privileges',
            'check-root-env' => 'Check root user environment variables',
            'test-root' => 'Test root user connection from environment',
            'setup-root-env' => 'Setup root user environment variables',
            'check-pdo-config' => 'Check and configure PDO options',
            'add-tenant-template' => 'Add tenant_template database connection',
            'list-users' => 'List all privileged database users',
        ];

        while (true) {
            $this->info('Available actions:');
            foreach ($actions as $key => $description) {
                $this->info("  <fg=green>{$key}</fg=green> - {$description}");
            }
            $this->info("  <fg=red>exit</fg=red> - Exit interactive mode");
            $this->newLine();

            $action = $this->choice('What would you like to do?', array_merge(array_keys($actions), ['exit']));

            if ($action === 'exit') {
                break;
            }

            $this->newLine();
            $this->executeInteractiveAction($action);
            $this->newLine();

            if (!$this->confirm('Would you like to perform another action?', true)) {
                break;
            }
            $this->newLine();
        }

        return 0;
    }

    /**
     * Execute interactive action
     */
    private function executeInteractiveAction(string $action): void
    {
        switch ($action) {
            case 'check-current':
                $this->checkCurrentUserPrivileges();
                break;
            case 'check-root-env':
                $this->checkRootUserEnvVars();
                break;
            case 'test-root':
                $this->testRootUserConnection();
                break;
            case 'setup-root-env':
                $this->setupRootEnvironmentVars();
                break;
            case 'check-pdo-config':
                $this->checkAndConfigurePDO();
                break;
            case 'add-tenant-template':
                $this->addTenantTemplateConnection();
                break;
            case 'list-users':
                $this->listPrivilegedUsers();
                break;
        }
    }

    /**
     * Check current user privileges
     */
    private function checkCurrentUserPrivileges(): void
    {
        $connection = config('database.default', 'mysql');
        $currentUser = $this->getCurrentDatabaseUser($connection);
        $this->info("🔍 Checking privileges for current user: {$currentUser}");
        $this->newLine();
        $this->checkUserPrivileges($currentUser, $connection);
    }

    /**
     * Setup root environment variables interactively
     */
    private function setupRootEnvironmentVars(): void
    {
        $this->info('⚙️  Setting up Root User Environment Variables');
        $this->newLine();

        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            $this->error('.env file not found!');
            return;
        }

        $currentRootUser = env('DB_ROOT_USERNAME');
        $currentRootPass = env('DB_ROOT_PASSWORD');

        $this->info('Current settings:');
        $this->line("  DB_ROOT_USERNAME: " . ($currentRootUser ?: 'Not set'));
        $this->line("  DB_ROOT_PASSWORD: " . ($currentRootPass ? '****** (Set)' : 'Not set'));
        $this->newLine();

        if ($this->confirm('Would you like to update these settings?')) {
            $newRootUser = $this->ask('Enter root username', $currentRootUser ?: 'root');
            $newRootPass = $this->secret('Enter root password (input will be hidden)');

            if ($newRootUser && $newRootPass) {
                $this->updateEnvFile($envPath, 'DB_ROOT_USERNAME', $newRootUser);
                $this->updateEnvFile($envPath, 'DB_ROOT_PASSWORD', $newRootPass);
                
                $this->info('✅ Root user credentials updated in .env file');
                $this->warn('⚠️  Remember to restart your application to load new environment variables');
                
                if ($this->confirm('Would you like to test the new root connection now?')) {
                    $this->testRootUserConnection($newRootUser, $newRootPass);
                }
            } else {
                $this->warn('Both username and password are required');
            }
        }
    }

    /**
     * Test root user connection from environment or provided credentials
     */
    private function testRootUserConnection(?string $testUser = null, ?string $testPass = null): void
    {
        $this->info('🧪 Testing Root User Connection...');
        
        $rootUser = $testUser ?: env('DB_ROOT_USERNAME');
        $rootPass = $testPass ?: env('DB_ROOT_PASSWORD');
        
        if (!$rootUser || !$rootPass) {
            $this->error('❌ Root credentials not found in environment variables');
            $this->info('💡 Use --interactive mode to set them up or provide them manually');
            return;
        }

        try {
            // Get current database config and create root connection
            $defaultConfig = config('database.connections.' . config('database.default', 'mysql'));
            $rootConfig = array_merge($defaultConfig, [
                'username' => $rootUser,
                'password' => $rootPass,
            ]);

            // Test connection
            $pdo = new \PDO(
                "mysql:host={$rootConfig['host']};port={$rootConfig['port']};dbname={$rootConfig['database']}",
                $rootUser,
                $rootPass
            );

            $this->info("✅ Root user connection successful!");
            $this->line("   User: {$rootUser}");
            $this->line("   Host: {$rootConfig['host']}");

            // Test root privileges
            $stmt = $pdo->query("SHOW GRANTS");
            $grants = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $hasCreateDb = false;
            $hasDropDb = false;
            $hasSuper = false;

            foreach ($grants as $grant) {
                if (stripos($grant, 'ALL PRIVILEGES') !== false || stripos($grant, 'CREATE') !== false) {
                    $hasCreateDb = true;
                }
                if (stripos($grant, 'ALL PRIVILEGES') !== false || stripos($grant, 'DROP') !== false) {
                    $hasDropDb = true;
                }
                if (stripos($grant, 'SUPER') !== false || stripos($grant, 'ALL PRIVILEGES') !== false) {
                    $hasSuper = true;
                }
            }

            $this->newLine();
            $this->info('🔍 Root User Privileges:');
            $this->table(['Privilege', 'Status'], [
                ['CREATE DATABASE', $hasCreateDb ? '✅ Yes' : '❌ No'],
                ['DROP DATABASE', $hasDropDb ? '✅ Yes' : '❌ No'],
                ['SUPER', $hasSuper ? '✅ Yes' : '❌ No'],
            ]);

            if ($hasCreateDb && $hasDropDb) {
                $this->info('✅ Root user has sufficient privileges for tenant operations');
            } else {
                $this->warn('⚠️  Root user may have limited privileges');
            }

        } catch (\Exception $e) {
            $this->error("❌ Root user connection failed: {$e->getMessage()}");
            $this->info('💡 Check your credentials and database server status');
        }
    }

    /**
     * Check and configure PDO options
     */
    private function checkAndConfigurePDO(): void
    {
        $this->info('⚙️  Database PDO Configuration Check');
        $this->newLine();

        $connection = config('database.default', 'mysql');
        $config = config("database.connections.{$connection}");
        
        if (!$config) {
            $this->error("Database connection '{$connection}' not found");
            return;
        }

        // Check current PDO options (handle conditional arrays)
        $currentOptions = [];
        if (isset($config['options'])) {
            if (is_array($config['options'])) {
                $currentOptions = $config['options'];
            }
        }
        
        $this->info('Current PDO Configuration:');
        $this->table(['Setting', 'Current Value', 'Recommended', 'Description'], [
            [
                'Error Mode',
                isset($currentOptions[\PDO::ATTR_ERRMODE]) ? $this->getPDOErrorModeString($currentOptions[\PDO::ATTR_ERRMODE]) : '❌ Not set',
                'PDO::ERRMODE_EXCEPTION',
                'Throw exceptions on database errors'
            ],
            [
                'Persistent Connections',
                isset($currentOptions[\PDO::ATTR_PERSISTENT]) ? ($currentOptions[\PDO::ATTR_PERSISTENT] ? '⚠️  Enabled' : '✅ Disabled') : '❌ Not set',
                'false (Disabled)',
                'NOT recommended for multi-tenant (causes connection conflicts)'
            ],
            [
                'Emulate Prepares',
                isset($currentOptions[\PDO::ATTR_EMULATE_PREPARES]) ? ($currentOptions[\PDO::ATTR_EMULATE_PREPARES] ? '❌ Enabled' : '✅ Disabled') : '❌ Not set',
                'false (Disabled)',
                'Use real prepared statements for better security'
            ],
            [
                'Default Fetch Mode',
                isset($currentOptions[\PDO::ATTR_DEFAULT_FETCH_MODE]) ? $this->getPDOFetchModeString($currentOptions[\PDO::ATTR_DEFAULT_FETCH_MODE]) : '❌ Not set',
                'PDO::FETCH_ASSOC',
                'Default fetch mode for queries'
            ],
            [
                'Connection Timeout',
                isset($currentOptions[\PDO::ATTR_TIMEOUT]) ? $currentOptions[\PDO::ATTR_TIMEOUT] . ' seconds' : '❌ Not set',
                '30 seconds',
                'Connection timeout for multi-tenant reliability'
            ],
            [
                'Autocommit',
                isset($currentOptions[\PDO::ATTR_AUTOCOMMIT]) ? ($currentOptions[\PDO::ATTR_AUTOCOMMIT] ? '✅ Enabled' : '❌ Disabled') : '❌ Not set',
                'true (Enabled)',
                'Auto-commit transactions (important for tenant isolation)'
            ]
        ]);

        $this->newLine();

        // Check if any settings need to be updated
        $needsUpdates = $this->checkIfPDONeedsUpdates($currentOptions);
        
        if ($needsUpdates) {
            $this->warn('⚠️  Some PDO settings are not optimally configured for multi-tenant applications');
            $this->newLine();

            $actions = [
                'auto-configure' => 'Automatically configure recommended PDO settings',
                'configure-interactive' => 'Configure PDO settings step-by-step',
                'manual-guide' => 'Show manual configuration instructions',
                'view-example' => 'View complete database configuration example',
                'skip' => 'Skip configuration changes',
            ];

            $this->info('Available configuration options:');
            foreach ($actions as $key => $description) {
                $this->info("  <fg=green>{$key}</fg=green> - {$description}");
            }
            $this->newLine();

            $action = $this->choice('How would you like to proceed?', array_keys($actions));

            switch ($action) {
                case 'auto-configure':
                    $this->autoConfigurePDO($connection);
                    break;
                case 'configure-interactive':
                    $this->interactiveConfigurePDO($connection, $currentOptions);
                    break;
                case 'manual-guide':
                    $this->showManualPDOConfiguration();
                    break;
                case 'view-example':
                    $this->showCompleteDBConfig();
                    break;
                case 'skip':
                    $this->info('⏭️  Skipped PDO configuration changes');
                    break;
            }
        } else {
            $this->info('✅ PDO configuration is already optimized!');
            
            if ($this->confirm('Would you like to see the complete database configuration example anyway?')) {
                $this->showCompleteDBConfig();
            }
        }
    }

    /**
     * Interactive PDO configuration with user choices
     */
    private function interactiveConfigurePDO(string $connection, array $currentOptions): void
    {
        $this->info('🎛️  Interactive PDO Configuration');
        $this->newLine();

        $newOptions = [];
        
        // Error Mode
        $errorMode = $this->choice('Error Mode (how to handle database errors)', [
            'exception' => 'PDO::ERRMODE_EXCEPTION (Recommended - throw exceptions)',
            'warning' => 'PDO::ERRMODE_WARNING (Show warnings)',
            'silent' => 'PDO::ERRMODE_SILENT (Silent mode)',
            'current' => 'Keep current setting'
        ], 'exception');
        
        if ($errorMode !== 'current') {
            $newOptions[\PDO::ATTR_ERRMODE] = match($errorMode) {
                'exception' => \PDO::ERRMODE_EXCEPTION,
                'warning' => \PDO::ERRMODE_WARNING,
                'silent' => \PDO::ERRMODE_SILENT
            };
        }

        // Persistent Connections
        $persistent = $this->choice('Persistent Connections (CRITICAL for multi-tenancy)', [
            'false' => 'false - Disabled (STRONGLY RECOMMENDED for multi-tenant)',
            'true' => 'true - Enabled (NOT recommended - causes tenant data conflicts)',
            'current' => 'Keep current setting'
        ], 'false');
        
        if ($persistent !== 'current') {
            $newOptions[\PDO::ATTR_PERSISTENT] = $persistent === 'true';
        }

        // Emulate Prepares
        $emulate = $this->choice('Emulate Prepared Statements', [
            'false' => 'false - Use real prepared statements (Recommended)',
            'true' => 'true - Emulate prepared statements',
            'current' => 'Keep current setting'
        ], 'false');
        
        if ($emulate !== 'current') {
            $newOptions[\PDO::ATTR_EMULATE_PREPARES] = $emulate === 'true';
        }

        // Default Fetch Mode
        $fetchMode = $this->choice('Default Fetch Mode', [
            'assoc' => 'PDO::FETCH_ASSOC - Associative arrays (Recommended)',
            'num' => 'PDO::FETCH_NUM - Numeric arrays',
            'both' => 'PDO::FETCH_BOTH - Both numeric and associative',
            'obj' => 'PDO::FETCH_OBJ - Objects',
            'current' => 'Keep current setting'
        ], 'assoc');
        
        if ($fetchMode !== 'current') {
            $newOptions[\PDO::ATTR_DEFAULT_FETCH_MODE] = match($fetchMode) {
                'assoc' => \PDO::FETCH_ASSOC,
                'num' => \PDO::FETCH_NUM,
                'both' => \PDO::FETCH_BOTH,
                'obj' => \PDO::FETCH_OBJ
            };
        }

        // Connection Timeout
        if ($this->confirm('Set connection timeout for reliability?', true)) {
            $timeout = $this->ask('Connection timeout in seconds', '30');
            $newOptions[\PDO::ATTR_TIMEOUT] = (int)$timeout;
        }

        // Autocommit
        $autocommit = $this->choice('Autocommit Transactions (important for tenant isolation)', [
            'true' => 'true - Auto-commit enabled (Recommended for tenant isolation)',
            'false' => 'false - Manual transaction control',
            'current' => 'Keep current setting'
        ], 'true');
        
        if ($autocommit !== 'current') {
            $newOptions[\PDO::ATTR_AUTOCOMMIT] = $autocommit === 'true';
        }

        // Multi-tenant specific options
        if ($this->confirm('Add MySQL-specific optimizations for multi-tenancy?', true)) {
            $newOptions[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $newOptions[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"';
        }

        if (!empty($newOptions)) {
            $this->newLine();
            $this->info('📝 Summary of changes:');
            foreach ($newOptions as $option => $value) {
                $optionName = $this->getPDOOptionName($option);
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (is_string($value) ? "'{$value}'" : $value);
                $this->line("  • {$optionName}: {$valueStr}");
            }
            $this->newLine();

            if ($this->confirm('Apply these changes to database.php?', true)) {
                $this->applyPDOConfiguration($connection, $newOptions);
            }
        } else {
            $this->info('No changes selected.');
        }
    }

    /**
     * Get PDO option name for display
     */
    private function getPDOOptionName(int $option): string
    {
        return match($option) {
            \PDO::ATTR_ERRMODE => 'Error Mode',
            \PDO::ATTR_PERSISTENT => 'Persistent Connections',
            \PDO::ATTR_EMULATE_PREPARES => 'Emulate Prepares',
            \PDO::ATTR_DEFAULT_FETCH_MODE => 'Default Fetch Mode',
            \PDO::ATTR_TIMEOUT => 'Connection Timeout',
            \PDO::ATTR_AUTOCOMMIT => 'Autocommit',
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => 'Buffered Queries',
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'Init Command',
            default => "Option {$option}"
        };
    }

    /**
     * Check if PDO configuration needs updates
     */
    private function checkIfPDONeedsUpdates(array $currentOptions): bool
    {
        $recommendedOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        foreach ($recommendedOptions as $option => $recommendedValue) {
            $currentValue = $currentOptions[$option] ?? null;
            if ($currentValue !== $recommendedValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Automatically configure PDO options in database.php
     */
    private function autoConfigurePDO(string $connection): void
    {
        $this->info('🔧 Automatically Configuring PDO Options...');
        $this->newLine();

        $databaseConfigPath = config_path('database.php');
        
        if (!file_exists($databaseConfigPath)) {
            $this->error('❌ config/database.php file not found!');
            return;
        }

        try {
            // Read the current database config file
            $configContent = file_get_contents($databaseConfigPath);
            
            // Define the recommended PDO options
            $pdoOptionsString = $this->generatePDOOptionsString();

            // Check if the connection already has an 'options' key
            $connectionStart = strpos($configContent, "'{$connection}' => [");
            
            if ($connectionStart !== false) {
                // Find the end of this connection block by counting brackets
                $searchStart = $connectionStart + strlen("'{$connection}' => [");
                $bracketCount = 1;
                $connectionEnd = $searchStart;
                
                while ($bracketCount > 0 && $connectionEnd < strlen($configContent)) {
                    $char = $configContent[$connectionEnd];
                    if ($char === '[') {
                        $bracketCount++;
                    } elseif ($char === ']') {
                        $bracketCount--;
                    }
                    $connectionEnd++;
                }

                // Extract the connection content
                $connectionBlock = substr($configContent, $connectionStart, $connectionEnd - $connectionStart);
                
                // Define the new options array
                $newOptionsArray = "        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\"STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\"',
        ],";

                // Check if options already exist
                if (preg_match("/'options'\s*=>\s*[^,}]+[,}]/s", $connectionBlock)) {
                    // Replace existing options
                    $newConnectionBlock = preg_replace(
                        "/'options'\s*=>\s*[^,}]+[,}]/s",
                        $newOptionsArray,
                        $connectionBlock
                    );
                } else {
                    // Add options before the closing bracket
                    $newConnectionBlock = preg_replace(
                        '/(\s*)\](\s*,?\s*)$/',
                        "\n{$newOptionsArray}\n    ]$2",
                        $connectionBlock
                    );
                }

                // Replace the connection block in the full config
                $updatedContent = substr_replace($configContent, $newConnectionBlock, $connectionStart, $connectionEnd - $connectionStart);

                // Create backup
                $backupPath = $databaseConfigPath . '.backup.' . date('Y-m-d-H-i-s');
                copy($databaseConfigPath, $backupPath);
                $this->info("� Backup created: {$backupPath}");

                // Write the updated configuration
                file_put_contents($databaseConfigPath, $updatedContent);

                $this->info('✅ PDO configuration updated successfully!');
                $this->info('📝 Updated settings:');
                $this->line('   • Error Mode: PDO::ERRMODE_EXCEPTION');
                $this->line('   • Persistent Connections: Disabled');
                $this->line('   • Emulate Prepares: Disabled');
                $this->line('   • Default Fetch Mode: PDO::FETCH_ASSOC');
                $this->line('   • Buffered Queries: Enabled');
                $this->line('   • MySQL Init Command: Set strict SQL mode');

                $this->newLine();
                $this->warn('⚠️  Important: You may need to restart your application/web server for changes to take effect');
                
                if ($this->confirm('Would you like to test the database connection with new settings?')) {
                    $this->testDatabaseConnectionWithNewSettings($connection);
                }

            } else {
                $this->error("❌ Could not find '{$connection}' connection configuration in database.php");
                $this->info('💡 Please manually add the PDO options to your database configuration');
                $this->showManualPDOConfiguration();
            }

        } catch (\Exception $e) {
            $this->error("❌ Error updating database configuration: {$e->getMessage()}");
            $this->info('💡 Please manually configure PDO options');
            $this->showManualPDOConfiguration();
        }
    }

    /**
     * Generate PDO options string for configuration file
     */
    private function generatePDOOptionsString(): string
    {
        return '                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,' . "\n" .
               '                PDO::ATTR_PERSISTENT => false,' . "\n" .
               '                PDO::ATTR_EMULATE_PREPARES => false,' . "\n" .
               '                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,' . "\n" .
               '                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,' . "\n" .
               '                PDO::MYSQL_ATTR_INIT_COMMAND => \'SET SESSION sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"\',';
    }

    /**
     * Show configuration summary
     */
    private function showConfigurationSummary(): void
    {
        $this->info('📝 Updated settings:');
        $this->line('   • Error Mode: PDO::ERRMODE_EXCEPTION');
        $this->line('   • Persistent Connections: Disabled');
        $this->line('   • Emulate Prepares: Disabled');
        $this->line('   • Default Fetch Mode: PDO::FETCH_ASSOC');
        $this->line('   • Buffered Queries: Enabled');
        $this->line('   • MySQL Init Command: Set strict SQL mode');
        $this->newLine();
        $this->warn('⚠️  Important: You may need to restart your application/web server for changes to take effect');
    }

    /**
     * Test database connection with new settings
     */
    private function testDatabaseConnectionWithNewSettings(string $connection): void
    {
        $this->info('🧪 Testing database connection with new PDO settings...');
        
        try {
            // Clear config cache to ensure new settings are loaded
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            
            // Test connection
            DB::connection($connection)->getPdo();
            
            $this->info('✅ Database connection test successful!');
            $this->info('🎉 New PDO settings are working correctly');
            
        } catch (\Exception $e) {
            $this->error("❌ Database connection test failed: {$e->getMessage()}");
            $this->warn('⚠️  You may need to check your PDO configuration or restart your application');
        }
    }

    /**
     * Show manual PDO configuration instructions
     */
    private function showManualPDOConfiguration(): void
    {
        $this->info('📋 Manual PDO Configuration Instructions:');
        $this->newLine();
        $this->info('Add or update the following in your config/database.php file:');
        $this->newLine();
        
        $this->line("'options' => [");
        $this->line("    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,");
        $this->line("    PDO::ATTR_PERSISTENT => false,");
        $this->line("    PDO::ATTR_EMULATE_PREPARES => false,");
        $this->line("    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,");
        $this->line("    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,");
        $this->line("    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\"STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\"',");
        $this->line("],");

        $this->newLine();
        if ($this->confirm('Would you like to see the complete database configuration example?')) {
            $this->showCompleteDBConfig();
        }
    }

    /**
     * Show complete database configuration example
     */
    private function showCompleteDBConfig(): void
    {
        $this->info('📄 Complete Database Configuration Example:');
        $this->newLine();
        $this->line("// config/database.php");
        $this->line("'mysql' => [");
        $this->line("    'driver' => 'mysql',");
        $this->line("    'host' => env('DB_HOST', '127.0.0.1'),");
        $this->line("    'port' => env('DB_PORT', '3306'),");
        $this->line("    'database' => env('DB_DATABASE', 'forge'),");
        $this->line("    'username' => env('DB_USERNAME', 'forge'),");
        $this->line("    'password' => env('DB_PASSWORD', ''),");
        $this->line("    'unix_socket' => env('DB_SOCKET', ''),");
        $this->line("    'charset' => 'utf8mb4',");
        $this->line("    'collation' => 'utf8mb4_unicode_ci',");
        $this->line("    'prefix' => '',");
        $this->line("    'prefix_indexes' => true,");
        $this->line("    'strict' => true,");
        $this->line("    'engine' => 'InnoDB',");
        $this->line("    'options' => [");
        $this->line("        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,");
        $this->line("        PDO::ATTR_PERSISTENT => false,");
        $this->line("        PDO::ATTR_EMULATE_PREPARES => false,");
        $this->line("        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,");
        $this->line("        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,");
        $this->line("        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\"STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\"',");
        $this->line("    ],");
        $this->line("],");
    }

    /**
     * Update environment file with new value
     */
    private function updateEnvFile(string $envPath, string $key, string $value): void
    {
        $envContent = file_get_contents($envPath);
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            $envContent .= "\n{$replacement}";
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Get PDO error mode string
     */
    private function getPDOErrorModeString(int $mode): string
    {
        return match($mode) {
            \PDO::ERRMODE_SILENT => 'PDO::ERRMODE_SILENT',
            \PDO::ERRMODE_WARNING => 'PDO::ERRMODE_WARNING', 
            \PDO::ERRMODE_EXCEPTION => '✅ PDO::ERRMODE_EXCEPTION',
            default => 'Unknown'
        };
    }

    /**
     * Get PDO fetch mode string
     */
    private function getPDOFetchModeString(int $mode): string
    {
        return match($mode) {
            \PDO::FETCH_ASSOC => '✅ PDO::FETCH_ASSOC',
            \PDO::FETCH_NUM => 'PDO::FETCH_NUM',
            \PDO::FETCH_BOTH => 'PDO::FETCH_BOTH',
            \PDO::FETCH_OBJ => 'PDO::FETCH_OBJ',
            default => 'Unknown'
        };
    }

    /**
     * Check for root user environment variables
     */
    private function checkRootUserEnvVars(): void
    {
        $this->info('🔍 Checking for Root User Environment Variables...');
        
        $rootUsername = env('DB_ROOT_USERNAME');
        $rootPassword = env('DB_ROOT_PASSWORD');
        
        if ($rootUsername || $rootPassword) {
            $this->table(['Variable', 'Status', 'Value'], [
                [
                    'DB_ROOT_USERNAME', 
                    $rootUsername ? '✅ Found' : '❌ Not Set',
                    $rootUsername ? $rootUsername . ' (ROOT USER)' : 'Not configured'
                ],
                [
                    'DB_ROOT_PASSWORD', 
                    $rootPassword ? '✅ Found' : '❌ Not Set',
                    $rootPassword ? '****** (Hidden)' : 'Not configured'
                ]
            ]);
            
            if ($rootUsername && $rootPassword) {
                $this->info('✅ Root user credentials are available in environment');
                $this->info('💡 These can be used for tenant database creation if current user lacks privileges');
            } else {
                $this->warn('⚠️  Partial root credentials found - both username and password are required');
            }
        } else {
            $this->warn('⚠️  No root user environment variables found');
            $this->info('💡 Consider setting DB_ROOT_USERNAME and DB_ROOT_PASSWORD for enhanced privileges');
            $this->info('   These can be used as fallback when current user lacks CREATE DATABASE privileges');
        }
    }

    private function displayConnectionInfo(array $config): void
    {
        $this->table(['Setting', 'Value'], [
            ['Host', $config['host'] ?? 'localhost'],
            ['Port', $config['port'] ?? '3306'],
            ['Database', $config['database'] ?? 'N/A'],
            ['Username', $config['username'] ?? 'N/A'],
            ['Driver', $config['driver'] ?? 'N/A'],
        ]);
    }

    private function getCurrentDatabaseUser(string $connection = null): string
    {
        try {
            // Get the current connection configuration
            $config = config("database.connections." . ($connection ?: config('database.default', 'mysql')));
            return $config['username'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    private function checkUserPrivileges(string $user, string $connection = null): void
    {
        try {
            $this->info("Checking privileges for user: {$user}");
            $this->newLine();

            // First try to get grants for the current user without specifying host
            try {
                $grants = DB::connection($connection)->select("SHOW GRANTS");
            } catch (\Exception $e) {
                // If that fails, try with specific user@host combinations
                $userWithHost = $this->findUserWithHost($user, $connection);
                if ($userWithHost) {
                    $grants = DB::connection($connection)->select("SHOW GRANTS FOR {$userWithHost}");
                } else {
                    $this->warn("Could not determine grants for user: {$user}");
                    return;
                }
            }
            
            if (empty($grants)) {
                $this->warn("No grants found for user: {$user}");
                return;
            }

            $privileges = [];
            $hasCreateDb = false;
            $hasDropDb = false;
            $hasCreateTable = false;
            $hasGlobalPrivs = false;

            foreach ($grants as $grant) {
                $grantText = array_values((array)$grant)[0];
                $privileges[] = $grantText;

                // Check for specific privileges
                if (stripos($grantText, 'CREATE') !== false) {
                    $hasCreateTable = true;
                    if (stripos($grantText, 'ON *.*') !== false || stripos($grantText, 'ALL PRIVILEGES') !== false) {
                        $hasCreateDb = true;
                        $hasGlobalPrivs = true;
                    }
                }
                
                if (stripos($grantText, 'DROP') !== false) {
                    $hasDropDb = true;
                }

                if (stripos($grantText, 'ALL PRIVILEGES') !== false) {
                    $hasCreateDb = true;
                    $hasDropDb = true;
                    $hasCreateTable = true;
                    $hasGlobalPrivs = true;
                }
            }

            // Display privilege summary
            $this->displayPrivilegeSummary($hasCreateDb, $hasDropDb, $hasCreateTable, $hasGlobalPrivs);
            $this->newLine();

            // Display detailed grants
            $this->info('📋 Detailed Grants:');
            foreach ($privileges as $privilege) {
                $this->line("  • {$privilege}");
            }

        } catch (\Exception $e) {
            $this->error("Error checking user privileges: {$e->getMessage()}");
        }
    }

    private function findUserWithHost(string $username, string $connection = null): ?string
    {
        try {
            // Get possible user@host combinations for this username
            $users = DB::connection($connection)->select("
                SELECT CONCAT('`', User, '`@`', Host, '`') as user_host
                FROM mysql.user 
                WHERE User = ?
                LIMIT 1
            ", [$username]);

            return $users[0]->user_host ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displayPrivilegeSummary(bool $hasCreateDb, bool $hasDropDb, bool $hasCreateTable, bool $hasGlobalPrivs): void
    {
        $this->info('🔍 Privilege Summary:');
        
        $summary = [
            ['Privilege', 'Status', 'Required For'],
            [
                'CREATE DATABASE', 
                $hasCreateDb ? '✅ Yes' : '❌ No', 
                'Creating tenant databases'
            ],
            [
                'DROP DATABASE', 
                $hasDropDb ? '✅ Yes' : '❌ No', 
                'Removing tenant databases'
            ],
            [
                'CREATE TABLE', 
                $hasCreateTable ? '✅ Yes' : '❌ No', 
                'Creating tenant tables'
            ],
            [
                'Global Privileges', 
                $hasGlobalPrivs ? '✅ Yes' : '❌ No', 
                'Full tenant management'
            ],
        ];

        $this->table($summary[0], array_slice($summary, 1));

        // Overall assessment
        if ($hasCreateDb && $hasDropDb && $hasCreateTable) {
            $this->info('✅ User has sufficient privileges for tenant operations');
        } else {
            $this->warn('⚠️  User may have insufficient privileges for some tenant operations');
            
            if (!$hasCreateDb) {
                $this->error('❌ Cannot create tenant databases - CREATE privilege on *.* required');
            }
            if (!$hasDropDb) {
                $this->warn('⚠️  Cannot drop tenant databases - DROP privilege recommended');
            }
            if (!$hasCreateTable) {
                $this->warn('⚠️  Cannot create tables - CREATE privilege required');
            }
        }
    }

    private function listPrivilegedUsers(string $connection = null): void
    {
        try {
            $this->info('👥 Users with CREATE DATABASE privileges:');
            
            // Query mysql.user table for users with global CREATE privilege
            $privilegedUsers = DB::connection($connection)->select("
                SELECT 
                    User, 
                    Host, 
                    Create_priv,
                    Drop_priv,
                    Super_priv,
                    Grant_priv
                FROM mysql.user 
                WHERE Create_priv = 'Y' 
                   OR Super_priv = 'Y'
                ORDER BY User
            ");

            if (empty($privilegedUsers)) {
                $this->warn('No users found with CREATE DATABASE privileges');
                return;
            }

            $tableData = [];
            foreach ($privilegedUsers as $user) {
                $tableData[] = [
                    'User' => "{$user->User}@{$user->Host}",
                    'CREATE' => $user->Create_priv === 'Y' ? '✅' : '❌',
                    'DROP' => $user->Drop_priv === 'Y' ? '✅' : '❌',
                    'SUPER' => $user->Super_priv === 'Y' ? '✅' : '❌',
                    'GRANT' => $user->Grant_priv === 'Y' ? '✅' : '❌',
                ];
            }

            $this->table(
                ['User@Host', 'CREATE', 'DROP', 'SUPER', 'GRANT'],
                $tableData
            );

            $this->newLine();
            $this->info('💡 Recommendations:');
            $this->line('  • Users with SUPER privilege can perform all operations');
            $this->line('  • For tenant operations, CREATE and DROP privileges are essential');
            $this->line('  • Consider using root user for initial setup if current user lacks privileges');

        } catch (\Exception $e) {
            $this->error("Error listing privileged users: {$e->getMessage()}");
        }
    }

    /**
     * Add tenant_template database connection to config/database.php
     */
    private function addTenantTemplateConnection(): void
    {
        $this->info('🏗️  Adding Tenant Template Database Connection');
        $this->newLine();

        $databaseConfigPath = config_path('database.php');
        
        if (!file_exists($databaseConfigPath)) {
            $this->error('❌ config/database.php file not found!');
            return;
        }

        // Check if tenant_template already exists
        $existingConfig = config('database.connections.tenant_template');
        if ($existingConfig) {
            $this->warn('⚠️  tenant_template connection already exists in database.php');
            
            if (!$this->confirm('Would you like to update it with optimized settings?', false)) {
                return;
            }
        }

        try {
            // Read the current database config file
            $configContent = file_get_contents($databaseConfigPath);
            
            // Generate the tenant_template connection configuration
            $tenantTemplateConfig = $this->generateTenantTemplateConfig();

            // Find the connections array
            $connectionsPos = strpos($configContent, "'connections' => [");
            
            if ($connectionsPos === false) {
                $this->error('❌ Could not find connections array in database.php');
                return;
            }

            // Find the position after the opening bracket of connections array
            $insertPos = strpos($configContent, '[', $connectionsPos) + 1;
            
            // Insert the tenant_template configuration
            $updatedContent = substr_replace(
                $configContent, 
                "\n" . $tenantTemplateConfig . "\n", 
                $insertPos, 
                0
            );

            // Create backup
            $backupPath = $databaseConfigPath . '.backup.' . date('Y-m-d-H-i-s');
            copy($databaseConfigPath, $backupPath);
            $this->info("📁 Backup created: {$backupPath}");

            // Write the updated configuration
            file_put_contents($databaseConfigPath, $updatedContent);

            $this->info('✅ tenant_template connection added successfully!');
            $this->newLine();
            $this->info('📋 Added configuration:');
            $this->line('   • Optimized for multi-tenant performance');
            $this->line('   • Persistent connections disabled (critical for multi-tenancy)');
            $this->line('   • Shorter timeouts for faster tenant switching');
            $this->line('   • Enhanced PDO settings for reliability');

            // Update tenancy.php to use the new template
            if ($this->confirm('Would you like to update config/tenancy.php to use this template?', true)) {
                $this->updateTenancyConfiguration();
            }

            $this->newLine();
            $this->warn('⚠️  Important: You may need to clear config cache for changes to take effect');
            $this->info('Run: php artisan config:clear');

        } catch (\Exception $e) {
            $this->error("❌ Error adding tenant_template connection: {$e->getMessage()}");
            $this->info('💡 Please manually add the tenant template connection');
            $this->showManualTenantTemplateConfig();
        }
    }

    /**
     * Generate tenant template configuration string
     */
    private function generateTenantTemplateConfig(): string
    {
        return "
        // Optimized connection template for multi-tenant databases
        'tenant_template' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => null, // Will be set dynamically by tenancy
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => false, // CRITICAL: Must be FALSE for multi-tenancy
                PDO::ATTR_EMULATE_PREPARES => false, // Better performance
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory usage
                PDO::ATTR_TIMEOUT => 10, // Shorter timeout for tenants
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode=\"STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\", SESSION wait_timeout=120, SESSION interactive_timeout=120',
            ]) : [],
        ],";
    }

    /**
     * Update tenancy configuration to use tenant_template
     */
    private function updateTenancyConfiguration(): void
    {
        $tenancyConfigPath = config_path('tenancy.php');
        
        if (!file_exists($tenancyConfigPath)) {
            $this->error('❌ config/tenancy.php file not found!');
            return;
        }

        try {
            $configContent = file_get_contents($tenancyConfigPath);
            
            // Update the template_tenant_connection setting
            $pattern = "/'template_tenant_connection'\s*=>\s*'[^']*'/";
            $replacement = "'template_tenant_connection' => 'tenant_template'";
            
            if (preg_match($pattern, $configContent)) {
                $configContent = preg_replace($pattern, $replacement, $configContent);
                file_put_contents($tenancyConfigPath, $configContent);
                $this->info('✅ Updated tenancy.php to use tenant_template connection');
            } else {
                $this->warn('⚠️  Could not automatically update tenancy.php');
                $this->info('💡 Please manually set template_tenant_connection to \'tenant_template\' in config/tenancy.php');
            }

        } catch (\Exception $e) {
            $this->error("❌ Error updating tenancy configuration: {$e->getMessage()}");
        }
    }

    /**
     * Show manual tenant template configuration instructions
     */
    private function showManualTenantTemplateConfig(): void
    {
        $this->info('📋 Manual Tenant Template Configuration:');
        $this->newLine();
        $this->info('Add the following to your config/database.php connections array:');
        $this->newLine();
        
        $this->line($this->generateTenantTemplateConfig());

        $this->newLine();
        $this->info('Then update config/tenancy.php:');
        $this->line("'template_tenant_connection' => 'tenant_template',");
    }
}
