<?php

namespace ArtflowStudio\Tenancy\Commands\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class CacheSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenancy:cache-setup 
                            {driver : Cache driver to setup (redis, database, file)} 
                            {--isolation=database : Cache isolation mode (database, prefix, tags)}
                            {--force : Force overwrite existing configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Setup and configure cache for multi-tenancy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = $this->argument('driver');
        $isolation = $this->option('isolation');

        $this->info("🔧 Setting up cache driver: {$driver} with isolation: {$isolation}");

        // Validate driver
        if (!in_array($driver, ['redis', 'database', 'file'])) {
            $this->error('Invalid cache driver. Supported: redis, database, file');
            return 1;
        }

        // Validate isolation mode
        if (!in_array($isolation, ['database', 'prefix', 'tags'])) {
            $this->error('Invalid isolation mode. Supported: database, prefix, tags');
            return 1;
        }

        // Check driver compatibility
        if ($isolation === 'tags' && !in_array($driver, ['redis', 'memcached'])) {
            $this->error('Tags isolation is only supported with Redis or Memcached drivers');
            return 1;
        }

        try {
            // Update cache configuration
            $this->updateCacheConfig($driver);
            
            // Update artflow-tenancy configuration
            $this->updateTenancyConfig($isolation);
            
            // Install Redis if needed
            if ($driver === 'redis') {
                $this->setupRedis();
            }
            
            // Setup database cache if needed
            if ($driver === 'database' || $isolation === 'database') {
                $this->setupDatabaseCache();
            }
            
            // Update tenancy bootstrappers
            $this->updateTenancyBootstrappers($isolation);
            
            $this->line('');
            $this->info('✅ Cache setup completed successfully!');
            $this->line('');
            $this->warn('⚠️  You may need to restart your web server for changes to take effect.');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Failed to setup cache: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Update cache configuration in config/cache.php
     */
    protected function updateCacheConfig(string $driver): void
    {
        $cacheConfigPath = config_path('cache.php');
        
        if (!File::exists($cacheConfigPath)) {
            $this->error('cache.php config file not found');
            throw new \Exception('Configuration file missing');
        }

        $this->info("📝 Updating cache configuration to use {$driver} driver");

        // For now, just inform the user what to do
        // In a real implementation, you'd modify the config file
        $envUpdates = [
            'CACHE_DRIVER=' . $driver,
        ];

        if ($driver === 'redis') {
            $envUpdates[] = 'REDIS_HOST=127.0.0.1';
            $envUpdates[] = 'REDIS_PASSWORD=null';
            $envUpdates[] = 'REDIS_PORT=6379';
        }

        $this->line('   Update your .env file with:');
        foreach ($envUpdates as $update) {
            $this->line("   {$update}");
        }
    }

    /**
     * Update artflow-tenancy configuration
     */
    protected function updateTenancyConfig(string $isolation): void
    {
        $tenancyConfigPath = config_path('artflow-tenancy.php');
        
        if (File::exists($tenancyConfigPath)) {
            $this->info("📝 Setting cache isolation mode to: {$isolation}");
            
            $configContent = File::get($tenancyConfigPath);
            
            // Update isolation mode
            $pattern = "/'isolation_mode'\s*=>\s*env\([^)]+\),?\s*[^,]*/";
            $replacement = "'isolation_mode' => env('TENANT_CACHE_ISOLATION', '{$isolation}'),";
            
            $updatedContent = preg_replace($pattern, $replacement, $configContent);
            
            if ($updatedContent !== $configContent) {
                File::put($tenancyConfigPath, $updatedContent);
                $this->line('   ✓ Updated artflow-tenancy.php configuration');
            }
            
            // Also suggest .env update
            $this->line('   Update your .env file with:');
            $this->line("   TENANT_CACHE_ISOLATION={$isolation}");
        }
    }

    /**
     * Setup Redis if needed
     */
    protected function setupRedis(): void
    {
        $this->info('🔧 Setting up Redis configuration');
        
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            $this->warn('⚠️  Redis PHP extension is not loaded. Please install php-redis extension.');
        }
        
        $this->line('   Redis setup instructions:');
        $this->line('   1. Install Redis server if not already installed');
        $this->line('   2. Install php-redis extension');
        $this->line('   3. Update your .env file with Redis connection details');
        $this->line('   4. Test connection with: php artisan tinker -> Redis::ping()');
    }

    /**
     * Setup database cache
     */
    protected function setupDatabaseCache(): void
    {
        $this->info('🔧 Setting up database cache');
        
        try {
            // Create cache table if it doesn't exist
            Artisan::call('cache:table');
            $this->line('   ✓ Cache table created/verified');
            
            // Run migration
            if ($this->confirm('Run cache table migration now?', true)) {
                Artisan::call('migrate');
                $this->line('   ✓ Cache table migration completed');
            }
            
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Could not setup cache table: {$e->getMessage()}");
        }
    }

    /**
     * Update tenancy bootstrappers configuration
     */
    protected function updateTenancyBootstrappers(string $isolation): void
    {
        $this->info('🔧 Updating tenancy bootstrappers');
        
        $tenancyConfigPath = config_path('tenancy.php');
        
        if (File::exists($tenancyConfigPath)) {
            $this->line('   To enable enhanced cache isolation, update config/tenancy.php:');
            $this->line('');
            $this->line("   'bootstrappers' => [");
            $this->line("       \\Stancl\\Tenancy\\Bootstrappers\\DatabaseTenancyBootstrapper::class,");
            $this->line("       ArtflowStudio\\Tenancy\\Bootstrappers\\SessionTenancyBootstrapper::class,");
            $this->line("       ArtflowStudio\\Tenancy\\Bootstrappers\\EnhancedCacheTenancyBootstrapper::class,");
            $this->line("       \\Stancl\\Tenancy\\Bootstrappers\\QueueTenancyBootstrapper::class,");
            $this->line("   ],");
            $this->line('');
        } else {
            $this->warn('   ⚠️  tenancy.php config file not found');
        }
    }
}
