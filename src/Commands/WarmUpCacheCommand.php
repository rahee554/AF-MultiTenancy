<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantContextCache;

class WarmUpCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:cache:warm
                            {--clear : Clear all caches before warming up}
                            {--stats : Show cache statistics after warming}';

    /**
     * The console command description.
     */
    protected $description = 'Warm up the multi-layer tenant cache system for optimal performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cache = new TenantContextCache();
        
        $this->info('🔥 Warming up multi-layer tenant cache...');
        
        // Clear caches if requested
        if ($this->option('clear')) {
            $this->line('🧹 Clearing existing caches...');
            TenantContextCache::clearAll();
            $this->info('✅ All caches cleared');
        }
        
        // Warm up the cache
        $startTime = microtime(true);
        $warmedCount = $cache->warmUpCache();
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if ($warmedCount > 0) {
            $this->info("✅ Successfully warmed up cache for {$warmedCount} tenant domains");
            $this->line("⏱️  Cache warming completed in {$duration}ms");
        } else {
            $this->warn('⚠️  No active tenants found to cache');
        }
        
        // Show statistics if requested
        if ($this->option('stats')) {
            $this->showCacheStats($cache);
        }
        
        return 0;
    }
    
    /**
     * Show cache statistics
     */
    protected function showCacheStats(TenantContextCache $cache): void
    {
        $this->newLine();
        $this->info('📊 Cache Statistics:');
        
        $stats = TenantContextCache::getStats();
        
        $this->table(['Cache Layer', 'Size', 'Details'], [
            ['Memory Cache', $stats['memory_cache_size'], 'In-memory (current request)'],
            ['Redis Cache', $stats['redis_cache_size'] ?? 'N/A', isset($stats['redis_cache_error']) ? 'Error: ' . $stats['redis_cache_error'] : 'Persistent cache'],
            ['Legacy Cache', $stats['tenant_cache_size'], 'Backward compatibility'],
            ['Connection Cache', $stats['connection_cache_size'], 'Database connections'],
        ]);
        
        if (!empty($stats['memory_cache_keys'])) {
            $this->line('Memory cached domains: ' . implode(', ', $stats['memory_cache_keys']));
        }
    }
}
