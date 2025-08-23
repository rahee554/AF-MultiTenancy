<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ArtflowStudio\Tenancy\Services\RedisHelper;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Arr;
use ArtflowStudio\Tenancy\Models\Tenant;

class TestRedisCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:test-redis {--detailed : Show detailed Redis and cache info}';

    /**
     * The console command description.
     */
    protected $description = 'Check Redis availability, cache configuration and tenant-scoped caching behavior';

    public function handle(): int
    {
        $this->info('🔎 Testing Redis & Cache configuration...');

        // Basic cache driver
        $cacheDefault = config('cache.default');
        $this->line("• Cache default driver: {$cacheDefault}");

        // Redis connection config
        $redisConfig = config('database.redis.default', []);
        if (!empty($redisConfig) && is_array($redisConfig)) {
            $this->line('• Redis connection settings:');
            $rows = [];
            foreach ($redisConfig as $k => $v) {
                $rows[] = [$k, is_scalar($v) ? (string)$v : json_encode($v)];
            }
            $this->table(['key', 'value'], $rows);
        } else {
            $this->line('• Redis connection not configured in database.redis.default');
        }

        // Is Redis available according to the app?
        $redisAvailable = $this->isRedisAvailable();
        $this->line('• Redis available: ' . ($redisAvailable ? '✅ Yes' : '❌ No'));

        // Show tenancy cache settings (if present)
        $tenancyCacheStore = env('TENANCY_CACHE_STORE', config('cache.default'));
        $tenancyCachedLookup = env('TENANCY_CACHED_LOOKUP', false);
        $this->line("• TENANCY_CACHE_STORE: {$tenancyCacheStore}");
        $this->line('• TENANCY_CACHED_LOOKUP: ' . ($tenancyCachedLookup ? 'enabled' : 'disabled'));

        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('🔧 Detailed cache stores config:');
            $stores = config('cache.stores', []);
            $rows = [];
            foreach ($stores as $name => $cfg) {
                $rows[] = [$name, Arr::get($cfg, 'driver'), json_encode($cfg)];
            }
            $this->table(['store', 'driver', 'config'], $rows);
        }

        // Quick ping test
        if ($redisAvailable) {
            try {
                $this->line('• Pinging Redis...');
                $conn = Redis::connection();
                $pong = $conn->ping();
                $this->line("   → ping response: " . trim((string)$pong));
            } catch (\Throwable $e) {
                $this->error('   Redis ping failed: ' . $e->getMessage());
            }
        }

        // Tenant-scoped caching example (non-destructive)
        $this->newLine();
        $this->info('🏷️  Tenant-scoped caching sample (first 3 tenants)');
        $tenants = Tenant::with('domains')->limit(3)->get();
        if ($tenants->isEmpty()) {
            $this->line('   No tenants found to demonstrate tenant caching.');
            return $redisAvailable ? 0 : 1;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant) {
                    $storeName = env('TENANCY_CACHE_STORE', config('cache.default'));
                    $store = Cache::store($storeName);

                    $sampleKey = "tenant:{$tenant->id}:sample_key";
                    $store->put($sampleKey, 'ok', 60);
                    $value = $store->get($sampleKey);

                    $domain = $tenant->domains->first()?->domain ?? 'no-domain';
                    $this->line("   • Tenant {$tenant->id} ({$tenant->name} @ {$domain}) -> cache[{$storeName}] -> sample_key={$value}");
                });
            } catch (\Throwable $e) {
                $this->error("   • Tenant {$tenant->id} error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('✅ Redis/cache test completed. Review above output for issues.');

        return $redisAvailable ? 0 : 1;
    }

    protected function isRedisAvailable(): bool
    {
        try {
            // Use RedisHelper if available
            if (class_exists(RedisHelper::class)) {
                return RedisHelper::isAvailable();
            }

            // Fallback to direct check
            if (!extension_loaded('redis')) {
                return false;
            }

            // Test Redis connection using Laravel's Redis facade
            $conn = Redis::connection();
            $pong = $conn->ping();
            
            // Check for valid ping response
            return $pong === '+PONG' || $pong === 'PONG' || $pong === 1 || $pong === true;
        } catch (\Throwable $e) {
            // Log the error for debugging
            $this->line("   Redis connection error: {$e->getMessage()}");
            return false;
        }
    }
}
