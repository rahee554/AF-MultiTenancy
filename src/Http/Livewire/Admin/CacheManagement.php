<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CacheManagement extends Component
{
    #[Layout('artflow-tenancy::layout.app')]

    public $currentCacheDriver;
    public $currentIsolationMode;
    public $availableDrivers = ['database', 'redis', 'memcached', 'file'];
    public $availableIsolationModes = ['database', 'prefix', 'tags'];
    
    public $newCacheDriver;
    public $newIsolationMode;
    
    public $cacheStats = [];
    public $redisConfig = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ];

    public $showRedisConfig = false;
    public $testResults = [];

    public function mount()
    {
        $this->currentCacheDriver = config('cache.default');
        $this->currentIsolationMode = config('artflow-tenancy.cache.isolation_mode', 'database');
        $this->newCacheDriver = $this->currentCacheDriver;
        $this->newIsolationMode = $this->currentIsolationMode;
        $this->loadCacheStats();
        $this->loadRedisConfig();
    }

    public function render()
    {
        return view('artflow-tenancy::livewire.admin.cache-management');
    }

    public function loadCacheStats()
    {
        try {
            $this->cacheStats = [
                'current_driver' => $this->currentCacheDriver,
                'isolation_mode' => $this->currentIsolationMode,
                'cache_size' => $this->getCacheSize(),
                'total_keys' => $this->getTotalKeys(),
                'redis_available' => $this->isRedisAvailable(),
                'memcached_available' => $this->isMemcachedAvailable(),
            ];
        } catch (\Exception $e) {
            $this->cacheStats = [
                'error' => 'Unable to load cache stats: ' . $e->getMessage()
            ];
        }
    }

    public function loadRedisConfig()
    {
        $redisConfig = config('database.redis.default', []);
        $this->redisConfig = array_merge($this->redisConfig, [
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => $redisConfig['port'] ?? 6379,
            'password' => $redisConfig['password'] ?? null,
            'database' => $redisConfig['database'] ?? 0,
        ]);
    }

    public function testCacheConnection()
    {
        $this->testResults = [];
        
        try {
            // Test current cache
            Cache::put('test_key', 'test_value', 60);
            $value = Cache::get('test_key');
            Cache::forget('test_key');
            
            if ($value === 'test_value') {
                $this->testResults['current_cache'] = [
                    'status' => 'success',
                    'message' => 'Current cache is working correctly'
                ];
            } else {
                $this->testResults['current_cache'] = [
                    'status' => 'error',
                    'message' => 'Current cache test failed'
                ];
            }
        } catch (\Exception $e) {
            $this->testResults['current_cache'] = [
                'status' => 'error',
                'message' => 'Current cache error: ' . $e->getMessage()
            ];
        }

        // Test Redis if available
        if ($this->isRedisAvailable()) {
            try {
                if (class_exists('Redis')) {
                    $redis = new \Redis();
                    $redis->connect($this->redisConfig['host'], $this->redisConfig['port']);
                    if ($this->redisConfig['password']) {
                        $redis->auth($this->redisConfig['password']);
                    }
                    $redis->select($this->redisConfig['database']);
                    
                    $redis->set('test_key', 'test_value', 60);
                    $value = $redis->get('test_key');
                    $redis->del('test_key');
                    $redis->close();
                    
                    if ($value === 'test_value') {
                        $this->testResults['redis'] = [
                            'status' => 'success',
                            'message' => 'Redis connection successful'
                        ];
                    } else {
                        $this->testResults['redis'] = [
                            'status' => 'error',
                            'message' => 'Redis test failed'
                        ];
                    }
                } else {
                    $this->testResults['redis'] = [
                        'status' => 'error',
                        'message' => 'Redis extension not available'
                    ];
                }
            } catch (\Exception $e) {
                $this->testResults['redis'] = [
                    'status' => 'error',
                    'message' => 'Redis error: ' . $e->getMessage()
                ];
            }
        }
    }

    public function switchCacheDriver()
    {
        try {
            // Update cache configuration
            $this->updateCacheConfig();
            
            // Clear current cache
            $this->clearAllCache();
            
            // Test new configuration
            $this->testCacheConnection();
            
            session()->flash('message', "Cache driver switched to {$this->newCacheDriver} successfully!");
            
            $this->currentCacheDriver = $this->newCacheDriver;
            $this->loadCacheStats();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error switching cache driver: ' . $e->getMessage());
        }
    }

    public function switchIsolationMode()
    {
        try {
            // Update isolation mode configuration
            $this->updateIsolationConfig();
            
            session()->flash('message', "Cache isolation mode switched to {$this->newIsolationMode} successfully!");
            
            $this->currentIsolationMode = $this->newIsolationMode;
            $this->loadCacheStats();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error switching isolation mode: ' . $e->getMessage());
        }
    }

    public function clearAllCache()
    {
        try {
            // Clear application cache
            Artisan::call('cache:clear');
            
            // Clear config cache
            Artisan::call('config:clear');
            
            // Clear view cache
            Artisan::call('view:clear');
            
            session()->flash('message', 'All cache cleared successfully!');
            $this->loadCacheStats();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error clearing cache: ' . $e->getMessage());
        }
    }

    public function optimizeCache()
    {
        try {
            // Clear and rebuild cache
            Artisan::call('cache:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            
            session()->flash('message', 'Cache optimized successfully!');
            $this->loadCacheStats();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error optimizing cache: ' . $e->getMessage());
        }
    }

    public function toggleRedisConfig()
    {
        $this->showRedisConfig = !$this->showRedisConfig;
    }

    public function saveRedisConfig()
    {
        try {
            $this->updateRedisConfig();
            session()->flash('message', 'Redis configuration updated successfully!');
            $this->showRedisConfig = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating Redis config: ' . $e->getMessage());
        }
    }

    private function updateCacheConfig()
    {
        $configPath = config_path('cache.php');
        $config = include $configPath;
        
        $config['default'] = $this->newCacheDriver;
        
        if ($this->newCacheDriver === 'redis' && !isset($config['stores']['redis'])) {
            $config['stores']['redis'] = [
                'driver' => 'redis',
                'connection' => 'cache',
                'lock_connection' => 'default',
            ];
        }
        
        $this->writeConfigFile($configPath, $config);
    }

    private function updateIsolationConfig()
    {
        $configPath = config_path('artflow-tenancy.php');
        if (!File::exists($configPath)) {
            return;
        }
        
        $config = include $configPath;
        $config['cache']['isolation_mode'] = $this->newIsolationMode;
        
        $this->writeConfigFile($configPath, $config);
    }

    private function updateRedisConfig()
    {
        $configPath = config_path('database.php');
        $config = include $configPath;
        
        $config['redis']['default'] = [
            'url' => env('REDIS_URL'),
            'host' => $this->redisConfig['host'],
            'password' => $this->redisConfig['password'],
            'port' => $this->redisConfig['port'],
            'database' => $this->redisConfig['database'],
        ];
        
        $config['redis']['cache'] = [
            'url' => env('REDIS_URL'),
            'host' => $this->redisConfig['host'],
            'password' => $this->redisConfig['password'],
            'port' => $this->redisConfig['port'],
            'database' => ($this->redisConfig['database'] + 1),
        ];
        
        $this->writeConfigFile($configPath, $config);
    }

    private function writeConfigFile($path, $config)
    {
        $export = var_export($config, true);
        $content = "<?php\n\nreturn {$export};\n";
        File::put($path, $content);
        
        // Clear config cache
        Artisan::call('config:clear');
    }

    private function getCacheSize()
    {
        try {
            switch ($this->currentCacheDriver) {
                case 'database':
                    return DB::table('cache')->count() . ' items';
                case 'file':
                    $cacheDir = storage_path('framework/cache/data');
                    if (File::exists($cacheDir)) {
                        $size = 0;
                        foreach (File::allFiles($cacheDir) as $file) {
                            $size += $file->getSize();
                        }
                        return $this->formatBytes($size);
                    }
                    return '0 bytes';
                default:
                    return 'Unknown';
            }
        } catch (\Exception $e) {
            return 'Error calculating size';
        }
    }

    private function getTotalKeys()
    {
        try {
            switch ($this->currentCacheDriver) {
                case 'database':
                    return DB::table('cache')->count();
                case 'redis':
                    if ($this->isRedisAvailable() && class_exists('Redis')) {
                        $redis = new \Redis();
                        $redis->connect($this->redisConfig['host'], $this->redisConfig['port']);
                        if ($this->redisConfig['password']) {
                            $redis->auth($this->redisConfig['password']);
                        }
                        $redis->select($this->redisConfig['database']);
                        $count = $redis->dbSize();
                        $redis->close();
                        return $count;
                    }
                    return 0;
                default:
                    return 'Unknown';
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function isRedisAvailable()
    {
        return extension_loaded('redis') || class_exists('Predis\Client');
    }

    private function isMemcachedAvailable()
    {
        return extension_loaded('memcached');
    }

    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}
