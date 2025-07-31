<?php

namespace ArtflowStudio\Tenancy\Commands\Core;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SwitchCacheDriverCommand extends Command
{
    protected $signature = 'tenancy:cache-driver 
                            {driver? : The cache driver to switch to (file, database, redis)}
                            {--tenant-cache= : Set different cache driver for tenant-specific cache}
                            {--restart-queue : Restart queue workers after changing cache}
                            {--clear-cache : Clear all cache after switching}
                            {--list : List available cache drivers}
                            {--current : Show current cache configuration}';

    protected $description = 'Switch cache driver and update environment configuration';

    protected array $supportedDrivers = ['file', 'database', 'redis'];

    public function handle(): int
    {
        // Handle list option
        if ($this->option('list')) {
            return $this->listAvailableDrivers();
        }

        // Handle current configuration option
        if ($this->option('current')) {
            return $this->showCurrentConfiguration();
        }

        $driver = $this->argument('driver');

        // If no driver specified, show interactive menu
        if (!$driver) {
            return $this->showInteractiveMenu();
        }

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

        // Continue with existing logic...
        return $this->performCacheDriverSwitch($driver, $tenantCache);
    }

    /**
     * Show interactive menu for cache driver selection
     */
    private function showInteractiveMenu(): int
    {
        $this->info('🗄️  Cache Driver Management');
        $this->info('Configure cache drivers for your multi-tenant application');
        $this->newLine();

        $this->showCurrentConfiguration();
        $this->newLine();

        $actions = [
            'switch' => 'Switch cache driver',
            'current' => 'Show current configuration',
            'test' => 'Test cache driver connectivity',
            'clear' => 'Clear all cache',
        ];

        $this->info('Available actions:');
        foreach ($actions as $key => $description) {
            $this->info("  <fg=green>{$key}</fg=green> - {$description}");
        }
        $this->newLine();

        $action = $this->choice('What would you like to do?', array_keys($actions));

        switch ($action) {
            case 'switch':
                $driver = $this->choice('Select cache driver', $this->supportedDrivers);
                return $this->performCacheDriverSwitch($driver, $driver);
            case 'current':
                return $this->showCurrentConfiguration();
            case 'test':
                return $this->testCacheDrivers();
            case 'clear':
                return $this->clearAllCache();
            default:
                return 0;
        }
    }

    /**
     * List available cache drivers
     */
    private function listAvailableDrivers(): int
    {
        $this->info('📋 Available Cache Drivers:');
        $this->newLine();

        $driverInfo = [
            'file' => [
                'description' => 'File-based cache storage',
                'pros' => 'Simple, no dependencies',
                'cons' => 'Slower, not shared across servers'
            ],
            'database' => [
                'description' => 'Database-based cache storage',
                'pros' => 'Persistent, shared across servers',
                'cons' => 'Database overhead, requires cache table'
            ],
            'redis' => [
                'description' => 'Redis in-memory cache',
                'pros' => 'Fast, advanced features, shared',
                'cons' => 'Requires Redis server'
            ]
        ];

        foreach ($this->supportedDrivers as $driver) {
            $info = $driverInfo[$driver];
            $this->info("🔧 <fg=green>{$driver}</fg=green>");
            $this->line("   Description: {$info['description']}");
            $this->line("   Pros: {$info['pros']}");
            $this->line("   Cons: {$info['cons']}");
            $this->newLine();
        }

        return 0;
    }

    /**
     * Show current cache configuration
     */
    private function showCurrentConfiguration(): int
    {
        $this->info('📊 Current Cache Configuration:');
        $this->newLine();

        $currentDriver = config('cache.default', 'file');
        $this->info("Default cache driver: <fg=green>{$currentDriver}</fg=green>");

        // Show cache stores
        $stores = config('cache.stores', []);
        if (!empty($stores)) {
            $this->info('Configured cache stores:');
            foreach ($stores as $name => $config) {
                $driver = $config['driver'] ?? 'unknown';
                
                // Check for missing extensions and provide helpful info
                $status = '❓';
                $note = '';
                
                try {
                    switch ($driver) {
                        case 'memcached':
                            if (!extension_loaded('memcached')) {
                                $status = '❌';
                                $note = ' (Memcached extension not installed)';
                            } else {
                                $status = $this->testCacheStore($name) ? '✅' : '❌';
                            }
                            break;
                        case 'redis':
                            if (!extension_loaded('redis') && !extension_loaded('predis')) {
                                $status = '❌';
                                $note = ' (Redis/Predis extension not installed)';
                            } else {
                                $status = $this->testCacheStore($name) ? '✅' : '❌';
                            }
                            break;
                        case 'dynamodb':
                            if (!class_exists('Aws\DynamoDb\DynamoDbClient')) {
                                $status = '❌';
                                $note = ' (AWS SDK not installed)';
                            } else {
                                $status = $this->testCacheStore($name) ? '✅' : '❌';
                            }
                            break;
                        case 'octane':
                            if (!class_exists('Laravel\Octane\OctaneServiceProvider')) {
                                $status = '❌';
                                $note = ' (Laravel Octane not installed)';
                            } else {
                                $status = $this->testCacheStore($name) ? '✅' : '❌';
                            }
                            break;
                        default:
                            $status = $this->testCacheStore($name) ? '✅' : '❌';
                    }
                } catch (\Exception $e) {
                    $status = '❌';
                    $note = ' (Error: ' . $e->getMessage() . ')';
                }
                
                $this->line("  {$status} <fg=yellow>{$name}</fg=yellow> ({$driver}){$note}");
            }
        }

        // Show extension status
        $this->newLine();
        $this->info('📦 Cache Extension Status:');
        $extensions = [
            'memcached' => 'Memcached support',
            'redis' => 'Redis support', 
            'apcu' => 'APCu support'
        ];

        foreach ($extensions as $ext => $desc) {
            $status = extension_loaded($ext) ? '✅' : '❌';
            $this->line("  {$status} {$desc}");
        }

        return 0;
    }

    /**
     * Perform the actual cache driver switch
     */
    private function performCacheDriverSwitch(string $driver, string $tenantCache): int
    {
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

    /**
     * Test cache store connectivity
     */
    private function testCacheStore(string $store): bool
    {
        try {
            $config = config("cache.stores.{$store}");
            if (!$config) {
                return false;
            }

            $driver = $config['driver'] ?? 'unknown';
            
            // Check if required extensions/packages are available
            switch ($driver) {
                case 'memcached':
                    if (!extension_loaded('memcached')) {
                        return false;
                    }
                    break;
                case 'redis':
                    if (!extension_loaded('redis') && !extension_loaded('predis')) {
                        return false;
                    }
                    break;
                case 'dynamodb':
                    if (!class_exists('Aws\DynamoDb\DynamoDbClient')) {
                        return false;
                    }
                    break;
                case 'octane':
                    if (!class_exists('Laravel\Octane\OctaneServiceProvider')) {
                        return false;
                    }
                    break;
            }

            $cache = Cache::store($store);
            $cache->put('test_key', 'test_value', 60);
            $result = $cache->get('test_key') === 'test_value';
            $cache->forget('test_key');
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test all cache drivers
     */
    private function testCacheDrivers(): int
    {
        $this->info('🧪 Testing Cache Driver Connectivity...');
        $this->newLine();

        $stores = config('cache.stores', []);
        
        foreach ($stores as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';
            $this->line("Testing {$name} ({$driver})...");
            
            $result = $this->testCacheStore($name);
            $status = $result ? '✅ Connected' : '❌ Failed';
            $this->line("  {$status}");
        }

        return 0;
    }

    /**
     * Clear all cache
     */
    private function clearAllCache(): int
    {
        $this->info('🧹 Clearing all cache...');
        
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            $this->info('✅ All cache cleared successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error clearing cache: {$e->getMessage()}");
            return 1;
        }
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
