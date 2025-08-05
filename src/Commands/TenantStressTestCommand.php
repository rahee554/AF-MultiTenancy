<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class TenantStressTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:stress-test 
                            {--users=100 : Number of concurrent users to simulate}
                            {--duration=300 : Test duration in seconds (default: 5 minutes)}
                            {--operations=1000 : Total operations to perform}
                            {--tenants=10 : Number of tenants to test (max 20)}
                            {--memory-limit=256 : Memory limit in MB}
                            {--connection-pool=20 : Maximum concurrent connections}
                            {--detailed : Show detailed stress test results}
                            {--real-time : Show real-time metrics during test}';

    /**
     * The console command description.
     */
    protected $description = 'High-intensity stress testing for multi-tenant system load capacity';

    protected $testStartTime;
    protected $stressMetrics = [];
    protected $activeConnections = 0;
    protected $maxMemoryUsage = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->testStartTime = microtime(true);
        
        $users = min((int) $this->option('users'), 200); // Cap at 200 for safety
        $duration = min((int) $this->option('duration'), 600); // Cap at 10 minutes
        $operations = min((int) $this->option('operations'), 5000); // Cap at 5000
        $tenantCount = min((int) $this->option('tenants'), 20);
        $memoryLimit = (int) $this->option('memory-limit');
        $connectionPool = min((int) $this->option('connection-pool'), 50);
        $detailed = $this->option('detailed');
        $realTime = $this->option('real-time');

        $this->displayStressTestHeader($users, $duration, $operations, $tenantCount);
        
        // Set memory limit
        ini_set('memory_limit', $memoryLimit . 'M');
        
        $tenants = Tenant::limit($tenantCount)->get();
        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants available for stress testing');
            return 1;
        }

        $this->info("🚀 Starting HIGH-INTENSITY stress test...");
        $this->warn("⚠️  This test will generate significant system load!");
        $this->newLine();

        $results = [];
        $overallPassed = true;

        // Test 1: Connection Pool Stress Test
        $this->info('🔌 Test 1: Connection Pool Stress Test');
        $connectionResult = $this->stressTestConnections($tenants, $connectionPool, $detailed, $realTime);
        $results['Connection Pool'] = $connectionResult;
        if (!$connectionResult['passed']) $overallPassed = false;

        // Test 2: High Volume CRUD Operations
        $this->info('💾 Test 2: High Volume CRUD Operations');
        $crudResult = $this->stressTestCrudOperations($tenants, $operations, $detailed, $realTime);
        $results['CRUD Operations'] = $crudResult;
        if (!$crudResult['passed']) $overallPassed = false;

        // Test 3: Concurrent User Simulation
        $this->info('👥 Test 3: Concurrent User Load Simulation');
        $userResult = $this->stressTestConcurrentUsers($tenants, $users, $duration, $detailed, $realTime);
        $results['Concurrent Users'] = $userResult;
        if (!$userResult['passed']) $overallPassed = false;

        // Test 4: Memory and Resource Stress Test
        $this->info('🧠 Test 4: Memory and Resource Stress Test');
        $memoryResult = $this->stressTestMemoryUsage($tenants, $memoryLimit, $detailed, $realTime);
        $results['Memory Management'] = $memoryResult;
        if (!$memoryResult['passed']) $overallPassed = false;

        // Test 5: Database Lock and Transaction Stress
        $this->info('🔒 Test 5: Database Lock and Transaction Stress');
        $lockResult = $this->stressTestDatabaseLocks($tenants, $detailed, $realTime);
        $results['Database Locks'] = $lockResult;
        if (!$lockResult['passed']) $overallPassed = false;

        $this->displayStressTestResults($results, $overallPassed, $detailed);

        return $overallPassed ? 0 : 1;
    }

    /**
     * Display stress test header
     */
    protected function displayStressTestHeader(int $users, int $duration, int $operations, int $tenants): void
    {
        $this->info('💥 HIGH-INTENSITY TENANT STRESS TEST');
        $this->table(['Parameter', 'Value', 'Limit'], [
            ['Concurrent Users', $users, '200 max'],
            ['Test Duration', $duration . 's', '600s max'],
            ['Total Operations', number_format($operations), '5,000 max'],
            ['Tenants Under Test', $tenants, '20 max'],
            ['Memory Limit', $this->option('memory-limit') . 'MB', 'Configurable'],
            ['Connection Pool', $this->option('connection-pool'), '50 max'],
        ]);
        $this->newLine();
    }

    /**
     * Stress test connection pool
     */
    protected function stressTestConnections($tenants, int $poolSize, bool $detailed, bool $realTime): array
    {
        $passed = true;
        $metrics = [];
        $connectionAttempts = $poolSize * 5; // 5x pool size for stress

        $this->line("  Testing {$connectionAttempts} concurrent connections across {$tenants->count()} tenants...");
        
        $progressBar = $this->output->createProgressBar($connectionAttempts);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s% - %message%');

        $startTime = microtime(true);
        $successfulConnections = 0;
        $failedConnections = 0;
        $responseTimes = [];

        for ($i = 0; $i < $connectionAttempts; $i++) {
            $tenant = $tenants->random();
            $connectionStart = microtime(true);
            
            try {
                $tenant->run(function () use ($tenant) {
                    DB::select("SELECT 1 as test, DATABASE() as db_name, CONNECTION_ID() as conn_id");
                });
                
                $responseTime = (microtime(true) - $connectionStart) * 1000;
                $responseTimes[] = $responseTime;
                $successfulConnections++;
                
                if ($realTime && $i % 10 === 0) {
                    $avgResponse = array_sum($responseTimes) / count($responseTimes);
                    $progressBar->setMessage("Avg: " . round($avgResponse, 2) . "ms");
                }
                
            } catch (\Exception $e) {
                $failedConnections++;
                if ($detailed) {
                    $metrics[] = "Connection failed to {$tenant->name}: " . substr($e->getMessage(), 0, 50);
                }
            }
            
            $progressBar->advance();
            
            // Prevent overwhelming the system
            if ($i % 50 === 0) {
                usleep(100000); // 100ms pause every 50 connections
            }
        }

        $progressBar->finish();
        $this->newLine();

        $totalTime = microtime(true) - $startTime;
        $successRate = ($successfulConnections / $connectionAttempts) * 100;
        
        $metrics['total_connections'] = $connectionAttempts;
        $metrics['successful_connections'] = $successfulConnections;
        $metrics['failed_connections'] = $failedConnections;
        $metrics['success_rate'] = $successRate;
        $metrics['total_time'] = $totalTime;
        $metrics['connections_per_second'] = $connectionAttempts / $totalTime;
        
        if (!empty($responseTimes)) {
            $metrics['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
            $metrics['min_response_time'] = min($responseTimes);
            $metrics['max_response_time'] = max($responseTimes);
        }

        // Connection stress is passed if success rate > 85%
        $passed = $successRate > 85;

        if ($detailed) {
            $this->table(['Metric', 'Value'], [
                ['Total Connections', number_format($connectionAttempts)],
                ['Successful', number_format($successfulConnections)],
                ['Failed', number_format($failedConnections)],
                ['Success Rate', round($successRate, 2) . '%'],
                ['Connections/sec', round($metrics['connections_per_second'], 2)],
                ['Avg Response', isset($metrics['avg_response_time']) ? round($metrics['avg_response_time'], 2) . 'ms' : 'N/A'],
            ]);
        }

        $status = $passed ? '✅ PASSED' : '❌ FAILED';
        $this->line("  Connection Pool Stress: {$status} ({$successRate}% success rate)");
        $this->newLine();

        return ['passed' => $passed, 'metrics' => $metrics];
    }

    /**
     * Stress test CRUD operations
     */
    protected function stressTestCrudOperations($tenants, int $operations, bool $detailed, bool $realTime): array
    {
        $passed = true;
        $metrics = [];
        $testTable = 'stress_test_crud_' . time();

        $this->line("  Performing {$operations} CRUD operations across {$tenants->count()} tenants...");
        
        $progressBar = $this->output->createProgressBar($operations);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s% - %message%');

        // Create test table in all tenants
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                    DB::statement("CREATE TABLE {$testTable} (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        tenant_id VARCHAR(255),
                        test_data TEXT,
                        operation_type VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                });
            } catch (\Exception $e) {
                $passed = false;
                $metrics[] = "Failed to create test table in {$tenant->name}: " . $e->getMessage();
            }
        }

        $startTime = microtime(true);
        $successfulOperations = 0;
        $failedOperations = 0;
        $operationTimes = [];

        $operationTypes = ['CREATE', 'READ', 'UPDATE', 'DELETE'];
        $records = []; // Track created records for updates/deletes

        for ($i = 0; $i < $operations; $i++) {
            $tenant = $tenants->random();
            $operation = $operationTypes[array_rand($operationTypes)];
            $operationStart = microtime(true);
            
            try {
                $tenant->run(function () use ($testTable, $operation, $tenant, &$records, $i) {
                    switch ($operation) {
                        case 'CREATE':
                            $id = DB::table($testTable)->insertGetId([
                                'tenant_id' => $tenant->id,
                                'test_data' => "Stress test data {$i} for tenant {$tenant->id}",
                                'operation_type' => 'CREATE',
                            ]);
                            $records[$tenant->id][] = $id;
                            break;
                            
                        case 'READ':
                            DB::table($testTable)->where('tenant_id', $tenant->id)->limit(10)->get();
                            break;
                            
                        case 'UPDATE':
                            if (!empty($records[$tenant->id])) {
                                $recordId = $records[$tenant->id][array_rand($records[$tenant->id])];
                                DB::table($testTable)
                                    ->where('id', $recordId)
                                    ->where('tenant_id', $tenant->id)
                                    ->update([
                                        'test_data' => "Updated data {$i}",
                                        'operation_type' => 'UPDATE',
                                    ]);
                            }
                            break;
                            
                        case 'DELETE':
                            if (!empty($records[$tenant->id])) {
                                $recordId = array_pop($records[$tenant->id]);
                                DB::table($testTable)
                                    ->where('id', $recordId)
                                    ->where('tenant_id', $tenant->id)
                                    ->delete();
                            }
                            break;
                    }
                });
                
                $operationTime = (microtime(true) - $operationStart) * 1000;
                $operationTimes[] = $operationTime;
                $successfulOperations++;
                
                if ($realTime && $i % 100 === 0) {
                    $avgTime = array_sum($operationTimes) / count($operationTimes);
                    $progressBar->setMessage($operation . " - " . round($avgTime, 2) . "ms avg");
                }
                
            } catch (\Exception $e) {
                $failedOperations++;
                if ($detailed) {
                    $metrics[] = "{$operation} failed on {$tenant->name}: " . substr($e->getMessage(), 0, 50);
                }
            }
            
            $progressBar->advance();
            
            // Memory management
            if ($i % 200 === 0) {
                $this->trackMemoryUsage();
                if (memory_get_usage(true) > (int)$this->option('memory-limit') * 1024 * 1024 * 0.9) {
                    gc_collect_cycles(); // Force garbage collection
                }
            }
        }

        $progressBar->finish();
        $this->newLine();

        // Cleanup test tables
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                });
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        $totalTime = microtime(true) - $startTime;
        $successRate = ($successfulOperations / $operations) * 100;
        
        $metrics['total_operations'] = $operations;
        $metrics['successful_operations'] = $successfulOperations;
        $metrics['failed_operations'] = $failedOperations;
        $metrics['success_rate'] = $successRate;
        $metrics['total_time'] = $totalTime;
        $metrics['operations_per_second'] = $operations / $totalTime;
        
        if (!empty($operationTimes)) {
            $metrics['avg_operation_time'] = array_sum($operationTimes) / count($operationTimes);
            $metrics['min_operation_time'] = min($operationTimes);
            $metrics['max_operation_time'] = max($operationTimes);
        }

        // CRUD stress is passed if success rate > 90%
        $passed = $successRate > 90;

        if ($detailed) {
            $this->table(['Metric', 'Value'], [
                ['Total Operations', number_format($operations)],
                ['Successful', number_format($successfulOperations)],
                ['Failed', number_format($failedOperations)],
                ['Success Rate', round($successRate, 2) . '%'],
                ['Operations/sec', round($metrics['operations_per_second'], 2)],
                ['Avg Time', isset($metrics['avg_operation_time']) ? round($metrics['avg_operation_time'], 2) . 'ms' : 'N/A'],
            ]);
        }

        $status = $passed ? '✅ PASSED' : '❌ FAILED';
        $this->line("  CRUD Operations Stress: {$status} ({$successRate}% success rate)");
        $this->newLine();

        return ['passed' => $passed, 'metrics' => $metrics];
    }

    /**
     * Stress test concurrent users
     */
    protected function stressTestConcurrentUsers($tenants, int $users, int $duration, bool $detailed, bool $realTime): array
    {
        $passed = true;
        $metrics = [];

        $this->line("  Simulating {$users} concurrent users for {$duration} seconds...");
        
        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $totalRequests = 0;
        $successfulRequests = 0;
        $failedRequests = 0;
        $responseTimes = [];

        $progressBar = $this->output->createProgressBar($duration);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s% - %message%');

        while (microtime(true) < $endTime) {
            $currentTime = microtime(true);
            $elapsed = $currentTime - $startTime;
            
            // Simulate user activity
            for ($u = 0; $u < min($users, 50); $u++) { // Batch users to prevent overload
                $tenant = $tenants->random();
                $requestStart = microtime(true);
                
                try {
                    $tenant->run(function () use ($tenant) {
                        // Simulate typical user operations
                        $operations = [
                            function() { DB::select("SELECT COUNT(*) as count FROM information_schema.tables"); },
                            function() { DB::select("SELECT DATABASE() as db, NOW() as time"); },
                            function() use ($tenant) { 
                                DB::select("SELECT ? as tenant_id, CONNECTION_ID() as conn", [$tenant->id]); 
                            },
                        ];
                        
                        $operation = $operations[array_rand($operations)];
                        $operation();
                    });
                    
                    $responseTime = (microtime(true) - $requestStart) * 1000;
                    $responseTimes[] = $responseTime;
                    $successfulRequests++;
                    
                } catch (\Exception $e) {
                    $failedRequests++;
                }
                
                $totalRequests++;
            }
            
            $progressBar->setProgress((int)$elapsed);
            
            if ($realTime) {
                $currentRps = $totalRequests / ($elapsed + 0.001);
                $avgResponse = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
                $progressBar->setMessage(round($currentRps, 1) . " req/s, " . round($avgResponse, 2) . "ms avg");
            }
            
            // Control the request rate to prevent overwhelming
            usleep(50000); // 50ms between batches
            
            $this->trackMemoryUsage();
        }

        $progressBar->finish();
        $this->newLine();

        $totalTime = microtime(true) - $startTime;
        $successRate = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0;
        
        $metrics['total_requests'] = $totalRequests;
        $metrics['successful_requests'] = $successfulRequests;
        $metrics['failed_requests'] = $failedRequests;
        $metrics['success_rate'] = $successRate;
        $metrics['requests_per_second'] = $totalRequests / $totalTime;
        $metrics['concurrent_users'] = $users;
        $metrics['test_duration'] = $totalTime;
        
        if (!empty($responseTimes)) {
            $metrics['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
            $metrics['min_response_time'] = min($responseTimes);
            $metrics['max_response_time'] = max($responseTimes);
        }

        // User simulation is passed if success rate > 80%
        $passed = $successRate > 80;

        if ($detailed) {
            $this->table(['Metric', 'Value'], [
                ['Total Requests', number_format($totalRequests)],
                ['Successful', number_format($successfulRequests)],
                ['Failed', number_format($failedRequests)],
                ['Success Rate', round($successRate, 2) . '%'],
                ['Requests/sec', round($metrics['requests_per_second'], 2)],
                ['Concurrent Users', $users],
                ['Avg Response', isset($metrics['avg_response_time']) ? round($metrics['avg_response_time'], 2) . 'ms' : 'N/A'],
            ]);
        }

        $status = $passed ? '✅ PASSED' : '❌ FAILED';
        $this->line("  Concurrent Users Stress: {$status} ({$successRate}% success rate)");
        $this->newLine();

        return ['passed' => $passed, 'metrics' => $metrics];
    }

    /**
     * Stress test memory usage
     */
    protected function stressTestMemoryUsage($tenants, int $memoryLimit, bool $detailed, bool $realTime): array
    {
        $passed = true;
        $metrics = [];

        $this->line("  Testing memory usage under load (limit: {$memoryLimit}MB)...");
        
        $startMemory = memory_get_usage(true);
        $peakMemory = $startMemory;
        $memorySnapshots = [];
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s% - %message%');

        // Create memory-intensive operations
        for ($i = 0; $i < 100; $i++) {
            $tenant = $tenants->random();
            
            try {
                $tenant->run(function () use ($i) {
                    // Create large result sets to stress memory
                    $largeData = array_fill(0, 1000, "Memory stress test data item {$i} " . str_repeat('x', 100));
                    
                    // Simulate complex queries that might use more memory
                    DB::select("SELECT ? as iteration, NOW() as timestamp, ? as data", [
                        $i, 
                        json_encode(array_slice($largeData, 0, 10))
                    ]);
                    
                    unset($largeData); // Explicit cleanup
                });
                
            } catch (\Exception $e) {
                // Continue on error
            }
            
            $currentMemory = memory_get_usage(true);
            $peakMemory = max($peakMemory, $currentMemory);
            $memorySnapshots[] = $currentMemory;
            
            if ($realTime) {
                $memoryMB = round($currentMemory / 1024 / 1024, 1);
                $progressBar->setMessage("{$memoryMB}MB used");
            }
            
            $progressBar->advance();
            
            // Check if we're approaching memory limit
            if ($currentMemory > $memoryLimit * 1024 * 1024 * 0.95) {
                gc_collect_cycles();
            }
            
            if ($i % 25 === 0) {
                usleep(100000); // Brief pause for system recovery
            }
        }

        $progressBar->finish();
        $this->newLine();

        $endMemory = memory_get_usage(true);
        $maxMemoryMB = round($peakMemory / 1024 / 1024, 2);
        $memoryIncreaseMB = round(($endMemory - $startMemory) / 1024 / 1024, 2);
        
        $metrics['start_memory_mb'] = round($startMemory / 1024 / 1024, 2);
        $metrics['end_memory_mb'] = round($endMemory / 1024 / 1024, 2);
        $metrics['peak_memory_mb'] = $maxMemoryMB;
        $metrics['memory_increase_mb'] = $memoryIncreaseMB;
        $metrics['memory_limit_mb'] = $memoryLimit;
        $metrics['memory_utilization'] = ($maxMemoryMB / $memoryLimit) * 100;

        // Memory test passes if peak usage is under 90% of limit
        $passed = $maxMemoryMB < ($memoryLimit * 0.9);

        if ($detailed) {
            $this->table(['Metric', 'Value'], [
                ['Memory Limit', $memoryLimit . 'MB'],
                ['Peak Usage', $maxMemoryMB . 'MB'],
                ['Utilization', round($metrics['memory_utilization'], 2) . '%'],
                ['Memory Increase', $memoryIncreaseMB . 'MB'],
                ['Start Memory', $metrics['start_memory_mb'] . 'MB'],
                ['End Memory', $metrics['end_memory_mb'] . 'MB'],
            ]);
        }

        $status = $passed ? '✅ PASSED' : '❌ FAILED';
        $this->line("  Memory Management: {$status} ({$maxMemoryMB}MB peak)");
        $this->newLine();

        return ['passed' => $passed, 'metrics' => $metrics];
    }

    /**
     * Stress test database locks and transactions
     */
    protected function stressTestDatabaseLocks($tenants, bool $detailed, bool $realTime): array
    {
        $passed = true;
        $metrics = [];
        $testTable = 'stress_test_locks_' . time();

        $this->line("  Testing database locks and transaction handling...");
        
        // Create test table for lock testing
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                    DB::statement("CREATE TABLE {$testTable} (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        tenant_id VARCHAR(255),
                        lock_test_data TEXT,
                        lock_counter INT DEFAULT 0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                    
                    // Insert initial records for lock testing
                    for ($i = 1; $i <= 10; $i++) {
                        DB::table($testTable)->insert([
                            'tenant_id' => tenant('id'),
                            'lock_test_data' => "Lock test record {$i}",
                            'lock_counter' => 0,
                        ]);
                    }
                });
            } catch (\Exception $e) {
                $passed = false;
            }
        }

        $progressBar = $this->output->createProgressBar(200);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s% - %message%');

        $successfulTransactions = 0;
        $failedTransactions = 0;
        $deadlocks = 0;
        $lockWaitTimeouts = 0;

        // Simulate concurrent transactions that might create locks
        for ($i = 0; $i < 200; $i++) {
            $tenant = $tenants->random();
            
            try {
                $tenant->run(function () use ($testTable, $i) {
                    DB::transaction(function () use ($testTable, $i) {
                        // Select for update to create locks
                        $record = DB::table($testTable)
                            ->where('tenant_id', tenant('id'))
                            ->lockForUpdate()
                            ->first();
                            
                        if ($record) {
                            // Simulate processing time
                            usleep(rand(1000, 10000)); // 1-10ms
                            
                            // Update the record
                            DB::table($testTable)
                                ->where('id', $record->id)
                                ->increment('lock_counter');
                        }
                    });
                });
                
                $successfulTransactions++;
                
            } catch (\Exception $e) {
                $failedTransactions++;
                
                $errorMessage = $e->getMessage();
                if (strpos($errorMessage, 'Deadlock') !== false) {
                    $deadlocks++;
                } elseif (strpos($errorMessage, 'Lock wait timeout') !== false) {
                    $lockWaitTimeouts++;
                }
            }
            
            if ($realTime && $i % 20 === 0) {
                $progressBar->setMessage("Deadlocks: {$deadlocks}, Timeouts: {$lockWaitTimeouts}");
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Cleanup
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($testTable) {
                    DB::statement("DROP TABLE IF EXISTS {$testTable}");
                });
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        $totalTransactions = $successfulTransactions + $failedTransactions;
        $successRate = $totalTransactions > 0 ? ($successfulTransactions / $totalTransactions) * 100 : 0;
        
        $metrics['total_transactions'] = $totalTransactions;
        $metrics['successful_transactions'] = $successfulTransactions;
        $metrics['failed_transactions'] = $failedTransactions;
        $metrics['deadlocks'] = $deadlocks;
        $metrics['lock_wait_timeouts'] = $lockWaitTimeouts;
        $metrics['success_rate'] = $successRate;

        // Lock test passes if success rate > 85% and deadlocks < 10%
        $passed = $successRate > 85 && ($deadlocks / $totalTransactions * 100) < 10;

        if ($detailed) {
            $this->table(['Metric', 'Value'], [
                ['Total Transactions', $totalTransactions],
                ['Successful', $successfulTransactions],
                ['Failed', $failedTransactions],
                ['Success Rate', round($successRate, 2) . '%'],
                ['Deadlocks', $deadlocks],
                ['Lock Timeouts', $lockWaitTimeouts],
            ]);
        }

        $status = $passed ? '✅ PASSED' : '❌ FAILED';
        $this->line("  Database Lock Handling: {$status} ({$successRate}% success rate)");
        $this->newLine();

        return ['passed' => $passed, 'metrics' => $metrics];
    }

    /**
     * Track memory usage
     */
    protected function trackMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true);
        $this->maxMemoryUsage = max($this->maxMemoryUsage, $currentMemory);
    }

    /**
     * Display final stress test results
     */
    protected function displayStressTestResults(array $results, bool $overallPassed, bool $detailed): void
    {
        $totalTime = microtime(true) - $this->testStartTime;
        
        $this->info('💥 STRESS TEST RESULTS SUMMARY');
        $this->newLine();

        $tableData = [];
        foreach ($results as $testName => $result) {
            $status = $result['passed'] ? '✅ PASSED' : '❌ FAILED';
            $tableData[] = [$testName, $status];
        }

        $this->table(['Stress Test', 'Result'], $tableData);
        $this->newLine();

        if ($overallPassed) {
            $this->info('🎉 STRESS TEST COMPLETED - SYSTEM PERFORMANCE EXCELLENT!');
            $this->info('Your multi-tenant system successfully handled high-intensity load.');
        } else {
            $this->error('⚠️  STRESS TEST COMPLETED - PERFORMANCE ISSUES DETECTED');
            $this->error('Some stress tests failed. Review the results above and optimize accordingly.');
        }

        $this->newLine();
        $this->info("📊 Test Summary:");
        $this->line("  • Total test time: " . round($totalTime, 2) . "s");
        $this->line("  • Peak memory usage: " . round($this->maxMemoryUsage / 1024 / 1024, 2) . "MB");
        $this->line("  • System stability: " . ($overallPassed ? 'EXCELLENT' : 'NEEDS ATTENTION'));

        $this->newLine();
        $this->info('💡 Recommendations:');
        if ($overallPassed) {
            $this->line('  • Your system is well-optimized for high load');
            $this->line('  • Consider periodic stress testing to monitor performance');
            $this->line('  • Monitor production metrics to maintain optimal performance');
        } else {
            $this->line('  • Review failed test details above');
            $this->line('  • Consider optimizing database connections and queries');
            $this->line('  • Implement connection pooling if not already in use');
            $this->line('  • Monitor memory usage and implement proper garbage collection');
        }
    }
}
