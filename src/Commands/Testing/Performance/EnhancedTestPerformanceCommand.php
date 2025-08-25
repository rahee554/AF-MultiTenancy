<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Performance;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class EnhancedTestPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-performance-enhanced 
                            {--concurrent-users=10 : Number of concurrent users to simulate}
                            {--duration=30 : Test duration in seconds}
                            {--requests-per-user=5 : Requests per user}
                            {--test-isolation=5 : Number of tenants for isolation testing (max 10)}
                            {--test-persistence=3 : Number of tenants for persistence testing (max 5)}
                            {--crud-operations=10 : CRUD operations per tenant (max 50)}
                            {--progress : Show detailed progress}
                            {--skip-deep-tests : Skip resource-intensive deep tests}';

    /**
     * The console command description.
     */
    protected $description = 'Enhanced tenant performance testing with intelligent resource management and progress tracking';

    protected $startTime;
    protected $metrics = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);
        $this->initializeMetrics();

        $concurrentUsers = min((int) $this->option('concurrent-users'), 50); // Limit max users
        $duration = min((int) $this->option('duration'), 120); // Limit max duration
        $requestsPerUser = min((int) $this->option('requests-per-user'), 20); // Limit max requests
        $isolationTenantsCount = min((int) $this->option('test-isolation'), 10);
        $persistenceTenantsCount = min((int) $this->option('test-persistence'), 5);
        $crudOperations = min((int) $this->option('crud-operations'), 50);
        
        $this->displayHeader($concurrentUsers, $duration, $requestsPerUser);

        // Step 1: Get test tenants (limited number)
        $tenants = $this->getTestTenants();
        if ($tenants->isEmpty()) {
            $this->error('❌ No test tenants found. Run: php artisan tenancy:create-test-tenants');
            return 1;
        }

        // Step 2: Basic performance test
        $this->runBasicPerformanceTest($tenants, $concurrentUsers, $requestsPerUser);

        // Step 3: Isolation tests (limited tenants)
        if ($isolationTenantsCount > 0) {
            $this->runLimitedIsolationTest($tenants->take($isolationTenantsCount));
        }

        // Step 4: Persistence tests (limited tenants)  
        if ($persistenceTenantsCount > 0) {
            $this->runLimitedPersistenceTest($tenants->take($persistenceTenantsCount));
        }

        // Step 5: CRUD tests (limited operations)
        if (!$this->option('skip-deep-tests') && $crudOperations > 0) {
            $this->runLimitedCrudTest($tenants->take(3), $crudOperations); // Max 3 tenants
        }

        // Step 6: Display final results
        $this->displayFinalResults();

        return 0;
    }

    /**
     * Initialize metrics array
     */
    protected function initializeMetrics(): void
    {
        $this->metrics = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'response_times' => [],
            'memory_usage' => [],
            'connection_times' => [],
            'database_switches' => 0,
            'isolation_tests' => 0,
            'isolation_passed' => 0,
            'persistence_tests' => 0,  
            'persistence_passed' => 0,
            'crud_operations' => 0,
            'crud_success' => 0,
        ];
    }

    /**
     * Display test configuration header
     */
    protected function displayHeader(int $users, int $duration, int $requests): void
    {
        $this->info('🚀 Enhanced Tenancy Performance Testing');
        $this->newLine();
        
        $cacheAvailable = $this->checkCacheAvailability();
        $redisAvailable = $this->isRedisAvailable();
        
        $this->table(['Setting', 'Value'], [
            ['Concurrent Users', $users],
            ['Duration', $duration . 's'],
            ['Requests per User', $requests],
            ['Cache Driver', config('cache.default')],
            ['Cache Available', $cacheAvailable ? '✅ Yes' : '❌ No'],
            ['Redis Available', $redisAvailable ? '✅ Yes' : '❌ No'],
            ['Memory Limit', ini_get('memory_limit')],
            ['Max Execution Time', ini_get('max_execution_time')],
        ]);
        $this->newLine();
    }

    /**
     * Get test tenants (limited number for performance)
     */
    protected function getTestTenants()
    {
        // Get test tenants, limit to 10 for performance
        $tenants = Tenant::where('name', 'LIKE', '%Test%')
                         ->orWhere('name', 'LIKE', '%test%')
                         ->limit(10)
                         ->get();

        if ($tenants->isEmpty()) {
            // Get any tenants if no test tenants found, but limit to 5
            $tenants = Tenant::limit(5)->get();
        }

        $this->info("📊 Found {$tenants->count()} tenants for testing");
        return $tenants;
    }

    /**
     * Run basic performance test with progress tracking
     */
    protected function runBasicPerformanceTest($tenants, int $concurrentUsers, int $requestsPerUser): void
    {
        $this->info('⚡ Running Basic Performance Test...');
        
        // Pre-warm connections
        $this->preWarmConnections($tenants);

        $totalRequests = $concurrentUsers * $requestsPerUser;
        $requestsProcessed = 0;
        $batchSize = min(5, $concurrentUsers); // Smaller batches

        $progressBar = $this->output->createProgressBar($totalRequests);
        $progressBar->setFormat('Progress: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        while ($requestsProcessed < $totalRequests) {
            for ($batch = 0; $batch < $batchSize && $requestsProcessed < $totalRequests; $batch++) {
                $userId = ($requestsProcessed % $concurrentUsers) + 1;
                $this->simulateUserRequest($userId, $tenants);
                $requestsProcessed++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);
        
        $this->displayBasicResults();
    }

    /**
     * Pre-warm tenant connections
     */
    protected function preWarmConnections($tenants): void
    {
        $this->info('🔥 Pre-warming tenant connections...');
        
        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->start();

        foreach ($tenants as $tenant) {
            try {
                Tenancy::initialize($tenant);
                DB::connection('tenant')->select('SELECT 1');
                Tenancy::end();
                $progressBar->advance();
            } catch (\Exception $e) {
                $progressBar->advance();
                // Continue with next tenant
            }
        }
        
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Simulate a single user request
     */
    protected function simulateUserRequest(int $userId, $tenants): void
    {
        $tenant = $tenants->random();
        $requestStart = microtime(true);
        $memoryStart = memory_get_usage();

        try {
            $connectionStart = microtime(true);
            Tenancy::initialize($tenant);
            $connectionTime = (microtime(true) - $connectionStart) * 1000;

            // Lightweight database operation
            DB::connection('tenant')->getPdo()->query('SELECT 1');
            
            Tenancy::end();

            $requestTime = (microtime(true) - $requestStart) * 1000;
            $memoryUsed = memory_get_usage() - $memoryStart;

            $this->metrics['total_requests']++;
            $this->metrics['successful_requests']++;
            $this->metrics['response_times'][] = $requestTime;
            $this->metrics['memory_usage'][] = $memoryUsed;
            $this->metrics['connection_times'][] = $connectionTime;
            $this->metrics['database_switches']++;

        } catch (\Exception $e) {
            $this->metrics['total_requests']++;
            $this->metrics['failed_requests']++;
            Tenancy::end(); // Ensure cleanup
        }
    }

    /**
     * Run limited isolation test with progress tracking
     */
    protected function runLimitedIsolationTest($tenants): void
    {
        $this->info('🔒 Running Limited Isolation Test...');
        
        if ($tenants->count() < 2) {
            $this->warn('⚠️  Need at least 2 tenants for isolation testing');
            return;
        }

        $tenant1 = $tenants->first();
        $tenant2 = $tenants->skip(1)->first();

        $this->info("Testing isolation between tenants: {$tenant1->name} and {$tenant2->name}");

        try {
            $this->metrics['isolation_tests']++;
            
            // Create test data in tenant 1
            $testData1 = 'isolation_test_' . time() . '_' . $tenant1->id;
            $tenant1->run(function () use ($testData1) {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
                DB::statement('CREATE TABLE isolation_test (id INT PRIMARY KEY, tenant_data VARCHAR(255))');
                DB::table('isolation_test')->insert(['id' => 1, 'tenant_data' => $testData1]);
            });

            // Create different test data in tenant 2
            $testData2 = 'isolation_test_' . time() . '_' . $tenant2->id;
            $tenant2->run(function () use ($testData2) {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
                DB::statement('CREATE TABLE isolation_test (id INT PRIMARY KEY, tenant_data VARCHAR(255))');
                DB::table('isolation_test')->insert(['id' => 1, 'tenant_data' => $testData2]);
            });

            // Verify isolation
            $tenant1Data = $tenant1->run(function () {
                return DB::table('isolation_test')->first();
            });

            $tenant2Data = $tenant2->run(function () {
                return DB::table('isolation_test')->first();
            });

            if ($tenant1Data && $tenant2Data && 
                $tenant1Data->tenant_data === $testData1 && 
                $tenant2Data->tenant_data === $testData2 &&
                $tenant1Data->tenant_data !== $tenant2Data->tenant_data) {
                
                $this->metrics['isolation_passed']++;
                $this->info('  ✅ Data isolation test PASSED');
            } else {
                $this->error('  ❌ Data isolation test FAILED');
            }

            // Cleanup
            $tenant1->run(function () {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
            });
            $tenant2->run(function () {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
            });

        } catch (\Exception $e) {
            $this->error("  ❌ Isolation test error: " . $e->getMessage());
        }
    }

    /**
     * Run limited persistence test
     */
    protected function runLimitedPersistenceTest($tenants): void
    {
        $this->info('💾 Running Limited Persistence Test...');
        
        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->start();

        foreach ($tenants as $tenant) {
            try {
                $this->metrics['persistence_tests']++;
                
                $testData = 'persistence_test_' . time() . '_' . $tenant->id;
                
                // Create data
                $tenant->run(function () use ($testData) {
                    DB::statement('DROP TABLE IF EXISTS persistence_test');
                    DB::statement('CREATE TABLE persistence_test (id INT PRIMARY KEY, test_data VARCHAR(255))');
                    DB::table('persistence_test')->insert(['id' => 1, 'test_data' => $testData]);
                });

                // Disconnect and reconnect to test persistence
                Tenancy::end();
                
                // Verify data persists
                $retrievedData = $tenant->run(function () {
                    return DB::table('persistence_test')->first();
                });

                if ($retrievedData && $retrievedData->test_data === $testData) {
                    $this->metrics['persistence_passed']++;
                }

                // Cleanup
                $tenant->run(function () {
                    DB::statement('DROP TABLE IF EXISTS persistence_test');
                });

                $progressBar->advance();

            } catch (\Exception $e) {
                $progressBar->advance();
                // Continue with next tenant
            }
        }

        $progressBar->finish();
        $this->newLine();
        
        $this->info("  ✅ Persistence tests: {$this->metrics['persistence_passed']}/{$this->metrics['persistence_tests']} passed");
    }

    /**
     * Run limited CRUD test with progress tracking
     */
    protected function runLimitedCrudTest($tenants, int $operationsPerTenant): void
    {
        $this->info('📝 Running Limited CRUD Test...');
        $this->info("Testing {$operationsPerTenant} operations on {$tenants->count()} tenants");

        // Ensure users table exists
        foreach ($tenants as $tenant) {
            $tenant->run(function () {
                if (!DB::getSchemaBuilder()->hasTable('users')) {
                    DB::statement('CREATE TABLE users (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        name VARCHAR(255),
                        email VARCHAR(255) UNIQUE,
                        password VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )');
                }
            });
        }

        $totalOperations = $tenants->count() * $operationsPerTenant;
        $progressBar = $this->output->createProgressBar($totalOperations);
        $progressBar->setFormat('CRUD Progress: %current%/%max% [%bar%] %percent:3s%% %memory:6s%');
        $progressBar->start();

        $summary = [];
        
        foreach ($tenants as $tenant) {
            $creates = $reads = $updates = $deletes = 0;
            
            for ($i = 0; $i < $operationsPerTenant; $i++) {
                $operation = ($i % 4) + 1; // Cycle through operations
                
                try {
                    $tenant->run(function () use ($operation, &$creates, &$reads, &$updates, &$deletes) {
                        switch ($operation) {
                            case 1: // Create
                                DB::table('users')->insert([
                                    'name' => 'Test User ' . uniqid(),
                                    'email' => 'test' . uniqid() . '@example.com',
                                    'password' => bcrypt('password'),
                                ]);
                                $creates++;
                                break;
                                
                            case 2: // Read
                                DB::table('users')->first();
                                $reads++;
                                break;
                                
                            case 3: // Update
                                $user = DB::table('users')->first();
                                if ($user) {
                                    DB::table('users')->where('id', $user->id)->update([
                                        'name' => 'Updated User ' . uniqid(),
                                    ]);
                                }
                                $updates++;
                                break;
                                
                            case 4: // Delete
                                $user = DB::table('users')->first();
                                if ($user) {
                                    DB::table('users')->where('id', $user->id)->delete();
                                }
                                $deletes++;
                                break;
                        }
                    });
                    
                    $this->metrics['crud_operations']++;
                    $this->metrics['crud_success']++;
                    
                } catch (\Exception $e) {
                    $this->metrics['crud_operations']++;
                    // Continue with next operation
                }
                
                $progressBar->advance();
            }

            // Get final count for this tenant
            $finalCount = $tenant->run(function () {
                return DB::table('users')->count();
            });

            $summary[] = [
                substr($tenant->id, 0, 8) . '...',
                $creates,
                $reads, 
                $updates,
                $deletes,
                $finalCount,
            ];
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display CRUD summary
        $this->info('📊 CRUD Operations Summary');
        $this->table([
            'Tenant ID', 'Creates', 'Reads', 'Updates', 'Deletes', 'Final Count'
        ], $summary);
    }

    /**
     * Display basic performance results
     */
    protected function displayBasicResults(): void
    {
        $totalTime = microtime(true) - $this->startTime;
        $successRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['successful_requests'] / $this->metrics['total_requests']) * 100 
            : 0;

        $this->info('📊 Basic Performance Results');
        $this->table(['Metric', 'Value'], [
            ['Total Requests', $this->metrics['total_requests']],
            ['Successful Requests', $this->metrics['successful_requests']],
            ['Failed Requests', $this->metrics['failed_requests']],
            ['Success Rate', round($successRate, 1) . '%'],
            ['Database Switches', $this->metrics['database_switches']],
            ['Total Time', round($totalTime, 2) . 's'],
            ['Requests/Second', $totalTime > 0 ? round($this->metrics['total_requests'] / $totalTime, 2) : 0],
        ]);

        // Response time analysis
        if (!empty($this->metrics['response_times'])) {
            $avgResponseTime = array_sum($this->metrics['response_times']) / count($this->metrics['response_times']);
            $maxResponseTime = max($this->metrics['response_times']);
            $minResponseTime = min($this->metrics['response_times']);
            
            $this->newLine();
            $this->info('⚡ Response Time Analysis');
            $this->table(['Metric', 'Value (ms)'], [
                ['Average', round($avgResponseTime, 2)],
                ['Min', round($minResponseTime, 2)],
                ['Max', round($maxResponseTime, 2)],
            ]);
        }
    }

    /**
     * Display final comprehensive results
     */
    protected function displayFinalResults(): void
    {
        $totalTime = microtime(true) - $this->startTime;
        
        $this->newLine();
        $this->info('🎯 Final Test Results Summary');
        $this->newLine();

        // Overall verdict
        $successRate = $this->metrics['total_requests'] > 0 
            ? ($this->metrics['successful_requests'] / $this->metrics['total_requests']) * 100 
            : 0;

        if ($successRate >= 95) {
            $this->info('✅ EXCELLENT - System performing optimally');
        } elseif ($successRate >= 85) {
            $this->warn('⚠️  GOOD - Minor performance issues detected');
        } else {
            $this->error('❌ POOR - Significant performance issues detected');
        }

        // Test summary
        $this->table(['Test Category', 'Completed', 'Status'], [
            ['Basic Performance', $this->metrics['total_requests'] > 0 ? '✅' : '❌', 
             $successRate >= 90 ? 'PASSED' : 'NEEDS ATTENTION'],
            ['Isolation Tests', $this->metrics['isolation_tests'] > 0 ? '✅' : '⏭️ ', 
             $this->metrics['isolation_tests'] > 0 && $this->metrics['isolation_passed'] === $this->metrics['isolation_tests'] 
             ? 'PASSED' : ($this->metrics['isolation_tests'] > 0 ? 'FAILED' : 'SKIPPED')],
            ['Persistence Tests', $this->metrics['persistence_tests'] > 0 ? '✅' : '⏭️ ', 
             $this->metrics['persistence_tests'] > 0 && $this->metrics['persistence_passed'] === $this->metrics['persistence_tests'] 
             ? 'PASSED' : ($this->metrics['persistence_tests'] > 0 ? 'FAILED' : 'SKIPPED')],
            ['CRUD Tests', $this->metrics['crud_operations'] > 0 ? '✅' : '⏭️ ', 
             $this->metrics['crud_operations'] > 0 && $this->metrics['crud_success'] > ($this->metrics['crud_operations'] * 0.9) 
             ? 'PASSED' : ($this->metrics['crud_operations'] > 0 ? 'NEEDS ATTENTION' : 'SKIPPED')],
        ]);

        $this->newLine();
        $this->info("🕐 Total test time: " . round($totalTime, 2) . "s");
        $this->info("💾 Peak memory usage: " . $this->formatBytes(memory_get_peak_usage(true)));
        
        // Tips based on results
        $this->newLine();
        $this->info('💡 Performance Tips:');
        if (!$this->isRedisAvailable()) {
            $this->line('  • Consider enabling Redis for better caching performance');
        }
        if ($successRate < 95) {
            $this->line('  • Check database connection pool settings');
            $this->line('  • Review slow query logs');
        }
        $this->line('  • Run periodic performance tests to monitor system health');
        $this->line('  • Use tenancy:validate to check system integrity');
    }

    /**
     * Check if caching is available and working
     */
    protected function checkCacheAvailability(): bool
    {
        try {
            $testKey = 'tenancy_test_' . time();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            return $retrieved === $testValue;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if Redis is available
     */
    protected function isRedisAvailable(): bool
    {
        try {
            return Cache::getStore() instanceof \Illuminate\Cache\RedisStore;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
