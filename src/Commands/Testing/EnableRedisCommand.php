<?php

namespace ArtflowStudio\Tenancy\Commands\Testing;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\RedisHelper;
use ArtflowStudio\Tenancy\Services\TenancyCacheManager;

class EnableRedisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:enable-redis 
                           {--force : Force enable even if Redis is not available}
                           {--test : Test after enabling}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable Redis for ArtFlow Tenancy with one command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Enabling Redis for ArtFlow Tenancy');
        $this->newLine();

        // Check if Redis is available
        $available = RedisHelper::isAvailable();
        
        if (!$available && !$this->option('force')) {
            $this->error('❌ Redis is not available. Install Redis first with:');
            $this->line('   php artisan tenancy:install-redis --server --configure');
            $this->newLine();
            $this->line('💡 Or use --force to enable with database fallback only');
            return 1;
        }

        if ($available) {
            $this->info('✅ Redis is available and working');
        } else {
            $this->warn('⚠️ Redis not available - configuring database fallback only');
        }

        // Configure cache manager
        $this->line('🔧 Configuring cache manager...');
        TenancyCacheManager::configureCacheDriver();
        
        $driver = TenancyCacheManager::getBestCacheStore();
        $this->line("   • Selected cache driver: {$driver}");

        // Show current configuration
        $this->newLine();
        $this->info('📊 Current Configuration:');
        
        $stats = TenancyCacheManager::getStats();
        $this->line('   • Current store: ' . $stats['current_store']);
        $this->line('   • Redis available: ' . ($stats['redis_available'] ? '✅' : '❌'));
        $this->line('   • Fallback enabled: ' . ($stats['fallback_enabled'] ? '✅' : '❌'));

        if ($stats['redis_available'] && isset($stats['version'])) {
            $this->line('   • Redis version: ' . $stats['version']);
            $this->line('   • Memory usage: ' . $stats['used_memory_human']);
            $this->line('   • Hit rate: ' . $stats['hit_rate'] . '%');
        }

        // Test if requested
        if ($this->option('test')) {
            $this->newLine();
            $this->testConfiguration();
        }

        $this->newLine();
        $this->info('✅ Redis configuration completed!');
        
        if ($available) {
            $this->line('🎉 Redis is now enabled for high-performance caching');
        } else {
            $this->line('💾 Database caching is active (Redis fallback ready)');
        }

        return 0;
    }

    private function testConfiguration(): void
    {
        $this->info('🧪 Testing cache configuration...');

        try {
            // Test basic caching
            $key = 'tenancy_test_' . time();
            $value = 'test_value_' . uniqid();
            
            TenancyCacheManager::put($key, $value, 60);
            $retrieved = TenancyCacheManager::get($key);
            TenancyCacheManager::forget($key);
            
            if ($retrieved === $value) {
                $this->line('   • Basic caching: ✅ Working');
            } else {
                $this->line('   • Basic caching: ❌ Failed');
                return;
            }

            // Test tenant-scoped caching
            $tenantKey = 'tenant_test_' . time();
            $tenantValue = 'tenant_value_' . uniqid();
            
            TenancyCacheManager::put($tenantKey, $tenantValue, 60, 'test_tenant');
            $retrievedTenant = TenancyCacheManager::get($tenantKey, null, 'test_tenant');
            TenancyCacheManager::forget($tenantKey, 'test_tenant');
            
            if ($retrievedTenant === $tenantValue) {
                $this->line('   • Tenant-scoped caching: ✅ Working');
            } else {
                $this->line('   • Tenant-scoped caching: ❌ Failed');
            }

            // Test remember functionality
            $rememberKey = 'remember_test_' . time();
            $callCount = 0;
            
            $result = TenancyCacheManager::remember($rememberKey, function() use (&$callCount) {
                $callCount++;
                return 'cached_result';
            }, 60);
            
            // Call again - should not increment counter
            $result2 = TenancyCacheManager::remember($rememberKey, function() use (&$callCount) {
                $callCount++;
                return 'cached_result';
            }, 60);
            
            TenancyCacheManager::forget($rememberKey);
            
            if ($callCount === 1 && $result === $result2) {
                $this->line('   • Remember caching: ✅ Working');
            } else {
                $this->line('   • Remember caching: ❌ Failed');
            }

            $this->line('   ✅ All cache tests passed');

        } catch (\Exception $e) {
            $this->error('   ❌ Cache test failed: ' . $e->getMessage());
        }
    }
}
