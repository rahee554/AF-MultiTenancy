<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class TenantIsolationTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-isolation 
                            {--tenants=5 : Number of tenants to test (max 10)}
                            {--operations=20 : Operations per tenant (max 100)}
                            {--detailed : Show detailed test results}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive tenant isolation testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxTenants = min((int) $this->option('tenants'), 10);
        $maxOperations = min((int) $this->option('operations'), 100);
        $detailed = $this->option('detailed');

        $this->info('🔒 Tenant Isolation Test Suite');
        $this->newLine();

        // Get test tenants
        $tenants = Tenant::limit($maxTenants)->get();
        if ($tenants->count() < 2) {
            $this->error('❌ Need at least 2 tenants for isolation testing');
            return 1;
        }

        $this->info("Testing isolation across {$tenants->count()} tenants with {$maxOperations} operations each");
        $this->newLine();

        $results = [];
        $overallPassed = true;

        // Test 1: Basic Data Isolation
        $this->info('📊 Test 1: Basic Data Isolation');
        $dataIsolationResult = $this->testDataIsolation($tenants, $detailed);
        $results['Data Isolation'] = $dataIsolationResult;
        if (!$dataIsolationResult['passed']) $overallPassed = false;

        // Test 2: Schema Isolation
        $this->info('🗃️  Test 2: Schema Isolation');
        $schemaIsolationResult = $this->testSchemaIsolation($tenants, $detailed);
        $results['Schema Isolation'] = $schemaIsolationResult;
        if (!$schemaIsolationResult['passed']) $overallPassed = false;

        // Test 3: User Data Cross-Contamination
        $this->info('👥 Test 3: User Data Cross-Contamination');
        $userIsolationResult = $this->testUserDataIsolation($tenants, $maxOperations, $detailed);
        $results['User Isolation'] = $userIsolationResult;
        if (!$userIsolationResult['passed']) $overallPassed = false;

        // Test 4: Connection State Isolation
        $this->info('🔌 Test 4: Connection State Isolation');
        $connectionIsolationResult = $this->testConnectionIsolation($tenants, $detailed);
        $results['Connection Isolation'] = $connectionIsolationResult;
        if (!$connectionIsolationResult['passed']) $overallPassed = false;

        // Display final results
        $this->displayFinalResults($results, $overallPassed);

        return $overallPassed ? 0 : 1;
    }

    /**
     * Test basic data isolation between tenants
     */
    protected function testDataIsolation($tenants, bool $detailed): array
    {
        $passed = true;
        $details = [];

        try {
            $testTable = 'isolation_test_' . time();
            
            // Create test data in each tenant
            foreach ($tenants as $index => $tenant) {
                $testData = "tenant_{$tenant->id}_data_" . time() . "_{$index}";
                
                $tenant->run(function () use ($testTable, $testData, $tenant) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                    DB::statement("CREATE TABLE {$testTable} (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        tenant_id VARCHAR(255),
                        test_data VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    DB::table($testTable)->insert([
                        'tenant_id' => $tenant->id,
                        'test_data' => $testData,
                    ]);
                });

                $details[] = "Created test data for tenant {$tenant->name}: {$testData}";
            }

            // Verify each tenant only sees its own data
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($testTable, $tenant, &$passed, &$details) {
                    $records = DB::table($testTable)->get();
                    
                    foreach ($records as $record) {
                        if ($record->tenant_id !== $tenant->id) {
                            $passed = false;
                            $details[] = "❌ FAIL: Tenant {$tenant->name} can see data from tenant {$record->tenant_id}";
                        }
                    }
                    
                    if ($passed) {
                        $details[] = "✅ PASS: Tenant {$tenant->name} only sees own data";
                    }
                });
            }

            // Cleanup
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                });
            }

        } catch (\Exception $e) {
            $passed = false;
            $details[] = "❌ ERROR: " . $e->getMessage();
        }

        if ($detailed) {
            foreach ($details as $detail) {
                $this->line("  {$detail}");
            }
        }

        $this->line($passed ? '  ✅ Data isolation: PASSED' : '  ❌ Data isolation: FAILED');
        $this->newLine();

        return ['passed' => $passed, 'details' => $details];
    }

    /**
     * Test schema isolation between tenants
     */
    protected function testSchemaIsolation($tenants, bool $detailed): array
    {
        $passed = true;
        $details = [];

        try {
            $testTable = 'schema_test_' . time();
            $tenant1 = $tenants->first();
            $tenant2 = $tenants->skip(1)->first();

            // Create table in tenant 1 only
            $tenant1->run(function () use ($testTable) {
                DB::statement("CREATE TABLE {$testTable} (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    data VARCHAR(255)
                )");
            });

            $details[] = "Created table {$testTable} in tenant {$tenant1->name}";

            // Check if table exists in tenant 2 (it shouldn't)
            $tenant2->run(function () use ($testTable, &$passed, &$details, $tenant2) {
                try {
                    DB::select("SELECT 1 FROM {$testTable} LIMIT 1");
                    $passed = false;
                    $details[] = "❌ FAIL: Table {$testTable} exists in tenant {$tenant2->name}";
                } catch (\Exception $e) {
                    $details[] = "✅ PASS: Table {$testTable} not accessible from tenant {$tenant2->name}";
                }
            });

            // Cleanup
            $tenant1->run(function () use ($testTable) {
                DB::statement("DROP TABLE IF EXISTS {$testTable}");
            });

        } catch (\Exception $e) {
            $passed = false;
            $details[] = "❌ ERROR: " . $e->getMessage();
        }

        if ($detailed) {
            foreach ($details as $detail) {
                $this->line("  {$detail}");
            }
        }

        $this->line($passed ? '  ✅ Schema isolation: PASSED' : '  ❌ Schema isolation: FAILED');
        $this->newLine();

        return ['passed' => $passed, 'details' => $details];
    }

    /**
     * Test user data isolation with multiple operations
     */
    protected function testUserDataIsolation($tenants, int $operations, bool $detailed): array
    {
        $passed = true;
        $details = [];

        try {
            $testTable = 'isolation_users_test_' . time();
            
            // Create our own test table to avoid conflicts with existing users table
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                    DB::statement("CREATE TABLE {$testTable} (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        tenant_id VARCHAR(255),
                        user_name VARCHAR(255),
                        user_email VARCHAR(255),
                        tenant_marker VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                });
            }

            // Create test users in each tenant
            $usersByTenant = [];
            foreach ($tenants as $tenant) {
                $usersByTenant[$tenant->id] = [];
                
                for ($i = 0; $i < min($operations, 10); $i++) { // Limit to 10 users per tenant
                    $timestamp = time();
                    $email = "test_isolation_{$timestamp}_{$tenant->id}_{$i}@example.com";
                    $marker = "tenant_{$tenant->id}_marker_{$timestamp}";
                    
                    $tenant->run(function () use ($testTable, $email, $marker, $tenant, $timestamp) {
                        DB::table($testTable)->insert([
                            'tenant_id' => $tenant->id,
                            'user_name' => "Test User {$tenant->id} {$timestamp}",
                            'user_email' => $email,
                            'tenant_marker' => $marker,
                        ]);
                    });
                    
                    $usersByTenant[$tenant->id][] = $email;
                }
                
                $details[] = "Created " . count($usersByTenant[$tenant->id]) . " test users in tenant {$tenant->name}";
            }

            // Verify isolation: each tenant should only see its own users
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($testTable, $tenant, $usersByTenant, &$passed, &$details) {
                    $users = DB::table($testTable)->get();
                    
                    foreach ($users as $user) {
                        // Check if this user belongs to this tenant
                        if (!in_array($user->user_email, $usersByTenant[$tenant->id])) {
                            $passed = false;
                            $details[] = "❌ FAIL: Tenant {$tenant->name} can see user: {$user->user_email} from tenant {$user->tenant_id}";
                        }
                        
                        // Double-check tenant_id matches
                        if ($user->tenant_id !== $tenant->id) {
                            $passed = false;
                            $details[] = "❌ FAIL: User {$user->user_email} has wrong tenant_id: {$user->tenant_id}, expected: {$tenant->id}";
                        }
                    }
                    
                    $ownUserCount = count($usersByTenant[$tenant->id]);
                    $actualUserCount = $users->count();
                    
                    if ($ownUserCount === $actualUserCount) {
                        $details[] = "✅ PASS: Tenant {$tenant->name} sees exactly {$actualUserCount} own users";
                    } else {
                        $passed = false;
                        $details[] = "❌ FAIL: Tenant {$tenant->name} expected {$ownUserCount} users, found {$actualUserCount}";
                    }
                });
            }

            // Cleanup test table
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                });
            }

        } catch (\Exception $e) {
            $passed = false;
            $details[] = "❌ ERROR: " . $e->getMessage();
        }

        if ($detailed) {
            foreach ($details as $detail) {
                $this->line("  {$detail}");
            }
        }

        $this->line($passed ? '  ✅ User data isolation: PASSED' : '  ❌ User data isolation: FAILED');
        $this->newLine();

        return ['passed' => $passed, 'details' => $details];
    }

    /**
     * Test connection state isolation
     */
    protected function testConnectionIsolation($tenants, bool $detailed): array
    {
        $passed = true;
        $details = [];

        try {
            $tenant1 = $tenants->first();
            $tenant2 = $tenants->skip(1)->first();

            // Set a session variable in tenant 1
            $tenant1->run(function () {
                DB::statement("SET @test_var = 'tenant1_value'");
            });

            // Check if variable exists in tenant 2 (it shouldn't)
            $tenant2->run(function () use (&$passed, &$details) {
                $result = DB::select("SELECT @test_var as test_var");
                if ($result && $result[0]->test_var === 'tenant1_value') {
                    $passed = false;
                    $details[] = "❌ FAIL: Session variable leaked between tenants";
                } else {
                    $details[] = "✅ PASS: Session variables are isolated";
                }
            });

            // Test database name isolation
            $tenant1DbName = $tenant1->run(function () {
                return DB::select("SELECT DATABASE() as db_name")[0]->db_name;
            });

            $tenant2DbName = $tenant2->run(function () {
                return DB::select("SELECT DATABASE() as db_name")[0]->db_name;
            });

            if ($tenant1DbName !== $tenant2DbName) {
                $details[] = "✅ PASS: Database contexts are properly isolated";
            } else {
                $passed = false;
                $details[] = "❌ FAIL: Both tenants using same database: {$tenant1DbName}";
            }

        } catch (\Exception $e) {
            $passed = false;
            $details[] = "❌ ERROR: " . $e->getMessage();
        }

        if ($detailed) {
            foreach ($details as $detail) {
                $this->line("  {$detail}");
            }
        }

        $this->line($passed ? '  ✅ Connection isolation: PASSED' : '  ❌ Connection isolation: FAILED');
        $this->newLine();

        return ['passed' => $passed, 'details' => $details];
    }

    /**
     * Display final test results
     */
    protected function displayFinalResults(array $results, bool $overallPassed): void
    {
        $this->info('📋 Final Isolation Test Results');
        $this->newLine();

        $tableData = [];
        foreach ($results as $testName => $result) {
            $tableData[] = [
                $testName,
                $result['passed'] ? '✅ PASSED' : '❌ FAILED',
                count($result['details']) . ' checks'
            ];
        }

        $this->table(['Test', 'Result', 'Details'], $tableData);

        $this->newLine();
        if ($overallPassed) {
            $this->info('🎉 All isolation tests PASSED! Your tenant isolation is working correctly.');
        } else {
            $this->error('⚠️  Some isolation tests FAILED! Please review the issues above.');
        }

        $this->newLine();
        $this->info('💡 Recommendations:');
        $this->line('  • Run isolation tests regularly in different environments');
        $this->line('  • Test with realistic data volumes');
        $this->line('  • Monitor for data leaks in production');
        $this->line('  • Use tenancy:validate for ongoing health checks');
    }
}
