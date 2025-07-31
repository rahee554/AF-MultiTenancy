<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Redis;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\RedisHelper;
use Illuminate\Support\Facades\File;
use Exception;

class ConfigureRedisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:configure-redis 
                           {--install : Install phpredis extension}
                           {--configure : Configure Laravel for Redis}
                           {--test : Test Redis configuration}
                           {--enable-fallback : Enable database fallback}
                           {--force : Force overwrite existing configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Redis for ArtFlow Tenancy package with automatic fallback';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 ArtFlow Tenancy Redis Configuration');
        $this->newLine();

        if ($this->option('install')) {
            $this->installPhpRedis();
        }

        if ($this->option('configure')) {
            $this->configureLaravel();
        }

        if ($this->option('enable-fallback')) {
            $this->enableFallback();
        }

        if ($this->option('test')) {
            $this->testConfiguration();
        }

        if (!$this->hasAnyOption()) {
            $this->runInteractiveSetup();
        }

        return 0;
    }

    private function hasAnyOption(): bool
    {
        return $this->option('install') || 
               $this->option('configure') || 
               $this->option('test') || 
               $this->option('enable-fallback');
    }

    private function runInteractiveSetup(): void
    {
        $this->info('🔧 Interactive Redis Setup for ArtFlow Tenancy');
        $this->newLine();

        // Check current status
        $this->checkCurrentStatus();
        $this->newLine();

        // Ask what to do
        $actions = [];
        
        if (!extension_loaded('redis')) {
            if ($this->confirm('Install phpredis extension?', true)) {
                $actions[] = 'install';
            }
        }

        if ($this->confirm('Configure Laravel for Redis?', true)) {
            $actions[] = 'configure';
        }

        if ($this->confirm('Enable database fallback when Redis is unavailable?', true)) {
            $actions[] = 'fallback';
        }

        if ($this->confirm('Test Redis configuration?', true)) {
            $actions[] = 'test';
        }

        // Execute actions
        foreach ($actions as $action) {
            $this->newLine();
            switch ($action) {
                case 'install':
                    $this->installPhpRedis();
                    break;
                case 'configure':
                    $this->configureLaravel();
                    break;
                case 'fallback':
                    $this->enableFallback();
                    break;
                case 'test':
                    $this->testConfiguration();
                    break;
            }
        }

        $this->newLine();
        $this->info('✅ Redis configuration completed!');
    }

    private function checkCurrentStatus(): void
    {
        $this->info('📋 Current Status:');
        
        // Check phpredis extension
        $redisExt = extension_loaded('redis');
        $this->line('   • phpredis extension: ' . ($redisExt ? '✅ Installed' : '❌ Not installed'));
        
        if ($redisExt) {
            $this->line('     Version: ' . phpversion('redis'));
        }

        // Check Redis server
        try {
            $redis = new \Redis();
            $connected = $redis->connect('127.0.0.1', 6379, 5);
            if ($connected) {
                $this->line('   • Redis server: ✅ Running');
                $info = $redis->info();
                $this->line('     Version: ' . ($info['redis_version'] ?? 'unknown'));
                $redis->close();
            } else {
                $this->line('   • Redis server: ❌ Not accessible');
            }
        } catch (Exception $e) {
            $this->line('   • Redis server: ❌ Not accessible');
        }

        // Check Laravel configuration
        $cacheDriver = config('cache.default', 'not set');
        $this->line('   • Laravel cache driver: ' . $cacheDriver);
        
        $tenancyCacheStore = config('tenancy.cache.store', 'not set');
        $this->line('   • Tenancy cache store: ' . $tenancyCacheStore);

        // Check environment variables
        $envFile = base_path('.env');
        if (File::exists($envFile)) {
            $envContent = File::get($envFile);
            $hasRedisConfig = str_contains($envContent, 'REDIS_CLIENT=') || str_contains($envContent, 'CACHE_STORE=redis');
            $this->line('   • .env Redis config: ' . ($hasRedisConfig ? '✅ Configured' : '❌ Not configured'));
        } else {
            $this->line('   • .env file: ❌ Not found');
        }
    }

    private function installPhpRedis(): void
    {
        $this->info('📦 Installing phpredis extension...');

        if (extension_loaded('redis')) {
            $this->line('   • phpredis already installed (version ' . phpversion('redis') . ')');
            return;
        }

        $this->line('   • Updating package index...');
        $this->execCommand('sudo apt update > /dev/null 2>&1');

        $this->line('   • Installing build dependencies...');
        $this->execCommand('sudo apt install -y php8.3-dev php-pear build-essential > /dev/null 2>&1');

        $this->line('   • Installing phpredis via PECL...');
        $output = $this->execCommand('sudo pecl install redis 2>&1');

        if (str_contains($output, 'successfully')) {
            $this->line('   • Creating module configuration...');
            $this->execCommand('echo "extension=redis.so" | sudo tee /etc/php/8.3/mods-available/redis.ini > /dev/null');

            $this->line('   • Enabling extension...');
            $this->execCommand('sudo phpenmod redis');

            $this->line('   • Restarting PHP-FPM...');
            $this->execCommand('sudo systemctl restart php8.3-fpm');

            $this->info('   ✅ phpredis installation completed');
        } else {
            $this->error('   ❌ phpredis installation failed');
            $this->line('   Output: ' . $output);
        }
    }

    private function configureLaravel(): void
    {
        $this->info('⚙️ Configuring Laravel for Redis...');

        $envFile = base_path('.env');
        
        if (!File::exists($envFile)) {
            $this->error('   ❌ .env file not found');
            return;
        }

        $envContent = File::get($envFile);
        $modified = false;

        // Redis configuration
        $redisVars = [
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
            'REDIS_DB' => '0',
            'REDIS_CACHE_DB' => '1',
            'REDIS_SESSION_DB' => '2',
        ];

        foreach ($redisVars as $key => $value) {
            if (!str_contains($envContent, $key . '=')) {
                $envContent .= "\n{$key}={$value}";
                $modified = true;
                $this->line("   • Added {$key}={$value}");
            } else {
                $this->line("   • {$key} already configured");
            }
        }

        // Cache configuration
        if (str_contains($envContent, 'CACHE_STORE=')) {
            $envContent = preg_replace('/CACHE_STORE=.*/m', 'CACHE_STORE=redis', $envContent);
            $modified = true;
            $this->line('   • Updated CACHE_STORE=redis');
        } else {
            $envContent .= "\nCACHE_STORE=redis";
            $modified = true;
            $this->line('   • Added CACHE_STORE=redis');
        }

        // Tenancy configuration
        $tenancyVars = [
            'TENANT_CACHE_DRIVER' => 'redis',
            'TENANCY_CACHE_STORE' => 'redis',
            'TENANCY_CACHED_LOOKUP' => '1',
        ];

        foreach ($tenancyVars as $key => $value) {
            if (str_contains($envContent, $key . '=')) {
                $envContent = preg_replace("/{$key}=.*/m", "{$key}={$value}", $envContent);
                $modified = true;
                $this->line("   • Updated {$key}={$value}");
            } else {
                $envContent .= "\n{$key}={$value}";
                $modified = true;
                $this->line("   • Added {$key}={$value}");
            }
        }

        if ($modified) {
            File::put($envFile, $envContent);
            $this->info('   ✅ .env file updated');
            
            // Clear config cache
            $this->execCommand('php artisan config:clear > /dev/null 2>&1');
            $this->line('   • Configuration cache cleared');
        } else {
            $this->line('   • No changes needed');
        }
    }

    private function enableFallback(): void
    {
        $this->info('🔄 Enabling database fallback...');

        $envFile = base_path('.env');
        
        if (!File::exists($envFile)) {
            $this->error('   ❌ .env file not found');
            return;
        }

        $envContent = File::get($envFile);
        
        if (!str_contains($envContent, 'CACHE_FALLBACK_DRIVER=')) {
            $envContent .= "\nCACHE_FALLBACK_DRIVER=database";
            File::put($envContent, $envFile);
            $this->line('   • Added CACHE_FALLBACK_DRIVER=database');
        } else {
            $this->line('   • Fallback already configured');
        }

        $this->info('   ✅ Database fallback enabled');
    }

    private function testConfiguration(): void
    {
        $this->info('🧪 Testing Redis configuration...');

        // Test Redis availability
        $available = RedisHelper::isAvailable();
        $this->line('   • Redis availability: ' . ($available ? '✅ Available' : '❌ Not available'));

        if ($available) {
            // Test connection details
            $test = RedisHelper::testConnection();
            
            if ($test['server_info']) {
                $info = $test['server_info'];
                $this->line('   • Redis version: ' . $info['redis_version']);
                $this->line('   • Memory usage: ' . $info['used_memory_human']);
                $this->line('   • Connected clients: ' . $info['connected_clients']);
            }

            // Test cache operations
            $this->line('   • Testing cache operations...');
            
            try {
                $store = RedisHelper::getStore();
                $testKey = 'tenancy_redis_test_' . time();
                $testValue = 'test_value_' . uniqid();
                
                $store->put($testKey, $testValue, 60);
                $retrieved = $store->get($testKey);
                $store->forget($testKey);
                
                if ($retrieved === $testValue) {
                    $this->line('   • Cache operations: ✅ Working');
                } else {
                    $this->line('   • Cache operations: ❌ Failed');
                }
            } catch (Exception $e) {
                $this->line('   • Cache operations: ❌ Error - ' . $e->getMessage());
            }

            // Get performance stats
            $stats = RedisHelper::getStats();
            if ($stats['available']) {
                $this->line('   • Hit rate: ' . $stats['hit_rate'] . '%');
                $this->line('   • Total commands: ' . number_format($stats['total_commands_processed']));
            }

        } else {
            $this->warn('   • Fallback to database cache will be used');
        }

        $this->info('   ✅ Configuration test completed');
    }

    protected function execCommand(string $command): string
    {
        $output = shell_exec($command);
        return $output ?: '';
    }
}
