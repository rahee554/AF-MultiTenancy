<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ArtflowStudio\Tenancy\Services\CachedTenantResolver;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Test Cached Lookup Command
 * 
 * Tests the cached tenant lookup functionality to ensure
 * it's working properly and not hitting the database unnecessarily.
 */
class TestCachedLookupCommand extends Command
{
    protected $signature = 'tenancy:test-cached-lookup 
                          {--domain= : Specific domain to test}
                          {--clear-cache : Clear cache before testing}
                          {--warm-cache : Warm cache after testing}
                          {--benchmark : Run performance benchmarks}
                          {--verbose : Show detailed output}';

    protected $description = 'Test cached tenant lookup functionality and performance';

    protected $cachedResolver;

    public function __construct(CachedTenantResolver $cachedResolver)
    {
        parent::__construct();
        $this->cachedResolver = $cachedResolver;
    }

    public function handle()
    {
        $this->info('🔍 Testing Cached Tenant Lookup');
        $this->line('');

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->clearCache();
        }

        // Run tests
        $this->testCacheConfiguration();
        $this->testCacheFunctionality();
        
        if ($this->option('benchmark')) {
            $this->runPerformanceBenchmarks();
        }

        // Warm cache if requested
        if ($this->option('warm-cache')) {
            $this->warmCache();
        }

        $this->displaySummary();
    }

    /**
     * Test cache configuration
     */
    protected function testCacheConfiguration()
    {
        $this->info('⚙️  Testing Cache Configuration');

        $stats = $this->cachedResolver->getCacheStats();
        
        $this->line("   Cache Store: {$stats['cache_store']}");
        $this->line("   Cache TTL: {$stats['cache_ttl']} seconds");
        $this->line("   Cache Prefix: {$stats['cache_prefix']}");
        $this->line("   Enabled: " . ($stats['enabled'] ? 'Yes' : 'No'));
        $this->line("   Total Domains: {$stats['total_domains']}");
        $this->line("   Cached Domains: {$stats['cached_domains']}");

        if (!$stats['enabled']) {
            $this->warn('   ⚠️  Cached lookup is disabled! Enable it in config/tenancy.php');
        } else {
            $this->line('   ✅ Cache configuration looks good');
        }

        $this->line('');
    }

    /**
     * Test cache functionality
     */
    protected function testCacheFunctionality()
    {
        $this->info('🧪 Testing Cache Functionality');

        $testDomain = $this->option('domain') ?: $this->getTestDomain();
        
        if (!$testDomain) {
            $this->warn('   ⚠️  No test domain available. Create a tenant first.');
            return;
        }

        $this->line("   Testing with domain: {$testDomain}");

        // Test 1: Cold lookup (should hit database)
        $this->testColdLookup($testDomain);

        // Test 2: Warm lookup (should hit cache)
        $this->testWarmLookup($testDomain);

        // Test 3: Cache clearing
        $this->testCacheClearing($testDomain);

        $this->line('');
    }

    /**
     * Test cold lookup (no cache)
     */
    protected function testColdLookup(string $domain)
    {
        $this->line('   🧊 Testing Cold Lookup (no cache)...');

        // Clear cache for this domain
        $this->cachedResolver->clearCache($domain);

        // Count DB queries
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $startTime = microtime(true);
        $tenant = $this->cachedResolver->resolve($domain);
        $endTime = microtime(true);

        $responseTime = round(($endTime - $startTime) * 1000, 2);

        if ($tenant) {
            $this->line("      ✅ Tenant resolved: {$tenant->getTenantKey()}");
        } else {
            $this->line("      ❌ No tenant found for domain");
            return;
        }

        $this->line("      📊 Response time: {$responseTime}ms");
        $this->line("      📊 Database queries: {$queryCount}");

        if ($queryCount > 0) {
            $this->line("      ✅ Cold lookup correctly hit database");
        } else {
            $this->warn("      ⚠️  Expected database queries for cold lookup");
        }
    }

    /**
     * Test warm lookup (cached)
     */
    protected function testWarmLookup(string $domain)
    {
        $this->line('   🔥 Testing Warm Lookup (cached)...');

        // Count DB queries
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $startTime = microtime(true);
        $tenant = $this->cachedResolver->resolve($domain);
        $endTime = microtime(true);

        $responseTime = round(($endTime - $startTime) * 1000, 2);

        if ($tenant) {
            $this->line("      ✅ Tenant resolved: {$tenant->getTenantKey()}");
        } else {
            $this->line("      ❌ No tenant found for domain");
            return;
        }

        $this->line("      📊 Response time: {$responseTime}ms");
        $this->line("      📊 Database queries: {$queryCount}");

        if ($queryCount === 0) {
            $this->line("      ✅ Warm lookup correctly used cache (no DB queries)");
        } else {
            $this->warn("      ⚠️  Warm lookup hit database unexpectedly");
        }
    }

    /**
     * Test cache clearing
     */
    protected function testCacheClearing(string $domain)
    {
        $this->line('   🧹 Testing Cache Clearing...');

        $cleared = $this->cachedResolver->clearCache($domain);
        
        if ($cleared) {
            $this->line("      ✅ Cache cleared successfully");
            
            // Verify it's actually cleared by doing another lookup
            $queryCount = 0;
            DB::listen(function () use (&$queryCount) {
                $queryCount++;
            });

            $this->cachedResolver->resolve($domain);

            if ($queryCount > 0) {
                $this->line("      ✅ Cache clearing verified (subsequent lookup hit DB)");
            } else {
                $this->warn("      ⚠️  Cache may not have been properly cleared");
            }
        } else {
            $this->warn("      ❌ Failed to clear cache");
        }
    }

    /**
     * Run performance benchmarks
     */
    protected function runPerformanceBenchmarks()
    {
        $this->info('⚡ Running Performance Benchmarks');

        $testDomain = $this->option('domain') ?: $this->getTestDomain();
        
        if (!$testDomain) {
            $this->warn('   ⚠️  No test domain available for benchmarks');
            return;
        }

        $iterations = 100;

        // Benchmark cold lookups
        $this->line("   🧊 Benchmarking Cold Lookups ({$iterations} iterations)...");
        $coldTimes = $this->benchmarkLookups($testDomain, $iterations, true);
        $avgColdTime = array_sum($coldTimes) / count($coldTimes);
        $this->line("      📊 Average cold lookup: " . round($avgColdTime, 2) . "ms");

        // Benchmark warm lookups
        $this->line("   🔥 Benchmarking Warm Lookups ({$iterations} iterations)...");
        $warmTimes = $this->benchmarkLookups($testDomain, $iterations, false);
        $avgWarmTime = array_sum($warmTimes) / count($warmTimes);
        $this->line("      📊 Average warm lookup: " . round($avgWarmTime, 2) . "ms");

        // Calculate improvement
        $improvement = (($avgColdTime - $avgWarmTime) / $avgColdTime) * 100;
        $this->line("      🚀 Cache improves performance by " . round($improvement, 1) . "%");

        $this->line('');
    }

    /**
     * Benchmark lookups
     */
    protected function benchmarkLookups(string $domain, int $iterations, bool $clearCacheEach): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            if ($clearCacheEach) {
                $this->cachedResolver->clearCache($domain);
            }

            $startTime = microtime(true);
            $this->cachedResolver->resolve($domain);
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        return $times;
    }

    /**
     * Clear cache
     */
    protected function clearCache()
    {
        $this->info('🧹 Clearing Tenant Lookup Cache');
        
        $cleared = $this->cachedResolver->clearAllCache();
        
        if ($cleared) {
            $this->line('   ✅ Cache cleared successfully');
        } else {
            $this->warn('   ⚠️  Cache clearing may not be fully supported for this cache store');
        }
        
        $this->line('');
    }

    /**
     * Warm cache
     */
    protected function warmCache()
    {
        $this->info('🔥 Warming Tenant Lookup Cache');
        
        $warmedCount = $this->cachedResolver->warmCache();
        
        $this->line("   ✅ Warmed cache for {$warmedCount} domains");
        $this->line('');
    }

    /**
     * Get a test domain
     */
    protected function getTestDomain(): ?string
    {
        try {
            $domain = Domain::first();
            return $domain ? $domain->domain : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Display summary
     */
    protected function displaySummary()
    {
        $this->info('📋 Summary');
        
        $stats = $this->cachedResolver->getCacheStats();
        
        if ($stats['enabled']) {
            $this->line('   ✅ Cached lookup is enabled and working');
            $this->line("   📊 {$stats['cached_domains']} domains cached out of {$stats['total_domains']} total");
            
            if ($stats['cached_domains'] === 0 && $stats['total_domains'] > 0) {
                $this->warn('   💡 Consider running: php artisan tenancy:test-cached-lookup --warm-cache');
            }
        } else {
            $this->warn('   ⚠️  Cached lookup is disabled');
            $this->line('   💡 Enable it in config/tenancy.php: cached_lookup.enabled = true');
        }

        $this->line('');
        $this->info('🎉 Cached lookup testing completed!');
    }
}
