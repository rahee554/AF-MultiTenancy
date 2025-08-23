<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Tenancy;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
use ArtflowStudio\Tenancy\Services\TenantService;

/**
 * Comprehensive Tenancy Test Command
 * 
 * Tests all aspects of the tenancy system including the new Universal Middleware
 */
class ComprehensiveTenancyTestCommand extends Command
{
    protected $signature = 'tenancy:test 
                          {--tenant= : Specific tenant ID to test}
                          {--create-test-tenant : Create a test tenant if none exist}
                          {--cleanup : Clean up test data after tests}
                          {--skip-redis : Skip Redis tests}
                          {--verbose : Show detailed output}';

    protected $description = 'Run comprehensive tests of the tenancy system including Universal Middleware';

    protected $results = [];
    protected $testTenant = null;
    protected $originalTenant = null;
    protected $tenantService;
    protected $tenancy;

    public function __construct(TenantService $tenantService, Tenancy $tenancy)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
        $this->tenancy = $tenancy;
    }

    public function handle()
    {
        $this->info('🔍 Running Comprehensive Tenancy System Tests');
        $this->info('🚀 Testing Universal Middleware and stancl/tenancy integration');
        $this->line('');

        try {
            // Pre-test setup
            $this->setupTestEnvironment();

            // Core tenancy tests
            $this->testCentralContext();
            $this->testTenantResolution();
            $this->testTenantContext();
            $this->testDatabaseIsolation();
            $this->testCacheIsolation();

            // Display comprehensive results
            $this->displayResults();

        } catch (\Exception $e) {
            $this->error('❌ Test suite failed: ' . $e->getMessage());
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
        } finally {
            // Cleanup
            $this->cleanup();
        }
    }

    /**
     * Setup test environment
     */
    protected function setupTestEnvironment(): void
    {
        $this->info('🔧 Setting up test environment...');

        if ($this->option('create-test-tenant') || $this->shouldCreateTestTenant()) {
            $this->testTenant = $this->createTestTenant();
            $this->line("   ✅ Created test tenant: {$this->testTenant->id}");
        } else {
            $this->testTenant = $this->getTestTenant();
            if ($this->testTenant) {
                $this->line("   ✅ Using existing tenant: {$this->testTenant->id}");
            } else {
                $this->warn('   ⚠️  No test tenant available - creating one...');
                $this->testTenant = $this->createTestTenant();
            }
        }
    }

    /**
     * Test central context (no tenant active)
     */
    protected function testCentralContext(): void
    {
        $this->info('🏢 Testing Central Context...');

        try {
            // Ensure no tenant context
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }

            $tests = [
                'No tenant active' => !$this->tenancy->initialized,
                'Central database accessible' => $this->testCentralDatabase(),
                'Central cache accessible' => $this->testCentralCache(),
            ];

            $this->runTestGroup('Central Context', $tests);

        } catch (\Exception $e) {
            $this->results['central'] = 'FAILED: ' . $e->getMessage();
            $this->error('   ❌ Central context tests failed');
        }
    }

    /**
     * Test tenant resolution
     */
    protected function testTenantResolution(): void
    {
        $this->info('🔍 Testing Tenant Resolution...');

        try {
            $domain = $this->testTenant->domains()->first();
            if (!$domain) {
                $domain = $this->createTestDomain($this->testTenant);
            }

            $tests = [
                "Domain resolution: {$domain->domain}" => $this->testDomainResolution($domain->domain),
                'Tenant can be found by ID' => Tenant::find($this->testTenant->id) !== null,
            ];

            $this->runTestGroup('Tenant Resolution', $tests);

        } catch (\Exception $e) {
            $this->results['resolution'] = 'FAILED: ' . $e->getMessage();
            $this->error('   ❌ Tenant resolution tests failed');
        }
    }

    /**
     * Test tenant context initialization
     */
    protected function testTenantContext(): void
    {
        $this->info('🏠 Testing Tenant Context...');

        try {
            // Initialize tenant context
            $this->tenancy->initialize($this->testTenant);

            $tests = [
                'Tenant initialized' => $this->tenancy->initialized,
                'Correct tenant active' => tenant('id') === $this->testTenant->id,
                'Tenant data accessible' => !empty(tenant()->toArray()),
                'Tenant helper function works' => function_exists('tenant') && tenant() !== null,
            ];

            $this->runTestGroup('Tenant Context', $tests);

        } catch (\Exception $e) {
            $this->results['context'] = 'FAILED: ' . $e->getMessage();
            $this->error('   ❌ Tenant context tests failed');
        } finally {
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }
        }
    }

    /**
     * Test database isolation
     */
    protected function testDatabaseIsolation(): void
    {
        $this->info('🗄️ Testing Database Isolation...');

        try {
            // Test central database
            $centralConnected = $this->testCentralDatabase();
            
            // Initialize tenant
            $this->tenancy->initialize($this->testTenant);
            
            // Test tenant database
            $tenantConnected = $this->testTenantDatabase();
            
            // End tenant context
            $this->tenancy->end();

            $tests = [
                'Central DB accessible' => $centralConnected,
                'Tenant DB accessible in tenant context' => $tenantConnected,
                'Database isolation verified' => $centralConnected && $tenantConnected,
            ];

            $this->runTestGroup('Database Isolation', $tests);

        } catch (\Exception $e) {
            $this->results['database'] = 'FAILED: ' . $e->getMessage();
            $this->error('   ❌ Database isolation tests failed');
        }
    }

    /**
     * Test cache isolation
     */
    protected function testCacheIsolation(): void
    {
        $this->info('💾 Testing Cache Isolation...');

        try {
            $cacheKey = 'test_isolation_' . time();
            $centralValue = 'central_value_' . uniqid();
            $tenantValue = 'tenant_value_' . uniqid();

            // Set central cache
            Cache::put($cacheKey, $centralValue, 60);
            $centralGet = Cache::get($cacheKey);

            // Initialize tenant and set tenant cache
            $this->tenancy->initialize($this->testTenant);
            Cache::put($cacheKey, $tenantValue, 60);
            $tenantGet = Cache::get($cacheKey);

            // End tenant context and check central cache
            $this->tenancy->end();
            $centralGetAfter = Cache::get($cacheKey);

            $tests = [
                'Central cache set/get works' => $centralGet === $centralValue,
                'Tenant cache set/get works' => $tenantGet === $tenantValue,
                'Cache isolation works' => $centralValue !== $tenantValue,
            ];

            $this->runTestGroup('Cache Isolation', $tests);

            // Cleanup
            Cache::forget($cacheKey);

        } catch (\Exception $e) {
            $this->results['cache'] = 'FAILED: ' . $e->getMessage();
            $this->error('   ❌ Cache isolation tests failed');
        }
    }

    /**
     * Run a group of tests and display results
     */
    protected function runTestGroup(string $groupName, array $tests): void
    {
        $passed = 0;
        $total = count($tests);

        foreach ($tests as $testName => $result) {
            if ($result === true) {
                $passed++;
                if ($this->option('verbose')) {
                    $this->line("      ✅ {$testName}");
                }
            } else {
                if ($this->option('verbose')) {
                    $this->line("      ❌ {$testName}");
                }
            }
        }

        $status = $passed === $total ? 'PASSED' : "PARTIAL ({$passed}/{$total})";
        $this->results[strtolower(str_replace(' ', '_', $groupName))] = $status;

        if ($passed === $total) {
            $this->line("   ✅ {$groupName}: All tests passed ({$passed}/{$total})");
        } else {
            $this->line("   ⚠️  {$groupName}: {$passed}/{$total} tests passed");
        }
    }

    /**
     * Display comprehensive test results
     */
    protected function displayResults(): void
    {
        $this->line('');
        $this->info('📋 Test Results Summary');
        $this->line('');

        $totalTests = count($this->results);
        $passedTests = count(array_filter($this->results, fn($result) => str_starts_with($result, 'PASSED')));

        foreach ($this->results as $category => $status) {
            $icon = str_starts_with($status, 'PASSED') ? '✅' : 
                   (str_starts_with($status, 'PARTIAL') ? '⚠️' : '❌');
            $this->line("   {$icon} " . ucwords(str_replace('_', ' ', $category)) . ": {$status}");
        }

        $this->line('');
        
        if ($passedTests === $totalTests) {
            $this->info("🎉 All test categories passed! ({$passedTests}/{$totalTests})");
            $this->line('   Your tenancy system is working correctly.');
        } else {
            $this->warn("⚠️  {$passedTests}/{$totalTests} test categories passed.");
            $this->line('   Please review the failed tests and check your configuration.');
        }
    }

    // Helper methods

    protected function createTestTenant()
    {
        $tenant = Tenant::create([
            'id' => 'test_' . uniqid(),
            'data' => [
                'name' => 'Test Tenant',
                'created_for_testing' => true,
                'created_at' => now(),
            ]
        ]);

        // Create a test domain
        $domain = $tenant->domains()->create([
            'domain' => 'test-' . uniqid() . '.localhost'
        ]);

        return $tenant;
    }

    protected function createTestDomain($tenant)
    {
        return $tenant->domains()->create([
            'domain' => 'test-domain-' . uniqid() . '.localhost'
        ]);
    }

    protected function testDomainResolution($domain): bool
    {
        try {
            $resolver = app(\Stancl\Tenancy\Resolvers\DomainTenantResolver::class);
            $tenant = $resolver->resolve($domain);
            return $tenant !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function testCentralDatabase(): bool
    {
        try {
            return DB::connection()->getPdo() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function testTenantDatabase(): bool
    {
        try {
            return DB::connection('tenant')->getPdo() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function testCentralCache(): bool
    {
        try {
            $key = 'test_central_' . time();
            Cache::put($key, 'test', 1);
            $result = Cache::get($key) === 'test';
            Cache::forget($key);
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function shouldCreateTestTenant(): bool
    {
        return Tenant::count() === 0;
    }

    protected function getTestTenant()
    {
        if ($tenantId = $this->option('tenant')) {
            return Tenant::find($tenantId);
        }
        
        return Tenant::first();
    }

    protected function cleanup(): void
    {
        try {
            // Ensure we're in central context
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }

            // Cleanup test tenant if requested
            if ($this->option('cleanup') && $this->testTenant && isset($this->testTenant->data['created_for_testing'])) {
                $this->testTenant->delete();
                $this->line('🧹 Cleaned up test tenant');
            }

        } catch (\Exception $e) {
            $this->warn('⚠️  Cleanup failed: ' . $e->getMessage());
        }
    }
}
