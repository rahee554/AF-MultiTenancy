<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComprehensiveTenancyTestCommand extends Command
{
    protected $signature = 'tenancy:test-comprehensive
                            {--cleanup : Clean up test data after running tests}';

    protected $description = 'Run comprehensive tenancy tests including database creation, isolation, and cleanup';

    protected TenantService $tenantService;
    protected array $testTenants = [];

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle(): int
    {
        $this->info('🧪 Starting Comprehensive Tenancy Tests');
        $this->newLine();

        $results = [
            'tests_run' => 0,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'errors' => [],
        ];

        // Test 1: Create tenant with custom database name
        $this->runTest('Create tenant with custom database', function () {
            $tenant = $this->tenantService->createTenant(
                'Test Company Custom',
                'testcustom.example.com',
                'active',
                'test_custom_db_' . time()
            );
            
            $this->testTenants[] = $tenant;
            
            // Verify database was created
            $dbExists = $this->checkDatabaseExists($tenant->database);
            if (!$dbExists) {
                throw new \Exception("Custom database '{$tenant->database}' was not created");
            }
            
            return "Custom database '{$tenant->database}' created successfully";
        }, $results);

        // Test 2: Create tenant with default database naming
        $this->runTest('Create tenant with default database naming', function () {
            $tenant = $this->tenantService->createTenant(
                'Test Company Default',
                'testdefault.example.com'
            );
            
            $this->testTenants[] = $tenant;
            
            // Verify database was created with default naming
            $expectedDb = 'tenant_' . str_replace('-', '', $tenant->id);
            $dbExists = $this->checkDatabaseExists($expectedDb);
            if (!$dbExists) {
                throw new \Exception("Default database '{$expectedDb}' was not created");
            }
            
            return "Default database '{$expectedDb}' created successfully";
        }, $results);

        // Test 3: Test database isolation
        $this->runTest('Test database isolation', function () {
            if (count($this->testTenants) < 2) {
                throw new \Exception("Need at least 2 tenants for isolation test");
            }
            
            $tenant1 = $this->testTenants[0];
            $tenant2 = $this->testTenants[1];
            
            // Switch to tenant 1 and create test data
            $tenant1->run(function () {
                // Drop table if exists to start fresh
                DB::statement('DROP TABLE IF EXISTS test_isolation');
                DB::statement('CREATE TABLE test_isolation (id INT PRIMARY KEY, tenant_id VARCHAR(255), data VARCHAR(255))');
                DB::table('test_isolation')->insert([
                    'id' => 1, 
                    'tenant_id' => 'tenant1', 
                    'data' => 'tenant1_secret_data'
                ]);
            });
            
            // Switch to tenant 2 and create different test data
            $tenant2->run(function () {
                // Drop table if exists to start fresh  
                DB::statement('DROP TABLE IF EXISTS test_isolation');
                DB::statement('CREATE TABLE test_isolation (id INT PRIMARY KEY, tenant_id VARCHAR(255), data VARCHAR(255))');
                DB::table('test_isolation')->insert([
                    'id' => 1, 
                    'tenant_id' => 'tenant2', 
                    'data' => 'tenant2_secret_data'
                ]);
            });
            
            // Verify isolation: tenant 1 should only see its own data
            $tenant1Data = null;
            $tenant1->run(function () use (&$tenant1Data) {
                $tenant1Data = DB::table('test_isolation')->where('id', 1)->first();
            });
            
            // Verify isolation: tenant 2 should only see its own data
            $tenant2Data = null;
            $tenant2->run(function () use (&$tenant2Data) {
                $tenant2Data = DB::table('test_isolation')->where('id', 1)->first();
            });
            
            // Check isolation results
            if (!$tenant1Data || $tenant1Data->tenant_id !== 'tenant1' || $tenant1Data->data !== 'tenant1_secret_data') {
                throw new \Exception("Tenant 1 data corruption or not found");
            }
            
            if (!$tenant2Data || $tenant2Data->tenant_id !== 'tenant2' || $tenant2Data->data !== 'tenant2_secret_data') {
                throw new \Exception("Tenant 2 data corruption or not found");
            }
            
            // Cleanup test tables
            $tenant1->run(function () {
                DB::statement('DROP TABLE IF EXISTS test_isolation');
            });
            $tenant2->run(function () {
                DB::statement('DROP TABLE IF EXISTS test_isolation');
            });
            
            return "Database isolation verified - tenants have completely separate data";
        }, $results);

        // Test 4: Test migrations in tenant context
        $this->runTest('Test tenant migrations', function () {
            $tenant = $this->testTenants[0] ?? null;
            if (!$tenant) {
                throw new \Exception("No tenant available for migration test");
            }
            
            $this->tenantService->migrateTenant($tenant);
            
            // Verify migrations were run in the tenant database
            $tenant->run(function () {
                $tables = DB::select('SHOW TABLES');
                $tableNames = array_map(function ($table) {
                    return array_values((array) $table)[0];
                }, $tables);
                
                if (!in_array('users', $tableNames)) {
                    throw new \Exception("Users table not found in tenant database");
                }
            });
            
            return "Tenant migrations executed successfully";
        }, $results);

        // Test 5: Test seeding without conflicts
        $this->runTest('Test tenant seeding without conflicts', function () {
            $tenant = $this->testTenants[0] ?? null;
            if (!$tenant) {
                throw new \Exception("No tenant available for seeding test");
            }
            
            $this->tenantService->seedTenant($tenant);
            
            return "Tenant seeding completed without conflicts";
        }, $results);

        // Test 6: Test tenant deletion with database cleanup
        $this->runTest('Test tenant deletion with database cleanup', function () {
            if (empty($this->testTenants)) {
                throw new \Exception("No tenants available for deletion test");
            }
            
            $tenant = array_pop($this->testTenants);
            $databaseName = $tenant->getDatabaseName();
            
            $this->tenantService->deleteTenant($tenant);
            
            // Verify database was deleted
            $dbExists = $this->checkDatabaseExists($databaseName);
            if ($dbExists) {
                throw new \Exception("Database '{$databaseName}' was not deleted after tenant deletion");
            }
            
            return "Tenant and database deleted successfully";
        }, $results);

        // Cleanup remaining test tenants if requested
        if ($this->option('cleanup')) {
            $this->info('🧹 Cleaning up remaining test data...');
            foreach ($this->testTenants as $tenant) {
                try {
                    $this->tenantService->deleteTenant($tenant);
                    $this->line("   ✅ Deleted tenant: {$tenant->name}");
                } catch (\Exception $e) {
                    $this->line("   ❌ Failed to delete tenant: {$tenant->name} - {$e->getMessage()}");
                }
            }
        }

        // Display results
        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->info('======================');
        $this->info("Tests Run: {$results['tests_run']}");
        $this->info("Tests Passed: {$results['tests_passed']}");
        $this->info("Tests Failed: {$results['tests_failed']}");
        
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Test Failures:');
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }

        $success_rate = $results['tests_run'] > 0 ? 
            round(($results['tests_passed'] / $results['tests_run']) * 100, 1) : 0;
        
        $this->newLine();
        if ($success_rate >= 95) {
            $this->info("🏆 Success Rate: {$success_rate}% - EXCELLENT");
        } elseif ($success_rate >= 80) {
            $this->info("✅ Success Rate: {$success_rate}% - GOOD");
        } else {
            $this->error("❌ Success Rate: {$success_rate}% - NEEDS IMPROVEMENT");
        }

        return $results['tests_failed'] > 0 ? 1 : 0;
    }

    private function runTest(string $testName, callable $testFunction, array &$results): void
    {
        $results['tests_run']++;
        
        $this->line("🔍 Testing: {$testName}");
        
        try {
            $result = $testFunction();
            $results['tests_passed']++;
            $this->line("   ✅ {$result}");
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['errors'][] = "{$testName}: {$e->getMessage()}";
            $this->line("   ❌ {$e->getMessage()}");
        }
        
        $this->newLine();
    }

    private function checkDatabaseExists(string $databaseName): bool
    {
        try {
            $databases = DB::select('SHOW DATABASES');
            foreach ($databases as $db) {
                if ($db->Database === $databaseName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
