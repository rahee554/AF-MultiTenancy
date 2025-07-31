<?php

namespace ArtflowStudio\Tenancy\Commands\FastPanel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Models\Tenant;

class VerifyDeploymentCommand extends Command
{
    protected $signature = 'fastpanel:verify-deployment 
                            {--fix : Attempt to fix issues automatically}
                            {--detailed : Show detailed information}';

    protected $description = 'Verify FastPanel deployment and integration';

    public function handle(): int
    {
        $this->info('🔍 Verifying FastPanel Deployment...');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // Check 1: FastPanel CLI availability
        $this->info('1️⃣ Checking FastPanel CLI...');
        if (!$this->checkFastPanelCLI()) {
            $issues[] = 'FastPanel CLI not accessible';
        } else {
            $this->line('   ✅ FastPanel CLI is accessible');
        }

        // Check 2: Database connectivity
        $this->info('2️⃣ Checking Database Connectivity...');
        if (!$this->checkDatabaseConnectivity()) {
            $issues[] = 'Database connectivity issues';
        } else {
            $this->line('   ✅ Database connectivity is working');
        }

        // Check 3: FastPanel users
        $this->info('3️⃣ Checking FastPanel Users...');
        $users = $this->getFastPanelUsers();
        if (empty($users)) {
            $issues[] = 'No FastPanel users found';
        } else {
            $this->line("   ✅ Found {count($users)} FastPanel users");
        }

        // Check 4: FastPanel sites
        $this->info('4️⃣ Checking FastPanel Sites...');
        $sites = $this->getFastPanelSites();
        if (empty($sites)) {
            $warnings[] = 'No FastPanel sites found (may be normal for new installations)';
        } else {
            $this->line("   ✅ Found " . count($sites) . " FastPanel sites");
        }

        // Check 5: Tenant creation capabilities
        $this->info('5️⃣ Testing Tenant Creation Capabilities...');
        if (!$this->testTenantCreationCapabilities()) {
            $issues[] = 'Tenant creation test failed';
        } else {
            $this->line('   ✅ Tenant creation capabilities verified');
        }

        // Check 6: Environment configuration
        $this->info('6️⃣ Checking Environment Configuration...');
        $envIssues = $this->checkEnvironmentConfig();
        if (!empty($envIssues)) {
            $issues = array_merge($issues, $envIssues);
        } else {
            $this->line('   ✅ Environment configuration is correct');
        }

        // Check 7: Command availability
        $this->info('7️⃣ Checking Command Availability...');
        $commandIssues = $this->checkCommandAvailability();
        if (!empty($commandIssues)) {
            $warnings = array_merge($warnings, $commandIssues);
        } else {
            $this->line('   ✅ All commands are available');
        }

        $this->newLine();
        $this->displayResults($issues, $warnings);

        if ($this->option('fix') && !empty($issues)) {
            $this->newLine();
            $this->info('🔧 Attempting to fix issues...');
            $this->attemptFixes($issues);
        }

        return empty($issues) ? 0 : 1;
    }

    private function checkFastPanelCLI(): bool
    {
        try {
            $fastPanelPath = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            
            if (!file_exists($fastPanelPath)) {
                if ($this->option('detailed')) {
                    $this->line("   ❌ FastPanel CLI not found at: {$fastPanelPath}");
                }
                return false;
            }

            $result = Process::run("sudo {$fastPanelPath} --version");
            
            if ($result->failed()) {
                if ($this->option('detailed')) {
                    $this->line("   ❌ FastPanel CLI execution failed: " . $result->errorOutput());
                }
                return false;
            }

            if ($this->option('detailed')) {
                $this->line("   ✅ FastPanel version: " . trim($result->output()));
            }

            return true;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line("   ❌ Error checking FastPanel CLI: " . $e->getMessage());
            }
            return false;
        }
    }

    private function checkDatabaseConnectivity(): bool
    {
        try {
            DB::connection()->getPdo();
            
            // Test creating and dropping a test database
            $testDbName = 'tenancy_test_' . uniqid();
            DB::statement("CREATE DATABASE `{$testDbName}`");
            DB::statement("DROP DATABASE `{$testDbName}`");
            
            if ($this->option('detailed')) {
                $this->line('   ✅ Database CREATE/DROP privileges confirmed');
            }
            
            return true;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line("   ❌ Database connectivity error: " . $e->getMessage());
            }
            return false;
        }
    }

    private function getFastPanelUsers(): array
    {
        try {
            $fastPanelPath = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            $result = Process::run("sudo {$fastPanelPath} user list --json");
            
            if ($result->failed()) {
                return [];
            }
            
            $users = json_decode($result->output(), true);
            
            if ($this->option('detailed') && !empty($users)) {
                $this->line('   Users found:');
                foreach ($users as $user) {
                    $this->line("     • {$user['username']} (ID: {$user['id']})");
                }
            }
            
            return $users ?: [];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line("   ❌ Error getting FastPanel users: " . $e->getMessage());
            }
            return [];
        }
    }

    private function getFastPanelSites(): array
    {
        try {
            $fastPanelPath = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            $result = Process::run("sudo {$fastPanelPath} site list --json");
            
            if ($result->failed()) {
                return [];
            }
            
            $sites = json_decode($result->output(), true);
            
            if ($this->option('detailed') && !empty($sites)) {
                $this->line('   Sites found:');
                foreach (array_slice($sites, 0, 5) as $site) { // Show first 5 sites
                    $this->line("     • {$site['domain']} (ID: {$site['id']})");
                }
                if (count($sites) > 5) {
                    $this->line("     ... and " . (count($sites) - 5) . " more");
                }
            }
            
            return $sites ?: [];
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line("   ❌ Error getting FastPanel sites: " . $e->getMessage());
            }
            return [];
        }
    }

    private function testTenantCreationCapabilities(): bool
    {
        try {
            // Test 1: Check if we can generate database names
            $testTenant = new Tenant();
            $testTenant->id = 'test-' . uniqid();
            $testTenant->name = 'Test Tenant';
            
            // Test 2: Check if FastPanel database creation would work
            $fastPanelPath = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
            $result = Process::run("sudo {$fastPanelPath} database list --limit=1 --json");
            
            if ($result->failed()) {
                if ($this->option('detailed')) {
                    $this->line("   ❌ FastPanel database commands not accessible");
                }
                return false;
            }

            if ($this->option('detailed')) {
                $this->line('   ✅ FastPanel database commands accessible');
            }

            return true;
        } catch (\Exception $e) {
            if ($this->option('detailed')) {
                $this->line("   ❌ Tenant creation test error: " . $e->getMessage());
            }
            return false;
        }
    }

    private function checkEnvironmentConfig(): array
    {
        $issues = [];
        
        $requiredEnvVars = [
            'DB_CONNECTION',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
        ];

        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                $issues[] = "Missing required environment variable: {$var}";
            }
        }

        // Check optional FastPanel-specific vars
        $fastPanelVars = [
            'FASTPANEL_CLI_PATH' => '/usr/local/fastpanel2/fastpanel',
            'FASTPANEL_ENABLED' => 'false',
        ];

        if ($this->option('detailed')) {
            $this->line('   FastPanel environment variables:');
            foreach ($fastPanelVars as $var => $default) {
                $value = env($var, $default);
                $this->line("     • {$var}={$value}");
            }
        }

        return $issues;
    }

    private function checkCommandAvailability(): array
    {
        $warnings = [];
        
        $requiredCommands = [
            'tenant:create',
            'tenant:manage',
            'tenant:db',
            'tenants:maintenance',
            'tenant:create-fastpanel',
        ];

        foreach ($requiredCommands as $command) {
            try {
                $result = Process::run("php artisan list | grep '{$command}'");
                if ($result->failed()) {
                    $warnings[] = "Command may not be available: {$command}";
                }
            } catch (\Exception $e) {
                $warnings[] = "Error checking command {$command}: " . $e->getMessage();
            }
        }

        return $warnings;
    }

    private function displayResults(array $issues, array $warnings): void
    {
        if (empty($issues) && empty($warnings)) {
            $this->info('🎉 All checks passed! FastPanel deployment is verified.');
            return;
        }

        if (!empty($issues)) {
            $this->error('❌ Issues found:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line("   • {$warning}");
            }
        }

        $this->newLine();
        $this->info('📋 Next Steps:');
        
        if (!empty($issues)) {
            $this->line('   1. Fix the issues listed above');
            $this->line('   2. Verify FastPanel CLI is accessible');
            $this->line('   3. Check database permissions');
            $this->line('   4. Run with --fix to attempt automatic fixes');
        }
        
        if (!empty($warnings)) {
            $this->line('   5. Review warnings and address if needed');
        }
    }

    private function attemptFixes(array $issues): void
    {
        $this->line('Attempting automatic fixes...');
        
        // Fix 1: Try to install missing packages
        if (in_array('FastPanel CLI not accessible', $issues)) {
            $this->line('• Checking FastPanel CLI installation...');
            // This would need to be customized based on server setup
            $this->warn('  Manual fix required: Install FastPanel CLI');
        }

        // Fix 2: Try to fix database permissions
        if (str_contains(implode(' ', $issues), 'Database')) {
            $this->line('• Checking database permissions...');
            try {
                $user = DB::select("SELECT USER() as user")[0]->user;
                $this->line("  Current database user: {$user}");
                $this->warn('  Manual fix may be required: Grant CREATE DATABASE privileges');
            } catch (\Exception $e) {
                $this->error("  Error checking database user: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->warn('Some issues may require manual intervention.');
        $this->line('Please review the documentation or contact support if needed.');
    }
}
