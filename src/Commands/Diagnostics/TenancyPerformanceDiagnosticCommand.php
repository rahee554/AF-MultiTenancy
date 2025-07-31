<?php

namespace ArtflowStudio\Tenancy\Commands\Diagnostics;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use ArtflowStudio\Tenancy\Models\Tenant;
use PDO;

class TenancyPerformanceDiagnosticCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:diagnose-performance 
                            {--detailed : Show detailed diagnostic information}
                            {--fix-issues : Automatically fix detected issues}
                            {--check-mysql : Check MySQL server configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose and fix common multi-tenant performance issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 TENANCY PERFORMANCE DIAGNOSTIC TOOL');
        $this->newLine();

        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Check 1: PDO Configuration
        $this->info('📊 Checking PDO Configuration...');
        $pdoIssues = $this->checkPDOConfiguration();
        $issues = array_merge($issues, $pdoIssues['issues']);
        $warnings = array_merge($warnings, $pdoIssues['warnings']);
        $recommendations = array_merge($recommendations, $pdoIssues['recommendations']);

        // Check 2: Database Connection Settings
        $this->info('🔌 Checking Database Connection Settings...');
        $dbIssues = $this->checkDatabaseConfiguration();
        $issues = array_merge($issues, $dbIssues['issues']);
        $warnings = array_merge($warnings, $dbIssues['warnings']);
        $recommendations = array_merge($recommendations, $dbIssues['recommendations']);

        // Check 3: Tenancy Configuration
        $this->info('🏢 Checking Tenancy Configuration...');
        $tenancyIssues = $this->checkTenancyConfiguration();
        $issues = array_merge($issues, $tenancyIssues['issues']);
        $warnings = array_merge($warnings, $tenancyIssues['warnings']);
        $recommendations = array_merge($recommendations, $tenancyIssues['recommendations']);

        // Check 4: MySQL Server Configuration (if requested)
        if ($this->option('check-mysql')) {
            $this->info('🗄️ Checking MySQL Server Configuration...');
            $mysqlIssues = $this->checkMySQLConfiguration();
            $issues = array_merge($issues, $mysqlIssues['issues']);
            $warnings = array_merge($warnings, $mysqlIssues['warnings']);
            $recommendations = array_merge($recommendations, $mysqlIssues['recommendations']);
        }

        // Check 5: System Resources
        $this->info('💾 Checking System Resources...');
        $resourceIssues = $this->checkSystemResources();
        $issues = array_merge($issues, $resourceIssues['issues']);
        $warnings = array_merge($warnings, $resourceIssues['warnings']);
        $recommendations = array_merge($recommendations, $resourceIssues['recommendations']);

        $this->newLine();
        $this->displayDiagnosticResults($issues, $warnings, $recommendations);

        // Auto-fix if requested
        if ($this->option('fix-issues') && !empty($issues)) {
            $this->newLine();
            $this->info('🔧 Attempting to fix detected issues...');
            $this->fixIssues($issues);
        }

        return empty($issues) ? 0 : 1;
    }

    /**
     * Check PDO Configuration
     */
    protected function checkPDOConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        $connections = ['mysql', 'tenant_template'];
        
        foreach ($connections as $connection) {
            $config = config("database.connections.{$connection}");
            
            if (!$config) {
                continue;
            }

            $options = $config['options'] ?? [];

            // Critical Issue: Persistent Connections
            if (isset($options[PDO::ATTR_PERSISTENT]) && $options[PDO::ATTR_PERSISTENT] === true) {
                $issues[] = "❌ CRITICAL: {$connection} has persistent connections enabled (PDO::ATTR_PERSISTENT = true)";
                $issues[] = "   This WILL cause connection pool exhaustion and tenant data leakage in multi-tenant systems";
            }

            // Check for recommended settings
            if (!isset($options[PDO::ATTR_ERRMODE]) || $options[PDO::ATTR_ERRMODE] !== PDO::ERRMODE_EXCEPTION) {
                $warnings[] = "⚠️  {$connection}: PDO error mode not set to exceptions";
            }

            if (!isset($options[PDO::ATTR_DEFAULT_FETCH_MODE]) || $options[PDO::ATTR_DEFAULT_FETCH_MODE] !== PDO::FETCH_ASSOC) {
                $warnings[] = "⚠️  {$connection}: Default fetch mode not set to associative array";
            }

            if (isset($options[PDO::ATTR_EMULATE_PREPARES]) && $options[PDO::ATTR_EMULATE_PREPARES] === true) {
                $warnings[] = "⚠️  {$connection}: Emulated prepared statements enabled (reduces performance)";
            }

            if (isset($options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]) && $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] === true) {
                $warnings[] = "⚠️  {$connection}: Buffered queries enabled (increases memory usage)";
            }

            if (!isset($options[PDO::ATTR_TIMEOUT])) {
                $recommendations[] = "💡 {$connection}: Consider setting PDO::ATTR_TIMEOUT for better connection management";
            }
        }

        return compact('issues', 'warnings', 'recommendations');
    }

    /**
     * Check Database Configuration
     */
    protected function checkDatabaseConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Check if tenant_template connection exists
        $tenantTemplate = config('database.connections.tenant_template');
        if (!$tenantTemplate) {
            $issues[] = "❌ Missing 'tenant_template' database connection configuration";
            $issues[] = "   This connection should be optimized specifically for tenant databases";
        }

        // Check tenancy configuration
        $templateConnection = config('tenancy.database.template_tenant_connection');
        if ($templateConnection === 'local' || !config("database.connections.{$templateConnection}")) {
            $issues[] = "❌ Invalid template_tenant_connection: '{$templateConnection}'";
            $issues[] = "   The template connection must exist in database.connections";
        }

        // Check default connection
        $defaultConnection = config('database.default');
        $centralConnection = config('tenancy.database.central_connection');
        
        if ($defaultConnection !== $centralConnection) {
            $warnings[] = "⚠️  Default DB connection ({$defaultConnection}) differs from central connection ({$centralConnection})";
        }

        return compact('issues', 'warnings', 'recommendations');
    }

    /**
     * Check Tenancy Configuration
     */
    protected function checkTenancyConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        $bootstrappers = config('tenancy.bootstrappers', []);
        
        // Check for filesystem bootstrapper issues
        if (in_array('Stancl\\Tenancy\\Bootstrappers\\FilesystemTenancyBootstrapper', $bootstrappers)) {
            $filesystemConfig = config('tenancy.filesystem');
            $disks = $filesystemConfig['disks'] ?? [];
            
            foreach ($disks as $disk) {
                if (!config("filesystems.disks.{$disk}")) {
                    $issues[] = "❌ Filesystem disk '{$disk}' configured for tenancy but not defined in filesystems.php";
                }
            }
        }

        // Check tenant model
        $tenantModel = config('tenancy.tenant_model');
        if (!class_exists($tenantModel)) {
            $issues[] = "❌ Tenant model class '{$tenantModel}' does not exist";
        }

        return compact('issues', 'warnings', 'recommendations');
    }

    /**
     * Check MySQL Server Configuration
     */
    protected function checkMySQLConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        try {
            // Check max connections
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value ?? 0;
            if ($maxConnections < 200) {
                $warnings[] = "⚠️  MySQL max_connections is {$maxConnections} (recommended: 500+ for multi-tenancy)";
            }

            // Check wait timeout
            $waitTimeout = DB::select("SHOW VARIABLES LIKE 'wait_timeout'")[0]->Value ?? 0;
            if ($waitTimeout > 600) {
                $warnings[] = "⚠️  MySQL wait_timeout is {$waitTimeout}s (recommended: 300s for multi-tenancy)";
            }

            // Check InnoDB buffer pool
            $bufferPool = DB::select("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")[0]->Value ?? 0;
            $bufferPoolMB = round($bufferPool / 1024 / 1024);
            if ($bufferPoolMB < 512) {
                $recommendations[] = "💡 InnoDB buffer pool is {$bufferPoolMB}MB (recommended: 1GB+ for better performance)";
            }

            // Check connection count
            $currentConnections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            $connectionPercentage = ($currentConnections / $maxConnections) * 100;
            if ($connectionPercentage > 80) {
                $warnings[] = "⚠️  High connection usage: {$currentConnections}/{$maxConnections} ({$connectionPercentage}%)";
            }

        } catch (\Exception $e) {
            $warnings[] = "⚠️  Could not check MySQL configuration: " . $e->getMessage();
        }

        return compact('issues', 'warnings', 'recommendations');
    }

    /**
     * Check System Resources
     */
    protected function checkSystemResources(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Check PHP memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        if ($memoryLimitBytes > 0 && $memoryLimitBytes < 256 * 1024 * 1024) {
            $warnings[] = "⚠️  PHP memory limit is {$memoryLimit} (recommended: 256M+ for multi-tenancy)";
        }

        // Check current memory usage
        $currentMemory = memory_get_usage(true);
        $currentMemoryMB = round($currentMemory / 1024 / 1024, 1);
        if ($memoryLimitBytes > 0) {
            $memoryPercentage = ($currentMemory / $memoryLimitBytes) * 100;
            if ($memoryPercentage > 70) {
                $warnings[] = "⚠️  High memory usage: {$currentMemoryMB}MB ({$memoryPercentage}% of limit)";
            }
        }

        // Check max execution time
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime > 0 && $maxExecutionTime < 300) {
            $recommendations[] = "💡 Consider increasing max_execution_time for long-running tenant operations";
        }

        return compact('issues', 'warnings', 'recommendations');
    }

    /**
     * Display diagnostic results
     */
    protected function displayDiagnosticResults(array $issues, array $warnings, array $recommendations): void
    {
        $this->info('📋 DIAGNOSTIC RESULTS');
        $this->newLine();

        if (!empty($issues)) {
            $this->error('🚨 CRITICAL ISSUES DETECTED:');
            foreach ($issues as $issue) {
                $this->line("  {$issue}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  WARNINGS:');
            foreach ($warnings as $warning) {
                $this->line("  {$warning}");
            }
            $this->newLine();
        }

        if (!empty($recommendations)) {
            $this->info('💡 RECOMMENDATIONS:');
            foreach ($recommendations as $recommendation) {
                $this->line("  {$recommendation}");
            }
            $this->newLine();
        }

        if (empty($issues) && empty($warnings) && empty($recommendations)) {
            $this->info('✅ No issues detected - your tenancy configuration looks good!');
        }

        // Summary
        $this->info('📊 SUMMARY:');
        $this->line("  • Critical Issues: " . count($issues));
        $this->line("  • Warnings: " . count($warnings));
        $this->line("  • Recommendations: " . count($recommendations));
        
        if (!empty($issues)) {
            $this->newLine();
            $this->error('⚠️  System requires attention - critical issues detected!');
            $this->line('Run with --fix-issues to automatically fix some issues.');
        } elseif (!empty($warnings)) {
            $this->newLine();
            $this->warn('System is functional but could be optimized.');
        } else {
            $this->newLine();
            $this->info('🎉 System configuration is optimal!');
        }
    }

    /**
     * Fix detected issues
     */
    protected function fixIssues(array $issues): void
    {
        $fixed = 0;
        
        foreach ($issues as $issue) {
            if (str_contains($issue, 'persistent connections enabled')) {
                if ($this->fixPersistentConnections()) {
                    $this->info('✅ Fixed: Disabled persistent connections');
                    $fixed++;
                }
            }
            
            if (str_contains($issue, "Missing 'tenant_template'")) {
                if ($this->createTenantTemplateConnection()) {
                    $this->info('✅ Fixed: Created tenant_template connection');
                    $fixed++;
                }
            }
            
            if (str_contains($issue, "Invalid template_tenant_connection")) {
                if ($this->fixTemplateConnectionConfig()) {
                    $this->info('✅ Fixed: Updated tenancy template connection configuration');
                    $fixed++;
                }
            }
        }
        
        $this->newLine();
        $this->info("🔧 Fixed {$fixed} issues automatically.");
        
        if ($fixed > 0) {
            $this->warn('You may need to run "php artisan config:clear" to apply changes.');
        }
    }

    /**
     * Fix persistent connections
     */
    protected function fixPersistentConnections(): bool
    {
        // This would require modifying the config file
        // For now, just provide instructions
        $this->warn('Manual fix required: Edit config/database.php and set PDO::ATTR_PERSISTENT => false');
        return false;
    }

    /**
     * Create tenant template connection
     */
    protected function createTenantTemplateConnection(): bool
    {
        $this->warn('Manual fix required: Add tenant_template connection to config/database.php');
        return false;
    }

    /**
     * Fix template connection config
     */
    protected function fixTemplateConnectionConfig(): bool
    {
        $this->warn('Manual fix required: Update template_tenant_connection in config/tenancy.php');
        return false;
    }

    /**
     * Convert PHP memory limit to bytes
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }
}
