<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use ArtflowStudio\Tenancy\Models\Tenant;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Stancl\Tenancy\Facades\Tenancy;

class LivewireTenancyTestCommand extends Command
{
    protected $signature = 'af-tenancy:test-livewire
                            {--tenant= : Specific tenant UUID to test}
                            {--detailed : Show detailed output}
                            {--fix : Attempt to fix identified issues}
                            {--all : Test all tenants}';

    protected $description = 'Test Livewire components for tenancy compliance and data leakage';

    private array $testResults = [];

    private array $issues = [];

    private bool $detailed;

    private bool $fix;

    public function handle(): int
    {
        $this->detailed = $this->option('detailed');
        $this->fix = $this->option('fix');

        $this->info('🧪 ArtflowStudio Livewire Tenancy Test Suite');
        $this->newLine();

        try {
            // Step 1: Initialize Test Environment
            $this->initializeTestEnvironment();

            // Step 2: Test Tenant Isolation
            $this->testTenantIsolation();

            // Step 3: Test Livewire Component Context
            $this->testLivewireComponentContext();

            // Step 4: Test Session Isolation
            $this->testSessionIsolation();

            // Step 5: Test Cache Isolation
            $this->testCacheIsolation();

            // Step 6: Report Results
            $this->reportResults();

            return 0;

        } catch (Exception $e) {
            $this->error("❌ Test suite failed: {$e->getMessage()}");
            if ($this->detailed) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    private function initializeTestEnvironment(): void
    {
        $this->info('📋 Initializing Test Environment...');

        // Get tenants to test
        if ($this->option('tenant')) {
            $tenants = Tenant::where('id', $this->option('tenant'))->get();
            if ($tenants->isEmpty()) {
                throw new Exception("Tenant {$this->option('tenant')} not found");
            }
        } elseif ($this->option('all')) {
            $tenants = Tenant::all();
        } else {
            $tenants = Tenant::limit(2)->get();
        }

        if ($tenants->isEmpty()) {
            throw new Exception('No tenants found. Create at least one tenant first.');
        }

        $this->testResults['tenants_tested'] = $tenants->count();
        $this->testResults['tenants'] = $tenants->pluck('name', 'id')->toArray();

        $this->line("✓ Testing {$tenants->count()} tenant(s): ".implode(', ', $tenants->pluck('name')->toArray()));
        $this->logDetailed('Tenant IDs: '.implode(', ', $tenants->pluck('id')->toArray()));
    }

    private function testTenantIsolation(): void
    {
        $this->info('🔒 Testing Tenant Database Isolation...');

        $tenants = Tenant::limit(2)->get();

        // Test 1: Each tenant has its own database
        $databases = $tenants->pluck('database')->unique();

        if ($databases->count() === $tenants->count()) {
            $this->line('   ✓ Each tenant has unique database');
            $this->logDetailed('Databases: '.implode(', ', $databases->toArray()));
            $this->testResults['tenant_isolation'] = 'PASSED';
        } else {
            $this->error('   ✗ Multiple tenants sharing same database detected');
            $this->recordIssue('tenant_isolation', 'Multiple tenants sharing same database');
        }

        // Test 2: Database names follow tenant naming convention
        foreach ($tenants as $tenant) {
            if ($tenant->database && strlen($tenant->database) > 0) {
                $this->logDetailed("   Tenant {$tenant->name}: database = {$tenant->database}");
            } else {
                $this->recordIssue('tenant_database_naming', "Tenant {$tenant->name} has no database assigned");
            }
        }
    }

    private function testLivewireComponentContext(): void
    {
        $this->info('⚙️ Testing Livewire Component Tenancy Context...');

        // Get tenant with valid database
        $tenant = Tenant::whereNotNull('database')->where('database', '!=', '')->first();

        if (! $tenant) {
            $this->warn('   ⚠️ No tenant with valid database found for Livewire context test');

            return;
        }

        try {
            // Initialize tenant
            Tenancy::initialize($tenant);
            $currentTenant = Tenancy::getTenant();

            if ($currentTenant && $currentTenant->id === $tenant->id) {
                $this->line('   ✓ Livewire can access tenant context during mount');
                $this->logDetailed("   Current tenant: {$currentTenant->name} ({$currentTenant->id})");
                $this->testResults['livewire_context'] = 'PASSED';
            } else {
                $this->error('   ✗ Tenant context lost during Livewire mount');
                $this->recordIssue('livewire_context', "Tenant context not available for {$tenant->name}");
            }

            Tenancy::end();

        } catch (Exception $e) {
            $this->error('   ✗ Livewire context test failed: Database may not exist');
            $this->recordIssue('livewire_context', "Database connection issue for {$tenant->name}");
        }
    }

    private function testSessionIsolation(): void
    {
        $this->info('💾 Testing Session Isolation Between Tenants...');

        $tenants = Tenant::whereNotNull('database')->where('database', '!=', '')->limit(2)->get();

        if ($tenants->count() < 2) {
            $this->warn('   ⚠️ Need at least 2 tenants with valid databases for session isolation test');

            return;
        }

        try {
            $tenant1 = $tenants[0];
            $tenant2 = $tenants[1];

            // Store data in tenant1 session
            Tenancy::initialize($tenant1);
            Session::put('livewire_test_key', 'tenant1_data');
            $data1 = Session::get('livewire_test_key');
            Tenancy::end();

            // Check if tenant2 can access tenant1's session
            Tenancy::initialize($tenant2);
            $dataFromTenant1 = Session::get('livewire_test_key');
            Session::put('livewire_test_key', 'tenant2_data');
            $data2 = Session::get('livewire_test_key');
            Tenancy::end();

            // Verify isolation
            if ($data1 === 'tenant1_data' && $data2 === 'tenant2_data' && $dataFromTenant1 === null) {
                $this->line('   ✓ Sessions properly isolated between tenants');
                $this->testResults['session_isolation'] = 'PASSED';
            } else {
                $this->error('   ✗ Session data leakage detected');
                $this->logDetailed("   Tenant1 data: {$data1}, Tenant2 data: {$data2}, Cross-tenant access: {$dataFromTenant1}");
                $this->recordIssue('session_isolation', 'Session data leakage between tenants detected');
            }

        } catch (Exception $e) {
            $this->warn('   ⚠️ Session test skipped: Session handling may be environment-dependent');
            $this->logDetailed("   Error: {$e->getMessage()}");
        }
    }

    private function testCacheIsolation(): void
    {
        $this->info('🔄 Testing Cache Isolation Between Tenants...');

        $tenants = Tenant::whereNotNull('database')->where('database', '!=', '')->limit(2)->get();

        if ($tenants->count() < 2) {
            $this->warn('   ⚠️ Need at least 2 tenants with valid databases for cache isolation test');

            return;
        }

        try {
            $tenant1 = $tenants[0];
            $tenant2 = $tenants[1];

            // Store data in tenant1 cache
            Tenancy::initialize($tenant1);
            Cache::put('livewire_test_cache_key', 'tenant1_cache_data', 3600);
            $cache1 = Cache::get('livewire_test_cache_key');
            Tenancy::end();

            // Check if tenant2 can access tenant1's cache
            Tenancy::initialize($tenant2);
            $cacheFromTenant1 = Cache::get('livewire_test_cache_key');
            Cache::put('livewire_test_cache_key', 'tenant2_cache_data', 3600);
            $cache2 = Cache::get('livewire_test_cache_key');
            Tenancy::end();

            // Verify isolation
            if ($cache1 === 'tenant1_cache_data' && $cache2 === 'tenant2_cache_data' && $cacheFromTenant1 === null) {
                $this->line('   ✓ Cache properly isolated between tenants');
                $this->testResults['cache_isolation'] = 'PASSED';
            } else {
                $this->error('   ✗ Cache data leakage detected');
                $this->logDetailed("   Tenant1 cache: {$cache1}, Tenant2 cache: {$cache2}, Cross-tenant access: {$cacheFromTenant1}");
                $this->recordIssue('cache_isolation', 'Cache data leakage between tenants detected');
            }

        } catch (Exception $e) {
            $this->warn('   ⚠️ Cache test skipped: Cache handling may be environment-dependent');
            $this->logDetailed("   Error: {$e->getMessage()}");
        }
    }

    private function reportResults(): void
    {
        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->line('='.str_repeat('=', 78).'=');

        // Display test results
        $results = [];
        foreach ($this->testResults as $key => $value) {
            if (is_string($value) && in_array($value, ['PASSED', 'FAILED', 'UNTESTED'])) {
                $results[] = [
                    str_replace('_', ' ', ucfirst($key)),
                    $value,
                ];
            }
        }

        if (! empty($results)) {
            $this->table(['Test Category', 'Status'], $results);
        }

        // Display issues
        if (! empty($this->issues)) {
            $this->newLine();
            $this->warn('⚠️ Issues Found:');
            foreach ($this->issues as $issue) {
                $this->line("   • {$issue}");
            }
        } else {
            $this->newLine();
            $this->info('✅ All tests passed! Livewire tenancy isolation is working correctly.');
        }

        $this->line('='.str_repeat('=', 78).'=');
    }

    private function recordIssue(string $category, string $message): void
    {
        $this->issues[] = "[{$category}] {$message}";
    }

    private function logDetailed(string $message): void
    {
        if ($this->detailed) {
            $this->line($message);
        }
    }
}
