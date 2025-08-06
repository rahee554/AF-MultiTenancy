<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class QuickInstallTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'af-tenancy:test-install';

    /**
     * The console command description.
     */
    protected $description = 'Quick test to verify AF-MultiTenancy installation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 AF-MultiTenancy Installation Test');
        $this->newLine();

        $passed = 0;
        $failed = 0;

        // Test 1: Check if stancl/tenancy is installed
        if (class_exists(\Stancl\Tenancy\TenancyServiceProvider::class)) {
            $this->line('✅ stancl/tenancy package installed');
            $passed++;
        } else {
            $this->error('❌ stancl/tenancy package not found');
            $failed++;
        }

        // Test 2: Check if tenant() helper exists
        if (function_exists('tenant')) {
            $this->line('✅ tenant() helper function available');
            $passed++;
        } else {
            $this->error('❌ tenant() helper function not available');
            $failed++;
        }

        // Test 3: Check configuration files
        if (config('tenancy.tenant_model')) {
            $this->line('✅ tenancy.php config loaded');
            $passed++;
        } else {
            $this->error('❌ tenancy.php config not found');
            $failed++;
        }

        // Test 4: Check database tables
        try {
            if (Schema::hasTable('tenants')) {
                $this->line('✅ tenants table exists');
                $passed++;
            } else {
                $this->error('❌ tenants table missing - run: php artisan migrate');
                $failed++;
            }
        } catch (\Exception $e) {
            $this->error('❌ Database connection issue: ' . $e->getMessage());
            $failed++;
        }

        // Test 5: Check TenantManager
        try {
            $manager = app(\Stancl\Tenancy\TenantManager::class);
            if ($manager) {
                $this->line('✅ TenantManager service available');
                $passed++;
            }
        } catch (\Exception $e) {
            $this->error('❌ TenantManager not available: ' . $e->getMessage());
            $failed++;
        }

        $this->newLine();
        $this->info("📊 Results: {$passed} passed, {$failed} failed");

        if ($failed === 0) {
            $this->info('🎉 Installation successful! You can now create tenants.');
            $this->line('Next steps:');
            $this->line('1. php artisan tenant:manage create');
            $this->line('2. Configure your routes for tenancy');
            return 0;
        } else {
            $this->error('🚨 Installation issues detected. Please fix them before proceeding.');
            return 1;
        }
    }
}
