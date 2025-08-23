<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SwitchCacheDriverCommand extends Command
{
    protected $signature = 'tenancy:cache-driver 
                            {driver : The cache driver to switch to (file, database, redis)}
                            {--tenant-cache= : Set different cache driver for tenant-specific cache}
                            {--restart-queue : Restart queue workers after changing cache}
                            {--clear-cache : Clear all cache after switching}';

    protected $description = 'Switch cache driver and update environment configuration';

    protected array $supportedDrivers = ['file', 'database', 'redis'];

    public function handle(): int
    {
        $driver = $this->argument('driver');
        $tenantCache = $this->option('tenant-cache') ?: $driver;

        // Validate drivers
        if (!in_array($driver, $this->supportedDrivers)) {
            $this->error("Unsupported cache driver: {$driver}");
            $this->line('Supported drivers: ' . implode(', ', $this->supportedDrivers));
            return 1;
        }

        if (!in_array($tenantCache, $this->supportedDrivers)) {
            $this->error("Unsupported tenant cache driver: {$tenantCache}");
            $this->line('Supported drivers: ' . implode(', ', $this->supportedDrivers));
            return 1;
        }

        $this->info("🔄 Switching cache driver to: {$driver}");
        if ($driver !== $tenantCache) {
            $this->info("🏢 Tenant cache driver: {$tenantCache}");
        }

        // Check environment file
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            $this->error('.env file not found!');
            return 1;
        }

        // Update environment variables
        $this->updateEnvironmentFile($envPath, $driver, $tenantCache);

        // Update configuration if needed
        $this->updateConfigurations($driver, $tenantCache);

        // Install dependencies if needed
        $this->installDependencies($driver);

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->clearCache();
        }

        // Restart queue if requested
        if ($this->option('restart-queue')) {
            $this->restartQueue();
        }

        $this->newLine();
        $this->info('✅ Cache driver switched successfully!');
        $this->displayNextSteps($driver, $tenantCache);

        return 0;
    }

    private function updateEnvironmentFile(string $envPath, string $driver, string $tenantCache): void
    {
        $this->line('📝 Updating .env file...');

        $envContent = File::get($envPath);
        
        // Update main cache driver
        $envContent = $this->updateEnvVariable($envContent, 'CACHE_DRIVER', $driver);
        $envContent = $this->updateEnvVariable($envContent, 'TENANT_CACHE_DRIVER', $tenantCache);

        // Update tenancy cache store
        $tenancyCacheStore = $driver === 'file' ? 'file' : $driver;
        $envContent = $this->updateEnvVariable($envContent, 'TENANCY_CACHE_STORE', $tenancyCacheStore);

        // Driver-specific configurations
        switch ($driver) {
            case 'redis':
                $envContent = $this->ensureRedisConfig($envContent);
                break;
            case 'database':
                $envContent = $this->ensureDatabaseConfig($envContent);
                break;
        }

        File::put($envPath, $envContent);
        $this->line('   ✅ Environment variables updated');
    }

    private function updateEnvVariable(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $replacement, $content);
        } else {
            // Add new variable if not exists
            return $content . "\n{$replacement}";
        }
    }

    private function ensureRedisConfig(string $content): string
    {
        $redisVars = [
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
        ];

        foreach ($redisVars as $key => $defaultValue) {
            if (!str_contains($content, $key . '=')) {
                $content .= "\n{$key}={$defaultValue}";
            }
        }

        return $content;
    }

    private function ensureDatabaseConfig(string $content): string
    {
        // Ensure session driver is database for consistency
        $content = $this->updateEnvVariable($content, 'SESSION_DRIVER', 'database');
        $content = $this->updateEnvVariable($content, 'QUEUE_CONNECTION', 'database');

        return $content;
    }

    private function updateConfigurations(string $driver, string $tenantCache): void
    {
        $this->line('⚙️  Updating configuration files...');

        // Update tenancy.php config
        $this->updateTenancyConfig($driver);

        // Update artflow-tenancy.php config 
        $this->updateArtflowConfig($tenantCache);

        $this->line('   ✅ Configuration files updated');
    }

    private function updateTenancyConfig(string $driver): void
    {
        $configPath = config_path('tenancy.php');
        if (!File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);
        
        // Update cached_lookup cache_store
        $pattern = "/'cache_store'\s*=>\s*env\('TENANCY_CACHE_STORE',\s*'[^']*'\)/";
        $replacement = "'cache_store' => env('TENANCY_CACHE_STORE', '{$driver}')";
        $content = preg_replace($pattern, $replacement, $content);

        File::put($configPath, $content);
    }

    private function updateArtflowConfig(string $tenantCache): void
    {
        $configPath = config_path('artflow-tenancy.php');
        if (!File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);
        
        // Update cache driver default
        $pattern = "/'driver'\s*=>\s*env\('TENANT_CACHE_DRIVER',\s*'[^']*'\)/";
        $replacement = "'driver' => env('TENANT_CACHE_DRIVER', '{$tenantCache}')";
        $content = preg_replace($pattern, $replacement, $content);

        File::put($configPath, $content);
    }

    private function installDependencies(string $driver): void
    {
        if ($driver === 'redis') {
            $this->line('📦 Checking Redis dependencies...');
            
            if (!extension_loaded('redis')) {
                $this->warn('⚠️  PHP Redis extension not installed!');
                $this->line('   Install with: sudo apt install php-redis (Ubuntu/Debian)');
                $this->line('   Or: brew install php-redis (macOS)');
            } else {
                $this->line('   ✅ Redis extension is available');
            }

            // Check if Redis server is running
            try {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->ping();
                $this->line('   ✅ Redis server is running');
                $redis->close();
            } catch (\Exception $e) {
                $this->warn('⚠️  Redis server not accessible!');
                $this->line('   Start Redis: sudo systemctl start redis-server');
                $this->line('   Or: brew services start redis');
            }
        }
    }

    private function clearCache(): void
    {
        $this->line('🧹 Clearing cache...');
        
        try {
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->call('route:clear');
            $this->call('view:clear');
            
            // Clear tenant maintenance cache if available
            if (class_exists(\ArtflowStudio\Tenancy\Commands\Maintenance\TenantMaintenanceModeCommand::class)) {
                $this->call('tenants:maintenance', ['action' => 'clear-cache']);
            }
            
            $this->line('   ✅ Cache cleared successfully');
        } catch (\Exception $e) {
            $this->warn('⚠️  Some cache clearing failed: ' . $e->getMessage());
        }
    }

    private function restartQueue(): void
    {
        $this->line('🔄 Restarting queue workers...');
        
        try {
            $this->call('queue:restart');
            $this->line('   ✅ Queue workers restarted');
        } catch (\Exception $e) {
            $this->warn('⚠️  Queue restart failed: ' . $e->getMessage());
        }
    }

    private function displayNextSteps(string $driver, string $tenantCache): void
    {
        $this->newLine();
        $this->info('📋 Next Steps:');

        switch ($driver) {
            case 'redis':
                $this->line('1. Ensure Redis server is running');
                $this->line('2. Update Redis configuration if needed');
                $this->line('3. Test with: php artisan tinker -> Cache::put("test", "value"); Cache::get("test");');
                break;

            case 'database':
                $this->line('1. Run migrations to create cache table: php artisan migrate');
                $this->line('2. Test with: php artisan tinker -> Cache::put("test", "value"); Cache::get("test");');
                break;

            case 'file':
                $this->line('1. Ensure storage/framework/cache directory is writable');
                $this->line('2. Test with: php artisan tinker -> Cache::put("test", "value"); Cache::get("test");');
                break;
        }

        $this->newLine();
        $this->line('🔧 Useful commands:');
        $this->line('• Test cache: php artisan tinker');
        $this->line('• Clear cache: php artisan cache:clear');
        $this->line('• Monitor cache: php artisan tenancy:health-check');
        $this->line('• Test tenancy: php artisan tenancy:test');

        if ($driver !== $tenantCache) {
            $this->newLine();
            $this->info("ℹ️  You're using different cache drivers:");
            $this->line("   • Main cache: {$driver}");
            $this->line("   • Tenant cache: {$tenantCache}");
            $this->line('   This is useful for isolating tenant data while using faster cache for system operations.');
        }
    }
}
