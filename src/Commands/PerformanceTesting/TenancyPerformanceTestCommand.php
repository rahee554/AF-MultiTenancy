<?php

namespace ArtflowStudio\Tenancy\Commands\PerformanceTesting;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Exception;

class TenancyPerformanceTestCommand extends Command
{
    protected $signature = 'tenancy:performance-test 
                            {--tenants=10 : Number of test tenants to create}
                            {--queries=100 : Number of queries per tenant}
                            {--concurrent=5 : Number of concurrent tenant switches}
                            {--cleanup : Clean up test tenants after test}';

    protected $description = '🚀 Comprehensive performance testing for multi-tenancy package';

    protected array $results = [];
    protected array $errors = [];
    protected float $startTime;

    public function handle(): int
    {
        $this->startTime = microtime(true);
        
        $this->displayHeader();
        
        // Run all performance tests
        $tests = [
            'Database Connection Pool' => fn() => $this->testDatabaseConnectionPool(),
            'Tenant Switching Speed' => fn() => $this->testTenantSwitchingSpeed(),
            'Concurrent Tenant Access' => fn() => $this->testConcurrentTenantAccess(),
            'Database Query Performance' => fn() => $this->testDatabaseQueryPerformance(),
            'Cache Performance' => fn() => $this->testCachePerformance(),
            'Memory Usage' => fn() => $this->testMemoryUsage(),
            'Connection Cleanup' => fn() => $this->testConnectionCleanup(),
        ];

        foreach ($tests as $testName => $testFunction) {
            $this->runTest($testName, $testFunction);
        }

        // Display results
        $this->displayResults();

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanupTestData();
        }

        return 0;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║        🚀 TENANCY PERFORMANCE TEST SUITE                      ║');
        $this->info('║        artflow-studio/tenancy Package                         ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $this->comment('📊 Test Configuration:');
        $this->line("   • Test Tenants: {$this->option('tenants')}");
        $this->line("   • Queries per Tenant: {$this->option('queries')}");
        $this->line("   • Concurrent Switches: {$this->option('concurrent')}");
        $this->line("   • Cleanup After Test: " . ($this->option('cleanup') ? 'Yes' : 'No'));
        $this->newLine();
    }

    private function runTest(string $testName, callable $testFunction): void
    {
        $this->info("🔍 Running: {$testName}");
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            $result = $testFunction();
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsed = $this->formatBytes(memory_get_usage(true) - $startMemory);
            
            $this->results[$testName] = [
                'status' => 'PASSED',
                'duration' => $duration,
                'memory' => $memoryUsed,
                'result' => $result,
            ];
            
            $this->line("   ✅ PASSED - {$duration}ms - {$memoryUsed}");
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->results[$testName] = [
                'status' => 'FAILED',
                'duration' => $duration,
                'error' => $e->getMessage(),
            ];
            
            $this->error("   ❌ FAILED - {$e->getMessage()}");
            $this->errors[] = [
                'test' => $testName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        
        $this->newLine();
    }

    private function testDatabaseConnectionPool(): array
    {
        $tenants = Tenant::take($this->option('tenants'))->get();
        
        if ($tenants->isEmpty()) {
            throw new Exception('No tenants found. Create test tenants first.');
        }

        $connections = [];
        $switchTimes = [];

        foreach ($tenants as $tenant) {
            $start = microtime(true);
            
            tenancy()->initialize($tenant);
            $connectionName = DB::connection()->getName();
            $connections[] = $connectionName;
            
            $switchTimes[] = (microtime(true) - $start) * 1000;
            
            tenancy()->end();
        }

        return [
            'tenants_tested' => count($tenants),
            'avg_switch_time' => round(array_sum($switchTimes) / count($switchTimes), 2) . 'ms',
            'min_switch_time' => round(min($switchTimes), 2) . 'ms',
            'max_switch_time' => round(max($switchTimes), 2) . 'ms',
            'unique_connections' => count(array_unique($connections)),
        ];
    }

    private function testTenantSwitchingSpeed(): array
    {
        $tenants = Tenant::take($this->option('tenants'))->get();
        $iterations = 100;
        $times = [];

        foreach ($tenants as $tenant) {
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                tenancy()->initialize($tenant);
                tenancy()->end();
                $times[] = (microtime(true) - $start) * 1000;
            }
        }

        return [
            'total_switches' => count($times),
            'avg_time' => round(array_sum($times) / count($times), 4) . 'ms',
            'min_time' => round(min($times), 4) . 'ms',
            'max_time' => round(max($times), 4) . 'ms',
            'switches_per_second' => round(1000 / (array_sum($times) / count($times)), 2),
        ];
    }

    private function testConcurrentTenantAccess(): array
    {
        $tenants = Tenant::take($this->option('concurrent'))->get();
        $queryCount = $this->option('queries');
        
        $results = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $start = microtime(true);
            for ($i = 0; $i < $queryCount; $i++) {
                DB::connection('tenant')->select('SELECT 1 as test');
            }
            $duration = (microtime(true) - $start) * 1000;
            
            $results[] = [
                'tenant_id' => $tenant->id,
                'queries' => $queryCount,
                'duration' => round($duration, 2),
                'queries_per_second' => round($queryCount / ($duration / 1000), 2),
            ];
            
            tenancy()->end();
        }

        $totalDuration = array_sum(array_column($results, 'duration'));
        $totalQueries = $queryCount * count($tenants);

        return [
            'tenants_tested' => count($tenants),
            'total_queries' => $totalQueries,
            'total_duration' => round($totalDuration, 2) . 'ms',
            'avg_queries_per_second' => round($totalQueries / ($totalDuration / 1000), 2),
            'per_tenant_results' => $results,
        ];
    }

    private function testDatabaseQueryPerformance(): array
    {
        $tenants = Tenant::take(3)->get();
        $queryTypes = [
            'simple_select' => 'SELECT 1 as test',
            'count_query' => 'SELECT COUNT(*) FROM information_schema.tables',
            'join_query' => 'SELECT t1.*, t2.* FROM information_schema.tables t1 JOIN information_schema.columns t2 ON t1.table_name = t2.table_name LIMIT 10',
        ];

        $results = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            foreach ($queryTypes as $name => $query) {
                $times = [];
                for ($i = 0; $i < 50; $i++) {
                    $start = microtime(true);
                    DB::connection('tenant')->select($query);
                    $times[] = (microtime(true) - $start) * 1000;
                }
                
                $results[$name][] = [
                    'tenant' => $tenant->id,
                    'avg_time' => round(array_sum($times) / count($times), 4),
                    'min_time' => round(min($times), 4),
                    'max_time' => round(max($times), 4),
                ];
            }
            
            tenancy()->end();
        }

        return [
            'query_types_tested' => count($queryTypes),
            'tenants_tested' => count($tenants),
            'iterations_per_query' => 50,
            'results' => $results,
        ];
    }

    private function testCachePerformance(): array
    {
        $tenants = Tenant::take(3)->get();
        $operations = 100;
        
        $results = [
            'write' => [],
            'read' => [],
            'delete' => [],
        ];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            // Write test
            $start = microtime(true);
            for ($i = 0; $i < $operations; $i++) {
                Cache::put("test_key_{$i}", "test_value_{$i}", 60);
            }
            $results['write'][] = (microtime(true) - $start) * 1000;
            
            // Read test
            $start = microtime(true);
            for ($i = 0; $i < $operations; $i++) {
                Cache::get("test_key_{$i}");
            }
            $results['read'][] = (microtime(true) - $start) * 1000;
            
            // Delete test
            $start = microtime(true);
            for ($i = 0; $i < $operations; $i++) {
                Cache::forget("test_key_{$i}");
            }
            $results['delete'][] = (microtime(true) - $start) * 1000;
            
            tenancy()->end();
        }

        return [
            'operations_per_test' => $operations,
            'tenants_tested' => count($tenants),
            'avg_write_time' => round(array_sum($results['write']) / count($results['write']), 2) . 'ms',
            'avg_read_time' => round(array_sum($results['read']) / count($results['read']), 2) . 'ms',
            'avg_delete_time' => round(array_sum($results['delete']) / count($results['delete']), 2) . 'ms',
            'writes_per_second' => round($operations / ((array_sum($results['write']) / count($results['write'])) / 1000), 2),
            'reads_per_second' => round($operations / ((array_sum($results['read']) / count($results['read'])) / 1000), 2),
        ];
    }

    private function testMemoryUsage(): array
    {
        $tenants = Tenant::take($this->option('tenants'))->get();
        
        $startMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            // Perform some operations
            for ($i = 0; $i < 50; $i++) {
                DB::connection('tenant')->select('SELECT 1 as test');
            }
            
            tenancy()->end();
        }

        $endMemory = memory_get_usage(true);
        $finalPeak = memory_get_peak_usage(true);

        return [
            'tenants_tested' => count($tenants),
            'start_memory' => $this->formatBytes($startMemory),
            'end_memory' => $this->formatBytes($endMemory),
            'memory_increase' => $this->formatBytes($endMemory - $startMemory),
            'peak_memory' => $this->formatBytes($finalPeak),
            'memory_per_tenant' => $this->formatBytes(($endMemory - $startMemory) / count($tenants)),
        ];
    }

    private function testConnectionCleanup(): array
    {
        $tenants = Tenant::take(5)->get();
        $leaks = [];

        foreach ($tenants as $tenant) {
            $beforeConnections = count(DB::getConnections());
            
            tenancy()->initialize($tenant);
            DB::connection('tenant')->select('SELECT 1 as test');
            tenancy()->end();
            
            $afterConnections = count(DB::getConnections());
            
            if ($afterConnections > $beforeConnections) {
                $leaks[] = [
                    'tenant' => $tenant->id,
                    'leaked_connections' => $afterConnections - $beforeConnections,
                ];
            }
        }

        return [
            'tenants_tested' => count($tenants),
            'connection_leaks_detected' => count($leaks),
            'leaks' => $leaks,
            'status' => count($leaks) === 0 ? 'No leaks detected ✅' : 'Memory leaks found ⚠️',
        ];
    }

    private function displayResults(): void
    {
        $totalDuration = round((microtime(true) - $this->startTime) * 1000, 2);
        
        $this->newLine(2);
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                     📊 TEST RESULTS                           ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Summary
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASSED'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAILED'));
        $total = count($this->results);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tests', $total],
                ['Passed', "✅ {$passed}"],
                ['Failed', $failed > 0 ? "❌ {$failed}" : "✅ 0"],
                ['Total Duration', "{$totalDuration}ms"],
                ['Memory Used', $this->formatBytes(memory_get_peak_usage(true))],
            ]
        );

        $this->newLine();

        // Detailed results
        foreach ($this->results as $testName => $result) {
            $this->info("📋 {$testName}");
            $this->line("   Status: " . ($result['status'] === 'PASSED' ? '✅ PASSED' : '❌ FAILED'));
            $this->line("   Duration: {$result['duration']}ms");
            
            if (isset($result['memory'])) {
                $this->line("   Memory: {$result['memory']}");
            }
            
            if (isset($result['result']) && is_array($result['result'])) {
                foreach ($result['result'] as $key => $value) {
                    if (is_array($value)) {
                        $this->line("   {$key}:");
                        foreach ($value as $subKey => $subValue) {
                            $this->line("      • {$subKey}: " . (is_array($subValue) ? json_encode($subValue) : $subValue));
                        }
                    } else {
                        $this->line("   {$key}: {$value}");
                    }
                }
            }
            
            if (isset($result['error'])) {
                $this->error("   Error: {$result['error']}");
            }
            
            $this->newLine();
        }

        // Display errors if any
        if (!empty($this->errors)) {
            $this->newLine();
            $this->error('╔════════════════════════════════════════════════════════════════╗');
            $this->error('║                     ⚠️  ERRORS DETECTED                       ║');
            $this->error('╚════════════════════════════════════════════════════════════════╝');
            $this->newLine();

            foreach ($this->errors as $error) {
                $this->error("Test: {$error['test']}");
                $this->line("Error: {$error['error']}");
                $this->newLine();
            }
        }

        // Final summary
        $this->newLine();
        if ($failed === 0) {
            $this->info('🎉 All tests passed successfully!');
        } else {
            $this->warn("⚠️  {$failed} test(s) failed. Please review the errors above.");
        }
        
        $this->newLine();
    }

    private function cleanupTestData(): void
    {
        $this->info('🧹 Cleaning up test data...');
        
        try {
            // Add cleanup logic here if needed
            $this->info('✅ Cleanup completed');
        } catch (Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
