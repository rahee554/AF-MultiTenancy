<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Performance;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Exception;

class CachePerformanceTestCommand extends Command
{
    protected $signature = 'tenancy:test-cache-performance 
                            {--tenants=5 : Number of tenants}
                            {--operations=1000 : Number of cache operations per test}
                            {--key-size=small : Cache key size (small|medium|large)}';

    protected $description = '⚡ Test cache performance across tenants';

    protected array $results = [];

    public function handle(): int
    {
        $this->displayHeader();

        $tenants = Tenant::take($this->option('tenants'))->get();

        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants found. Create test tenants first.');
            return 1;
        }

        // Test 1: Cache Write Performance
        $this->info('🔍 Test 1: Cache Write Performance');
        $this->testCacheWrites($tenants);
        $this->newLine();

        // Test 2: Cache Read Performance
        $this->info('🔍 Test 2: Cache Read Performance');
        $this->testCacheReads($tenants);
        $this->newLine();

        // Test 3: Cache Miss Performance
        $this->info('🔍 Test 3: Cache Miss Performance');
        $this->testCacheMisses($tenants);
        $this->newLine();

        // Test 4: Cache Delete Performance
        $this->info('🔍 Test 4: Cache Delete Performance');
        $this->testCacheDeletes($tenants);
        $this->newLine();

        // Test 5: Cache Isolation Test
        $this->info('🔍 Test 5: Cache Isolation Between Tenants');
        $this->testCacheIsolation($tenants);
        $this->newLine();

        // Test 6: Cache Prefix Test
        $this->info('🔍 Test 6: Cache Key Prefix Validation');
        $this->testCacheKeyPrefixes($tenants);
        $this->newLine();

        $this->displayResults();

        return 0;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║              ⚡ CACHE PERFORMANCE TEST                        ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $this->comment('📊 Configuration:');
        $this->line("   • Tenants: {$this->option('tenants')}");
        $this->line("   • Operations: {$this->option('operations')}");
        $this->line("   • Key Size: {$this->option('key-size')}");
        $this->line("   • Cache Driver: " . config('cache.default'));
        $this->newLine();
    }

    private function testCacheWrites(iterable $tenants): void
    {
        $operations = $this->option('operations');
        $times = [];
        $failedWrites = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $start = microtime(true);
            
            for ($i = 0; $i < $operations; $i++) {
                try {
                    $key = "test_write_{$i}";
                    $value = $this->generateValue();
                    Cache::put($key, $value, 3600);
                } catch (Exception $e) {
                    $failedWrites++;
                }
            }
            
            $duration = (microtime(true) - $start) * 1000;
            $times[] = $duration;
            
            tenancy()->end();
        }

        $totalTime = array_sum($times);
        $totalOperations = $operations * count($tenants);

        $this->results['cache_writes'] = [
            'status' => $failedWrites === 0 ? 'PASSED' : 'FAILED',
            'total_operations' => $totalOperations,
            'failed_writes' => $failedWrites,
            'total_time' => round($totalTime, 2) . 'ms',
            'avg_time_per_tenant' => round($totalTime / count($tenants), 2) . 'ms',
            'avg_time_per_operation' => round($totalTime / $totalOperations, 4) . 'ms',
            'writes_per_second' => round($totalOperations / ($totalTime / 1000), 2),
        ];

        $this->line("   ✅ Total Operations: " . number_format($totalOperations));
        $this->line("   ✅ Writes/Second: " . number_format(round($totalOperations / ($totalTime / 1000), 2)));
        $this->line("   ✅ Avg Time: " . round($totalTime / $totalOperations, 4) . 'ms');
        
        if ($failedWrites > 0) {
            $this->error("   ❌ Failed Writes: {$failedWrites}");
        }
    }

    private function testCacheReads(iterable $tenants): void
    {
        $operations = $this->option('operations');
        $times = [];
        $hits = 0;
        $misses = 0;

        // First, populate cache
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            for ($i = 0; $i < $operations; $i++) {
                Cache::put("test_read_{$i}", $this->generateValue(), 3600);
            }
            tenancy()->end();
        }

        // Now test reads
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $start = microtime(true);
            
            for ($i = 0; $i < $operations; $i++) {
                $value = Cache::get("test_read_{$i}");
                if ($value !== null) {
                    $hits++;
                } else {
                    $misses++;
                }
            }
            
            $duration = (microtime(true) - $start) * 1000;
            $times[] = $duration;
            
            tenancy()->end();
        }

        $totalTime = array_sum($times);
        $totalOperations = $operations * count($tenants);
        $hitRate = round(($hits / $totalOperations) * 100, 2);

        $this->results['cache_reads'] = [
            'status' => 'PASSED',
            'total_operations' => $totalOperations,
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'hit_rate' => $hitRate . '%',
            'total_time' => round($totalTime, 2) . 'ms',
            'avg_time_per_operation' => round($totalTime / $totalOperations, 4) . 'ms',
            'reads_per_second' => round($totalOperations / ($totalTime / 1000), 2),
        ];

        $this->line("   ✅ Cache Hits: " . number_format($hits) . " ({$hitRate}%)");
        $this->line("   ✅ Reads/Second: " . number_format(round($totalOperations / ($totalTime / 1000), 2)));
        $this->line("   ✅ Avg Time: " . round($totalTime / $totalOperations, 4) . 'ms');
    }

    private function testCacheMisses(iterable $tenants): void
    {
        $operations = $this->option('operations');
        $times = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $start = microtime(true);
            
            for ($i = 0; $i < $operations; $i++) {
                Cache::get("nonexistent_key_{$i}");
            }
            
            $duration = (microtime(true) - $start) * 1000;
            $times[] = $duration;
            
            tenancy()->end();
        }

        $totalTime = array_sum($times);
        $totalOperations = $operations * count($tenants);

        $this->results['cache_misses'] = [
            'status' => 'PASSED',
            'total_operations' => $totalOperations,
            'total_time' => round($totalTime, 2) . 'ms',
            'avg_time_per_operation' => round($totalTime / $totalOperations, 4) . 'ms',
            'misses_per_second' => round($totalOperations / ($totalTime / 1000), 2),
        ];

        $this->line("   ✅ Total Misses: " . number_format($totalOperations));
        $this->line("   ✅ Misses/Second: " . number_format(round($totalOperations / ($totalTime / 1000), 2)));
        $this->line("   ✅ Avg Time: " . round($totalTime / $totalOperations, 4) . 'ms');
    }

    private function testCacheDeletes(iterable $tenants): void
    {
        $operations = $this->option('operations');
        $times = [];

        // First, populate cache
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            for ($i = 0; $i < $operations; $i++) {
                Cache::put("test_delete_{$i}", $this->generateValue(), 3600);
            }
            tenancy()->end();
        }

        // Now test deletes
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            $start = microtime(true);
            
            for ($i = 0; $i < $operations; $i++) {
                Cache::forget("test_delete_{$i}");
            }
            
            $duration = (microtime(true) - $start) * 1000;
            $times[] = $duration;
            
            tenancy()->end();
        }

        $totalTime = array_sum($times);
        $totalOperations = $operations * count($tenants);

        $this->results['cache_deletes'] = [
            'status' => 'PASSED',
            'total_operations' => $totalOperations,
            'total_time' => round($totalTime, 2) . 'ms',
            'avg_time_per_operation' => round($totalTime / $totalOperations, 4) . 'ms',
            'deletes_per_second' => round($totalOperations / ($totalTime / 1000), 2),
        ];

        $this->line("   ✅ Total Deletes: " . number_format($totalOperations));
        $this->line("   ✅ Deletes/Second: " . number_format(round($totalOperations / ($totalTime / 1000), 2)));
        $this->line("   ✅ Avg Time: " . round($totalTime / $totalOperations, 4) . 'ms');
    }

    private function testCacheIsolation(iterable $tenants): void
    {
        $testKey = 'isolation_test_key';
        $isolationPassed = true;
        $tenantValues = [];

        // Write unique values for each tenant
        foreach ($tenants as $index => $tenant) {
            tenancy()->initialize($tenant);
            $value = "tenant_{$tenant->id}_value_{$index}";
            Cache::put($testKey, $value, 3600);
            $tenantValues[$tenant->id] = $value;
            tenancy()->end();
        }

        // Read back and verify isolation
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $retrievedValue = Cache::get($testKey);
            
            if ($retrievedValue !== $tenantValues[$tenant->id]) {
                $isolationPassed = false;
                $this->error("   ❌ Isolation breach for tenant {$tenant->id}");
            }
            
            tenancy()->end();
        }

        $this->results['cache_isolation'] = [
            'status' => $isolationPassed ? 'PASSED' : 'FAILED',
            'tenants_tested' => count($tenants),
            'isolation_maintained' => $isolationPassed,
            'message' => $isolationPassed 
                ? '✅ Cache properly isolated between tenants'
                : '❌ Cache isolation breach detected',
        ];

        if ($isolationPassed) {
            $this->line("   ✅ Cache properly isolated between " . count($tenants) . " tenants");
        } else {
            $this->error("   ❌ Cache isolation breach detected!");
        }
    }

    private function testCacheKeyPrefixes(iterable $tenants): void
    {
        $testKey = 'prefix_test_key';
        $prefixesFound = [];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            
            Cache::put($testKey, 'test_value', 3600);
            
            // Try to inspect the actual cache key (driver-dependent)
            $cacheStore = Cache::getStore();
            $prefix = Cache::getPrefix();
            
            $prefixesFound[$tenant->id] = $prefix;
            
            tenancy()->end();
        }

        $uniquePrefixes = count(array_unique($prefixesFound));
        $hasPrefixes = !in_array('', $prefixesFound, true);

        $this->results['cache_prefixes'] = [
            'status' => $hasPrefixes ? 'PASSED' : 'WARNING',
            'tenants_tested' => count($tenants),
            'unique_prefixes' => $uniquePrefixes,
            'prefixes_enabled' => $hasPrefixes,
            'message' => $hasPrefixes
                ? '✅ Cache key prefixes properly configured'
                : '⚠️  No cache key prefixes detected (may cause isolation issues)',
        ];

        if ($hasPrefixes) {
            $this->line("   ✅ Cache prefixes enabled: {$uniquePrefixes} unique prefixes");
        } else {
            $this->warn("   ⚠️  No cache key prefixes detected");
            $this->comment("   💡 Consider enabling cache key prefixes for better isolation");
        }
    }

    private function generateValue(): string
    {
        $keySize = $this->option('key-size');
        
        return match($keySize) {
            'small' => str_repeat('x', 100),
            'medium' => str_repeat('x', 1000),
            'large' => str_repeat('x', 10000),
            default => str_repeat('x', 100),
        };
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                  📊 FINAL RESULTS                             ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $warnings = 0;

        foreach ($this->results as $result) {
            $status = $result['status'];
            if ($status === 'PASSED') {
                $passed++;
            } elseif ($status === 'FAILED') {
                $failed++;
            } elseif ($status === 'WARNING') {
                $warnings++;
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tests', count($this->results)],
                ['Passed', "✅ {$passed}"],
                ['Warnings', $warnings > 0 ? "⚠️  {$warnings}" : "✅ 0"],
                ['Failed', $failed > 0 ? "❌ {$failed}" : "✅ 0"],
            ]
        );

        $this->newLine();

        // Performance Summary
        if (isset($this->results['cache_writes']) && isset($this->results['cache_reads'])) {
            $this->info('⚡ Performance Summary:');
            $this->newLine();

            $summaryData = [
                ['Writes/Second', $this->results['cache_writes']['writes_per_second']],
                ['Reads/Second', $this->results['cache_reads']['reads_per_second']],
                ['Deletes/Second', $this->results['cache_deletes']['deletes_per_second']],
                ['Hit Rate', $this->results['cache_reads']['hit_rate']],
            ];

            $this->table(['Operation', 'Rate'], $summaryData);
        }

        $this->newLine();

        // Recommendations
        if ($warnings > 0 || $failed > 0) {
            $this->warn('📋 Recommendations:');
            $this->newLine();

            if (isset($this->results['cache_isolation']) && !$this->results['cache_isolation']['isolation_maintained']) {
                $this->comment('   • Fix cache isolation issues');
                $this->comment('   • Ensure tenant context is properly set before cache operations');
            }

            if (isset($this->results['cache_prefixes']) && !$this->results['cache_prefixes']['prefixes_enabled']) {
                $this->comment('   • Enable cache key prefixes for better tenant isolation');
                $this->comment('   • Configure cache prefix in tenancy configuration');
            }

            $this->newLine();
        } else {
            $this->info('🎉 All cache performance tests passed!');
        }

        $this->newLine();
    }
}
