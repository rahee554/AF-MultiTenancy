<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TestPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-performance 
                            {--concurrent-users=100 : Number of concurrent users to simulate}
                            {--duration=30 : Test duration in seconds}
                            {--requests-per-user=5 : Requests per user}
                            {--test-isolation : Include database isolation testing}
                            {--test-persistence : Test database persistence across connections}';

    /**
     * The console command description.
     */
    protected $description = 'Test tenant performance with database isolation and persistence validation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $concurrentUsers = (int) $this->option('concurrent-users');
        $duration = (int) $this->option('duration');
        $requestsPerUser = (int) $this->option('requests-per-user');

        $this->info('🚀 Starting Tenancy Performance Test');
        $this->line("Concurrent Users: {$concurrentUsers}");
        $this->line("Duration: {$duration} seconds");
        $this->line("Requests per User: {$requestsPerUser}");
        $this->newLine();

        // Get test tenants
        $tenants = Tenant::where('name', 'LIKE', '%Test%')->limit(5)->get();
        
        if ($tenants->isEmpty()) {
            $this->error('No test tenants found. Run: php artisan tenancy:create-test-tenants');
            return 1;
        }

        $this->info("Found {$tenants->count()} test tenants for performance testing");

        // Performance metrics
        $metrics = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'response_times' => [],
            'memory_usage' => [],
            'connection_times' => [],
            'isolation_tests' => 0,
            'isolation_passed' => 0,
            'persistence_tests' => 0,
            'persistence_passed' => 0,
            'database_switches' => 0,
        ];

        $startTime = microtime(true);
        $this->info('Starting enhanced performance test...');

        // Run isolation tests if requested
        if ($this->option('test-isolation')) {
            $this->runIsolationTests($tenants, $metrics);
        }

        // Run persistence tests if requested
        if ($this->option('test-persistence')) {
            $this->runPersistenceTests($tenants, $metrics);
        }

        // Pre-warm connections for better performance
        $this->info('Pre-warming tenant connections...');
        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                DB::connection('tenant')->select('SELECT 1');
                tenancy()->end();
            } catch (\Exception $e) {
                // Continue with other tenants
            }
        }

        // Simulate concurrent users with optimized batching
        $batchSize = min(10, $concurrentUsers);
        $requestsProcessed = 0;
        $totalRequests = $concurrentUsers * $requestsPerUser;
        
        while ($requestsProcessed < $totalRequests) {
            for ($batch = 0; $batch < $batchSize && $requestsProcessed < $totalRequests; $batch++) {
                $userId = ($requestsProcessed % $concurrentUsers) + 1;
                $this->simulateUser($userId, $tenants, 1, $metrics);
                $requestsProcessed++;
            }
            
            // Progress indicator
            if ($requestsProcessed % 50 === 0) {
                $progress = round(($requestsProcessed / $totalRequests) * 100, 1);
                $this->line("Progress: {$progress}% ({$requestsProcessed}/{$totalRequests})");
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Display results
        $this->displayResults($metrics, $totalTime, $concurrentUsers);

        return 0;
    }

    /**
     * Simulate a user making requests (optimized version).
     */
    protected function simulateUser(int $userId, $tenants, int $requests, array &$metrics): void
    {
        for ($i = 1; $i <= $requests; $i++) {
            $tenant = $tenants->random();
            
            $requestStart = microtime(true);
            $memoryStart = memory_get_usage();
            
            try {
                // Test tenant switching performance with minimal overhead
                $connectionStart = microtime(true);
                tenancy()->initialize($tenant);
                $connectionTime = (microtime(true) - $connectionStart) * 1000; // ms
                
                // Perform the most lightweight database operation possible
                DB::connection('tenant')->getPdo()->query('SELECT 1');
                
                tenancy()->end();
                
                $requestTime = (microtime(true) - $requestStart) * 1000; // ms
                $memoryUsed = memory_get_usage() - $memoryStart;
                
                $metrics['total_requests']++;
                $metrics['successful_requests']++;
                $metrics['response_times'][] = $requestTime;
                $metrics['memory_usage'][] = $memoryUsed;
                $metrics['connection_times'][] = $connectionTime;
                $metrics['database_switches']++;
                
            } catch (\Exception $e) {
                $metrics['total_requests']++;
                $metrics['failed_requests']++;
                if ($userId <= 5) { // Only show first 5 errors to avoid spam
                    $this->warn("Request failed for user {$userId}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Run comprehensive database isolation tests.
     */
    protected function runIsolationTests($tenants, array &$metrics): void
    {
        $this->info('🔒 Running Database Isolation Tests...');
        
        if ($tenants->count() < 2) {
            $this->warn('Need at least 2 tenants for isolation testing');
            return;
        }

        $tenant1 = $tenants->first();
        $tenant2 = $tenants->last();
        
        // Test 1: Data isolation
        try {
            $metrics['isolation_tests']++;
            
            // Create unique test data in tenant 1
            $testData1 = 'isolation_test_' . time() . '_tenant1';
            $tenant1->run(function () use ($testData1) {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
                DB::statement('CREATE TABLE isolation_test (id INT PRIMARY KEY, tenant_data VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
                DB::table('isolation_test')->insert(['id' => 1, 'tenant_data' => $testData1]);
            });

            // Create different test data in tenant 2
            $testData2 = 'isolation_test_' . time() . '_tenant2';
            $tenant2->run(function () use ($testData2) {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
                DB::statement('CREATE TABLE isolation_test (id INT PRIMARY KEY, tenant_data VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
                DB::table('isolation_test')->insert(['id' => 1, 'tenant_data' => $testData2]);
            });

            // Verify tenant 1 data
            $tenant1Data = null;
            $tenant1->run(function () use (&$tenant1Data) {
                $tenant1Data = DB::table('isolation_test')->first();
            });

            // Verify tenant 2 data
            $tenant2Data = null;
            $tenant2->run(function () use (&$tenant2Data) {
                $tenant2Data = DB::table('isolation_test')->first();
            });

            // Check isolation
            if ($tenant1Data && $tenant2Data && 
                $tenant1Data->tenant_data === $testData1 && 
                $tenant2Data->tenant_data === $testData2 &&
                $tenant1Data->tenant_data !== $tenant2Data->tenant_data) {
                
                $metrics['isolation_passed']++;
                $this->line("  ✅ Data isolation test passed");
            } else {
                $this->line("  ❌ Data isolation test failed");
            }

            // Cleanup
            $tenant1->run(function () {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
            });
            $tenant2->run(function () {
                DB::statement('DROP TABLE IF EXISTS isolation_test');
            });

        } catch (\Exception $e) {
            $this->line("  ❌ Isolation test error: " . $e->getMessage());
        }

        // Test 2: Schema isolation
        try {
            $metrics['isolation_tests']++;
            
            // Create table in tenant 1 only
            $tenant1->run(function () {
                DB::statement('DROP TABLE IF EXISTS schema_isolation_test');
                DB::statement('CREATE TABLE schema_isolation_test (id INT PRIMARY KEY, data VARCHAR(255))');
            });

            // Check if table exists in tenant 2 (it shouldn't)
            $tableExistsInTenant2 = false;
            $tenant2->run(function () use (&$tableExistsInTenant2) {
                try {
                    DB::select('SELECT 1 FROM schema_isolation_test LIMIT 1');
                    $tableExistsInTenant2 = true;
                } catch (\Exception $e) {
                    // Table doesn't exist, which is correct
                }
            });

            if (!$tableExistsInTenant2) {
                $metrics['isolation_passed']++;
                $this->line("  ✅ Schema isolation test passed");
            } else {
                $this->line("  ❌ Schema isolation test failed - table exists in both tenants");
            }

            // Cleanup
            $tenant1->run(function () {
                DB::statement('DROP TABLE IF EXISTS schema_isolation_test');
            });

        } catch (\Exception $e) {
            $this->line("  ❌ Schema isolation test error: " . $e->getMessage());
        }
    }

    /**
     * Run database persistence tests.
     */
    protected function runPersistenceTests($tenants, array &$metrics): void
    {
        $this->info('💾 Running Database Persistence Tests...');
        
        foreach ($tenants->take(3) as $tenant) {
            try {
                $metrics['persistence_tests']++;
                
                $testData = 'persistence_test_' . time() . '_' . $tenant->id;
                
                // Create test data
                $tenant->run(function () use ($testData) {
                    DB::statement('DROP TABLE IF EXISTS persistence_test');
                    DB::statement('CREATE TABLE persistence_test (id INT PRIMARY KEY, data VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
                    DB::table('persistence_test')->insert(['id' => 1, 'data' => $testData]);
                });

                // Disconnect and reconnect to test persistence
                tenancy()->end();
                sleep(1); // Brief pause to simulate real-world conditions
                
                // Reconnect and verify data persists
                $persistedData = null;
                $tenant->run(function () use (&$persistedData) {
                    $persistedData = DB::table('persistence_test')->first();
                });

                if ($persistedData && $persistedData->data === $testData) {
                    $metrics['persistence_passed']++;
                    $this->line("  ✅ Persistence test passed for tenant: {$tenant->name}");
                } else {
                    $this->line("  ❌ Persistence test failed for tenant: {$tenant->name}");
                }

                // Cleanup
                $tenant->run(function () {
                    DB::statement('DROP TABLE IF EXISTS persistence_test');
                });

            } catch (\Exception $e) {
                $this->line("  ❌ Persistence test error for tenant {$tenant->name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Display performance test results.
     */
    protected function displayResults(array $metrics, float $totalTime, int $concurrentUsers): void
    {
        $this->newLine();
        $this->info('📊 Performance Test Results');
        $this->line(str_repeat('=', 50));

        // Basic metrics
        $this->table([
            'Metric', 'Value'
        ], [
            ['Total Requests', $metrics['total_requests']],
            ['Successful Requests', $metrics['successful_requests']],
            ['Failed Requests', $metrics['failed_requests']],
            ['Success Rate', round(($metrics['successful_requests'] / max($metrics['total_requests'], 1)) * 100, 2) . '%'],
            ['Database Switches', $metrics['database_switches']],
            ['Total Time', round($totalTime, 2) . 's'],
            ['Requests/Second', round($metrics['total_requests'] / max($totalTime, 0.001), 2)],
        ]);

        // Isolation test results
        if ($metrics['isolation_tests'] > 0) {
            $this->newLine();
            $this->info('🔒 Database Isolation Results');
            $isolationRate = round(($metrics['isolation_passed'] / $metrics['isolation_tests']) * 100, 1);
            $this->table([
                'Metric', 'Value'
            ], [
                ['Isolation Tests Run', $metrics['isolation_tests']],
                ['Isolation Tests Passed', $metrics['isolation_passed']],
                ['Isolation Success Rate', $isolationRate . '%'],
                ['Isolation Status', $isolationRate >= 100 ? '✅ PERFECT' : ($isolationRate >= 95 ? '⚠️ GOOD' : '❌ POOR')],
            ]);
        }

        // Persistence test results
        if ($metrics['persistence_tests'] > 0) {
            $this->newLine();
            $this->info('💾 Database Persistence Results');
            $persistenceRate = round(($metrics['persistence_passed'] / $metrics['persistence_tests']) * 100, 1);
            $this->table([
                'Metric', 'Value'
            ], [
                ['Persistence Tests Run', $metrics['persistence_tests']],
                ['Persistence Tests Passed', $metrics['persistence_passed']],
                ['Persistence Success Rate', $persistenceRate . '%'],
                ['Persistence Status', $persistenceRate >= 100 ? '✅ PERFECT' : ($persistenceRate >= 95 ? '⚠️ GOOD' : '❌ POOR')],
            ]);
        }

        if (!empty($metrics['response_times'])) {
            // Response time statistics
            $responseTimes = $metrics['response_times'];
            sort($responseTimes);
            
            $this->newLine();
            $this->info('⚡ Response Time Analysis');
            $this->table([
                'Metric', 'Value (ms)'
            ], [
                ['Average', round(array_sum($responseTimes) / count($responseTimes), 2)],
                ['Median', round($responseTimes[count($responseTimes) / 2], 2)],
                ['95th Percentile', round($responseTimes[count($responseTimes) * 0.95], 2)],
                ['99th Percentile', round($responseTimes[count($responseTimes) * 0.99], 2)],
                ['Min', round(min($responseTimes), 2)],
                ['Max', round(max($responseTimes), 2)],
            ]);
        }

        if (!empty($metrics['connection_times'])) {
            // Connection time statistics
            $connectionTimes = $metrics['connection_times'];
            sort($connectionTimes);
            
            $this->newLine();
            $this->info('🔌 Database Connection Performance');
            $this->table([
                'Metric', 'Value (ms)'
            ], [
                ['Avg Connection Time', round(array_sum($connectionTimes) / count($connectionTimes), 2)],
                ['95th Percentile', round($connectionTimes[count($connectionTimes) * 0.95], 2)],
                ['Max Connection Time', round(max($connectionTimes), 2)],
            ]);
        }

        if (!empty($metrics['memory_usage'])) {
            // Memory usage statistics
            $memoryUsage = $metrics['memory_usage'];
            
            $this->newLine();
            $this->info('💾 Memory Usage Analysis');
            $this->table([
                'Metric', 'Value'
            ], [
                ['Avg Memory per Request', $this->formatBytes(array_sum($memoryUsage) / count($memoryUsage))],
                ['Max Memory per Request', $this->formatBytes(max($memoryUsage))],
                ['Total Memory Used', $this->formatBytes(array_sum($memoryUsage))],
                ['Current Memory Usage', $this->formatBytes(memory_get_usage())],
                ['Peak Memory Usage', $this->formatBytes(memory_get_peak_usage())],
            ]);
        }

        // Performance verdict
        $this->newLine();
        $this->performanceVerdict($metrics, $totalTime);
    }

    /**
     * Provide performance verdict.
     */
    protected function performanceVerdict(array $metrics, float $totalTime): void
    {
        $avgResponseTime = !empty($metrics['response_times']) ? array_sum($metrics['response_times']) / count($metrics['response_times']) : 0;
        $avgConnectionTime = !empty($metrics['connection_times']) ? array_sum($metrics['connection_times']) / count($metrics['connection_times']) : 0;
        $successRate = $metrics['total_requests'] > 0 ? ($metrics['successful_requests'] / $metrics['total_requests']) * 100 : 0;
        $requestsPerSecond = $totalTime > 0 ? $metrics['total_requests'] / $totalTime : 0;

        $this->info('🎯 Performance Verdict');
        
        // Response time verdict
        if ($avgResponseTime < 50) {
            $this->line('<fg=green>✅ Excellent response times (< 50ms average)</fg=green>');
        } elseif ($avgResponseTime < 100) {
            $this->line('<fg=yellow>⚠️ Good response times (< 100ms average)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor response times (> 100ms average)</fg=red>');
        }

        // Connection time verdict
        if ($avgConnectionTime < 10) {
            $this->line('<fg=green>✅ Excellent connection performance (< 10ms average)</fg=green>');
        } elseif ($avgConnectionTime < 50) {
            $this->line('<fg=yellow>⚠️ Good connection performance (< 50ms average)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor connection performance (> 50ms average)</fg=red>');
        }

        // Success rate verdict
        if ($successRate >= 99) {
            $this->line('<fg=green>✅ Excellent reliability (' . round($successRate, 1) . '% success rate)</fg=green>');
        } elseif ($successRate >= 95) {
            $this->line('<fg=yellow>⚠️ Good reliability (' . round($successRate, 1) . '% success rate)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Poor reliability (' . round($successRate, 1) . '% success rate)</fg=red>');
        }

        // Throughput verdict
        if ($requestsPerSecond >= 100) {
            $this->line('<fg=green>✅ High throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=green>');
        } elseif ($requestsPerSecond >= 50) {
            $this->line('<fg=yellow>⚠️ Moderate throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=yellow>');
        } else {
            $this->line('<fg=red>❌ Low throughput (' . round($requestsPerSecond, 1) . ' req/s)</fg=red>');
        }

        // Database isolation verdict
        if ($metrics['isolation_tests'] > 0) {
            $isolationRate = ($metrics['isolation_passed'] / $metrics['isolation_tests']) * 100;
            if ($isolationRate >= 100) {
                $this->line('<fg=green>✅ Perfect database isolation (' . round($isolationRate, 1) . '% success)</fg=green>');
            } elseif ($isolationRate >= 95) {
                $this->line('<fg=yellow>⚠️ Good database isolation (' . round($isolationRate, 1) . '% success)</fg=yellow>');
            } else {
                $this->line('<fg=red>❌ Poor database isolation (' . round($isolationRate, 1) . '% success)</fg=red>');
            }
        }

        // Database persistence verdict
        if ($metrics['persistence_tests'] > 0) {
            $persistenceRate = ($metrics['persistence_passed'] / $metrics['persistence_tests']) * 100;
            if ($persistenceRate >= 100) {
                $this->line('<fg=green>✅ Perfect database persistence (' . round($persistenceRate, 1) . '% success)</fg=green>');
            } elseif ($persistenceRate >= 95) {
                $this->line('<fg=yellow>⚠️ Good database persistence (' . round($persistenceRate, 1) . '% success)</fg=yellow>');
            } else {
                $this->line('<fg=red>❌ Poor database persistence (' . round($persistenceRate, 1) . '% success)</fg=red>');
            }
        }

        $this->newLine();
        $this->comment('💡 Tips for better performance:');
        $this->line('  • Use Redis for caching (CACHE_DRIVER=redis)');
        $this->line('  • Enable persistent connections');
        $this->line('  • Optimize database queries');
        $this->line('  • Consider connection pooling for high loads');
        
        if ($metrics['isolation_tests'] > 0 || $metrics['persistence_tests'] > 0) {
            $this->newLine();
            $this->comment('🔒 Database Integrity Tips:');
            $this->line('  • Ensure proper tenant context switching');
            $this->line('  • Verify database isolation in production');
            $this->line('  • Monitor for data leaks between tenants');
            $this->line('  • Test persistence under load conditions');
        }
    }

    /**
     * Format bytes to human readable format.
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
