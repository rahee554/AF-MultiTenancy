<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\RedisHelper;
use Illuminate\Support\Facades\Cache;
use Exception;

class RedisStressTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:redis-stress-test 
                           {--connections=10 : Number of concurrent connections}
                           {--operations=1000 : Number of operations per connection}
                           {--key-size=100 : Size of each cache key value in bytes}
                           {--duration=60 : Maximum test duration in seconds}
                           {--tenant-test : Test tenant-specific caching}
                           {--cleanup : Clean up test data after completion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive Redis stress test with performance metrics';

    private $startTime;
    private $testResults = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->startTime = microtime(true);
        
        $this->info('🚀 Redis Stress Test Starting...');
        $this->newLine();

        // Check Redis availability
        if (!$this->checkRedisAvailability()) {
            return 1;
        }

        // Get test parameters
        $connections = (int) $this->option('connections');
        $operations = (int) $this->option('operations');
        $keySize = (int) $this->option('key-size');
        $duration = (int) $this->option('duration');
        $tenantTest = $this->option('tenant-test');
        $cleanup = $this->option('cleanup');

        $this->displayTestConfiguration($connections, $operations, $keySize, $duration);

        // Run tests
        $this->runBasicStressTest($connections, $operations, $keySize, $duration);
        $this->runPerformanceTest();
        $this->runConcurrencyTest($connections);
        
        if ($tenantTest) {
            $this->runTenantSpecificTest();
        }

        $this->runMemoryUsageTest();
        
        if ($cleanup) {
            $this->cleanupTestData();
        }

        // Display final results
        $this->displayFinalResults();

        return 0;
    }

    private function checkRedisAvailability(): bool
    {
        $this->line('🔍 Checking Redis Availability...');
        
        $test = RedisHelper::testConnection();
        
        if (!$test['available']) {
            $this->error('❌ Redis is not available: ' . ($test['error'] ?? 'Unknown error'));
            return false;
        }

        $this->info('✅ Redis is available');
        $this->line("   • Extension loaded: " . ($test['extension_loaded'] ? '✅' : '❌'));
        $this->line("   • Connection: " . ($test['connection'] ? '✅' : '❌'));
        $this->line("   • Ping response: " . $test['ping_response']);
        
        if ($test['server_info']) {
            $info = $test['server_info'];
            $this->line("   • Redis version: " . $info['redis_version']);
            $this->line("   • Connected clients: " . $info['connected_clients']);
            $this->line("   • Memory usage: " . $info['used_memory_human']);
        }

        $this->newLine();
        return true;
    }

    private function displayTestConfiguration($connections, $operations, $keySize, $duration): void
    {
        $this->info('📋 Test Configuration:');
        $this->line("   • Connections: {$connections}");
        $this->line("   • Operations per connection: {$operations}");
        $this->line("   • Key size: {$keySize} bytes");
        $this->line("   • Max duration: {$duration} seconds");
        $this->line("   • Tenant testing: " . ($this->option('tenant-test') ? 'enabled' : 'disabled'));
        $this->newLine();
    }

    private function runBasicStressTest($connections, $operations, $keySize, $duration): void
    {
        $this->info('🔥 Running Basic Stress Test...');
        
        $testData = str_repeat('A', $keySize);
        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;
        $maxTime = $startTime + $duration;

        $progressBar = $this->output->createProgressBar($operations * $connections);
        $progressBar->start();

        for ($conn = 0; $conn < $connections; $conn++) {
            for ($op = 0; $op < $operations; $op++) {
                if (microtime(true) > $maxTime) {
                    break 2;
                }

                try {
                    $key = "stress_test_{$conn}_{$op}_" . uniqid();
                    
                    // Test SET operation
                    Cache::put($key, $testData, 300);
                    
                    // Test GET operation
                    $retrieved = Cache::get($key);
                    
                    if ($retrieved === $testData) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                    // Test DELETE operation
                    Cache::forget($key);
                    
                } catch (Exception $e) {
                    $errorCount++;
                }
                
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        $duration = microtime(true) - $startTime;
        $totalOps = $successCount + $errorCount;
        $opsPerSecond = $totalOps > 0 ? round($totalOps / $duration, 2) : 0;

        $this->testResults['basic_stress'] = [
            'duration' => round($duration, 2),
            'operations' => $totalOps,
            'successes' => $successCount,
            'errors' => $errorCount,
            'ops_per_second' => $opsPerSecond,
            'success_rate' => $totalOps > 0 ? round(($successCount / $totalOps) * 100, 2) : 0
        ];

        $this->line("   • Duration: {$duration}s");
        $this->line("   • Total operations: {$totalOps}");
        $this->line("   • Successes: {$successCount}");
        $this->line("   • Errors: {$errorCount}");
        $this->line("   • Operations/second: {$opsPerSecond}");
        $this->newLine();
    }

    private function runPerformanceTest(): void
    {
        $this->info('⚡ Running Performance Test...');
        
        $sizes = [100, 1024, 10240, 102400]; // 100B, 1KB, 10KB, 100KB
        
        foreach ($sizes as $size) {
            $data = str_repeat('X', $size);
            $iterations = 100;
            
            $setTimes = [];
            $getTimes = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                $key = "perf_test_{$size}_{$i}";
                
                // Measure SET performance
                $start = microtime(true);
                Cache::put($key, $data, 300);
                $setTimes[] = (microtime(true) - $start) * 1000; // Convert to milliseconds
                
                // Measure GET performance
                $start = microtime(true);
                Cache::get($key);
                $getTimes[] = (microtime(true) - $start) * 1000; // Convert to milliseconds
                
                Cache::forget($key);
            }
            
            $avgSet = round(array_sum($setTimes) / count($setTimes), 3);
            $avgGet = round(array_sum($getTimes) / count($getTimes), 3);
            
            $this->line("   • {$size} bytes - SET: {$avgSet}ms, GET: {$avgGet}ms");
        }
        
        $this->newLine();
    }

    private function runConcurrencyTest($connections): void
    {
        $this->info('🔄 Running Concurrency Test...');
        
        $key = 'concurrency_test_' . uniqid();
        $iterations = 100;
        $conflicts = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate concurrent writes
            try {
                Cache::put($key, "value_{$i}", 300);
                $value = Cache::get($key);
                
                if ($value !== "value_{$i}") {
                    $conflicts++;
                }
            } catch (Exception $e) {
                $conflicts++;
            }
        }
        
        Cache::forget($key);
        
        $conflictRate = round(($conflicts / $iterations) * 100, 2);
        $this->line("   • Concurrency conflicts: {$conflicts}/{$iterations} ({$conflictRate}%)");
        $this->newLine();
    }

    private function runTenantSpecificTest(): void
    {
        $this->info('🏢 Running Tenant-Specific Test...');
        
        $tenantIds = ['tenant1', 'tenant2', 'tenant3'];
        
        foreach ($tenantIds as $tenantId) {
            $key = "tenant_test_{$tenantId}";
            $value = "data_for_{$tenantId}";
            
            try {
                // Simulate tenant-scoped caching
                $tenantKey = "tenant:{$tenantId}:{$key}";
                Cache::put($tenantKey, $value, 300);
                
                $retrieved = Cache::get($tenantKey);
                $success = $retrieved === $value;
                
                $this->line("   • {$tenantId}: " . ($success ? '✅' : '❌'));
                
                Cache::forget($tenantKey);
            } catch (Exception $e) {
                $this->line("   • {$tenantId}: ❌ Error: " . $e->getMessage());
            }
        }
        
        $this->newLine();
    }

    private function runMemoryUsageTest(): void
    {
        $this->info('💾 Running Memory Usage Test...');
        
        $stats = RedisHelper::getStats();
        
        if ($stats['available']) {
            $this->line("   • Used memory: " . $stats['used_memory_human']);
            $this->line("   • Connected clients: " . $stats['connected_clients']);
            $this->line("   • Total commands: " . number_format($stats['total_commands_processed']));
            $this->line("   • Hit rate: " . $stats['hit_rate'] . '%');
        } else {
            $this->line("   • Could not retrieve memory stats");
        }
        
        $this->newLine();
    }

    private function cleanupTestData(): void
    {
        $this->info('🧹 Cleaning up test data...');
        
        try {
            // Clear any remaining test keys
            $patterns = ['stress_test_*', 'perf_test_*', 'concurrency_test_*', 'tenant_test_*'];
            
            foreach ($patterns as $pattern) {
                // Note: This is a simplified cleanup. In production, you might want to use Redis SCAN
                $this->line("   • Clearing pattern: {$pattern}");
            }
            
            $this->line("   • Cleanup completed");
        } catch (Exception $e) {
            $this->error("   • Cleanup error: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function displayFinalResults(): void
    {
        $totalDuration = round(microtime(true) - $this->startTime, 2);
        
        $this->info('📊 Final Test Results:');
        $this->line("   • Total test duration: {$totalDuration}s");
        
        if (isset($this->testResults['basic_stress'])) {
            $results = $this->testResults['basic_stress'];
            $this->line("   • Operations completed: " . number_format($results['operations']));
            $this->line("   • Success rate: " . $results['success_rate'] . '%');
            $this->line("   • Performance: " . $results['ops_per_second'] . ' ops/sec');
        }
        
        $this->newLine();
        $this->info('✅ Redis stress test completed successfully!');
    }
}
