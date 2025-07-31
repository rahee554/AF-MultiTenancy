<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ServerCompatibilityCommand extends Command
{
    protected $signature = 'tenant:server-check 
                            {--fastpanel : Check FastPanel 2 compatibility}
                            {--local : Check local development environment}
                            {--production : Check production server requirements}';

    protected $description = 'Check server compatibility for tenancy operations';

    public function handle()
    {
        $this->info('🖥️  Server Compatibility Check');
        $this->info('==============================');
        $this->newLine();

        $checkFastPanel = $this->option('fastpanel');
        $checkLocal = $this->option('local');
        $checkProduction = $this->option('production');

        // If no specific option, check everything
        if (!$checkFastPanel && !$checkLocal && !$checkProduction) {
            $checkFastPanel = true;
            $checkLocal = true;
            $checkProduction = true;
        }

        $allPassed = true;

        // Basic environment checks
        $allPassed &= $this->checkBasicRequirements();

        // Database checks
        $allPassed &= $this->checkDatabaseCompatibility();

        // File system checks
        $allPassed &= $this->checkFileSystemPermissions();

        // FastPanel specific checks
        if ($checkFastPanel) {
            $allPassed &= $this->checkFastPanelCompatibility();
        }

        // Local development checks
        if ($checkLocal) {
            $allPassed &= $this->checkLocalEnvironment();
        }

        // Production environment checks
        if ($checkProduction) {
            $allPassed &= $this->checkProductionRequirements();
        }

        $this->newLine();
        if ($allPassed) {
            $this->info('✅ All compatibility checks passed!');
            return 0;
        } else {
            $this->error('❌ Some compatibility checks failed. Please review the issues above.');
            return 1;
        }
    }

    private function checkBasicRequirements(): bool
    {
        $this->info('🔍 Basic Requirements');
        $this->info('====================');

        $checks = [];

        // PHP Version
        $phpVersion = phpversion();
        $phpOk = version_compare($phpVersion, '8.1.0', '>=');
        $checks[] = [
            'PHP Version',
            $phpOk ? '✅' : '❌',
            "Current: {$phpVersion} (Required: 8.1+)"
        ];

        // Laravel Version
        $laravelVersion = app()->version();
        $laravelOk = version_compare($laravelVersion, '11.0', '>=');
        $checks[] = [
            'Laravel Version',
            $laravelOk ? '✅' : '❌',
            "Current: {$laravelVersion} (Required: 11.0+)"
        ];

        // Extensions
        $extensions = ['pdo', 'pdo_mysql', 'json', 'fileinfo', 'openssl'];
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = [
                "PHP {$ext}",
                $loaded ? '✅' : '❌',
                $loaded ? 'Loaded' : 'Missing'
            ];
        }

        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseMemoryLimit($memoryLimit);
        $memoryOk = $memoryBytes >= (128 * 1024 * 1024); // 128MB
        $checks[] = [
            'Memory Limit',
            $memoryOk ? '✅' : '❌',
            "Current: {$memoryLimit} (Recommended: 128M+)"
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return !collect($checks)->contains(function($check) {
            return $check[1] === '❌';
        });
    }

    private function checkDatabaseCompatibility(): bool
    {
        $this->info('💾 Database Compatibility');
        $this->info('=========================');

        $checks = [];

        try {
            // Database connection
            DB::connection()->getPdo();
            $checks[] = ['Database Connection', '✅', 'Connected successfully'];

            // Database version
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            $checks[] = ['Database Version', '✅', $version];

            // Current user
            $config = config('database.connections.' . config('database.default'));
            $currentUser = $config['username'] ?? 'unknown';
            $checks[] = ['Database User', '✅', $currentUser];

            // Check privileges
            try {
                $grants = DB::select("SHOW GRANTS");
                $hasCreate = false;
                foreach ($grants as $grant) {
                    $grantText = array_values((array)$grant)[0];
                    if (str_contains($grantText, 'CREATE') || str_contains($grantText, 'ALL PRIVILEGES')) {
                        $hasCreate = true;
                        break;
                    }
                }
                $checks[] = [
                    'CREATE Privileges',
                    $hasCreate ? '✅' : '❌',
                    $hasCreate ? 'Has CREATE privilege' : 'Missing CREATE privilege'
                ];
            } catch (\Exception $e) {
                $checks[] = ['Privilege Check', '⚠️', 'Could not verify privileges'];
            }

            // Test database creation
            $testDbName = 'tenancy_test_' . time();
            try {
                DB::statement("CREATE DATABASE {$testDbName}");
                DB::statement("DROP DATABASE {$testDbName}");
                $checks[] = ['Database Creation', '✅', 'Can create/drop databases'];
            } catch (\Exception $e) {
                $checks[] = ['Database Creation', '❌', 'Cannot create databases: ' . $e->getMessage()];
            }

        } catch (\Exception $e) {
            $checks[] = ['Database Connection', '❌', 'Connection failed: ' . $e->getMessage()];
        }

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return !collect($checks)->contains(function($check) {
            return $check[1] === '❌';
        });
    }

    private function checkFileSystemPermissions(): bool
    {
        $this->info('📁 File System Permissions');
        $this->info('==========================');

        $checks = [];

        // Storage directory
        $storagePath = storage_path();
        $storageWritable = is_writable($storagePath);
        $checks[] = [
            'Storage Directory',
            $storageWritable ? '✅' : '❌',
            $storageWritable ? 'Writable' : 'Not writable'
        ];

        // Resources/views/tenant directory (for homepage creation)
        $tenantViewsPath = resource_path('views/tenant');
        if (!is_dir($tenantViewsPath)) {
            File::makeDirectory($tenantViewsPath, 0755, true);
        }
        $tenantViewsWritable = is_writable($tenantViewsPath);
        $checks[] = [
            'Tenant Views Directory',
            $tenantViewsWritable ? '✅' : '❌',
            $tenantViewsWritable ? 'Writable' : 'Not writable - needed for homepage creation'
        ];

        // Bootstrap/cache directory
        $bootstrapCachePath = base_path('bootstrap/cache');
        $bootstrapCacheWritable = is_writable($bootstrapCachePath);
        $checks[] = [
            'Bootstrap Cache',
            $bootstrapCacheWritable ? '✅' : '❌',
            $bootstrapCacheWritable ? 'Writable' : 'Not writable'
        ];

        // Config directory (for dynamic config changes)
        $configPath = base_path('config');
        $configWritable = is_writable($configPath);
        $checks[] = [
            'Config Directory',
            $configWritable ? '✅' : '⚠️',
            $configWritable ? 'Writable' : 'Read-only (dynamic config may not work)'
        ];

        // Public directory (for asset linking)
        $publicPath = base_path('public');
        $publicWritable = is_writable($publicPath);
        $checks[] = [
            'Public Directory',
            $publicWritable ? '✅' : '❌',
            $publicWritable ? 'Writable' : 'Not writable - needed for asset linking'
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return !collect($checks)->contains(function($check) {
            return $check[1] === '❌';
        });
    }

    private function checkFastPanelCompatibility(): bool
    {
        $this->info('🚀 FastPanel 2 Compatibility');
        $this->info('============================');

        $checks = [];

        // Check if we're on Ubuntu/Debian
        $osInfo = php_uname('s');
        $isLinux = stripos($osInfo, 'linux') !== false;
        $checks[] = [
            'Operating System',
            $isLinux ? '✅' : '⚠️',
            $isLinux ? 'Linux detected' : "Current: {$osInfo} (FastPanel requires Linux)"
        ];

        // Check FastPanel CLI
        $fastPanelCli = env('FASTPANEL_CLI_PATH', '/usr/local/fastpanel2/fastpanel');
        $cliExists = file_exists($fastPanelCli);
        $checks[] = [
            'FastPanel CLI',
            $cliExists ? '✅' : '❌',
            $cliExists ? "Found at {$fastPanelCli}" : "Not found at {$fastPanelCli}"
        ];

        if ($cliExists) {
            // Test CLI access
            $testCommand = "sudo {$fastPanelCli} --version 2>/dev/null";
            $output = shell_exec($testCommand);
            $cliWorking = !empty($output);
            $checks[] = [
                'FastPanel CLI Access',
                $cliWorking ? '✅' : '❌',
                $cliWorking ? 'CLI accessible' : 'CLI not accessible (sudo permissions?)'
            ];

            // Test database listing
            $dbListCommand = "sudo {$fastPanelCli} --json databases list 2>/dev/null";
            $dbOutput = shell_exec($dbListCommand);
            $dbListWorking = !empty($dbOutput) && json_decode($dbOutput) !== null;
            $checks[] = [
                'FastPanel Database Access',
                $dbListWorking ? '✅' : '❌',
                $dbListWorking ? 'Can list databases' : 'Cannot access database metadata'
            ];
        }

        // Check FastPanel metadata database
        $fastPanelDb = '/usr/local/fastpanel2/fastpanel2.db';
        $metadataDbExists = file_exists($fastPanelDb);
        $checks[] = [
            'FastPanel Metadata DB',
            $metadataDbExists ? '✅' : '❌',
            $metadataDbExists ? "Found at {$fastPanelDb}" : "Not found at {$fastPanelDb}"
        ];

        // Check environment variables
        $fastPanelUrl = env('FASTPANEL_URL');
        $fastPanelToken = env('FASTPANEL_API_TOKEN');
        $checks[] = [
            'FastPanel Config',
            ($fastPanelUrl && $fastPanelToken) ? '✅' : '⚠️',
            ($fastPanelUrl && $fastPanelToken) ? 'API credentials configured' : 'API credentials missing in .env'
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return !collect($checks)->contains(function($check) {
            return $check[1] === '❌';
        });
    }

    private function checkLocalEnvironment(): bool
    {
        $this->info('🏠 Local Development Environment');
        $this->info('================================');

        $checks = [];

        // Check if running locally
        $isLocal = app()->environment('local') || in_array(request()->ip(), ['127.0.0.1', '::1']);
        $checks[] = [
            'Environment',
            $isLocal ? '✅' : '⚠️',
            $isLocal ? 'Local environment detected' : 'Not running locally'
        ];

        // Check debug mode
        $debugMode = config('app.debug');
        $checks[] = [
            'Debug Mode',
            $debugMode ? '✅' : '⚠️',
            $debugMode ? 'Enabled (good for development)' : 'Disabled'
        ];

        // Check SQLite support (for local testing)
        $sqliteSupport = extension_loaded('pdo_sqlite');
        $checks[] = [
            'SQLite Support',
            $sqliteSupport ? '✅' : '⚠️',
            $sqliteSupport ? 'Available for local testing' : 'Not available'
        ];

        // Check local database file
        $databasePath = base_path('database/database.sqlite');
        $sqliteExists = file_exists($databasePath);
        $checks[] = [
            'Local SQLite DB',
            $sqliteExists ? '✅' : '⚠️',
            $sqliteExists ? 'SQLite database exists' : 'No SQLite database (MySQL only)'
        ];

        // Check development tools
        $composerExists = !empty(shell_exec('composer --version 2>/dev/null'));
        $checks[] = [
            'Composer',
            $composerExists ? '✅' : '⚠️',
            $composerExists ? 'Available' : 'Not found in PATH'
        ];

        $nodeExists = !empty(shell_exec('node --version 2>/dev/null'));
        $checks[] = [
            'Node.js',
            $nodeExists ? '✅' : '⚠️',
            $nodeExists ? 'Available' : 'Not found (needed for asset compilation)'
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return true; // Local checks are mostly warnings
    }

    private function checkProductionRequirements(): bool
    {
        $this->info('🌐 Production Server Requirements');
        $this->info('=================================');

        $checks = [];

        // Check if running in production
        $isProduction = app()->environment('production');
        $checks[] = [
            'Environment',
            $isProduction ? '✅' : '⚠️',
            $isProduction ? 'Production environment' : 'Not production environment'
        ];

        // Check debug mode (should be off in production)
        $debugMode = config('app.debug');
        $checks[] = [
            'Debug Mode',
            !$debugMode ? '✅' : '❌',
            !$debugMode ? 'Disabled (good for production)' : 'Enabled (security risk!)'
        ];

        // Check HTTPS
        $httpsEnabled = request()->isSecure() || env('APP_URL', '')->startsWith('https://');
        $checks[] = [
            'HTTPS',
            $httpsEnabled ? '✅' : '❌',
            $httpsEnabled ? 'Enabled' : 'Not enabled (required for production)'
        ];

        // Check opcache
        $opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled'];
        $checks[] = [
            'OPcache',
            $opcacheEnabled ? '✅' : '⚠️',
            $opcacheEnabled ? 'Enabled' : 'Disabled (recommended for production)'
        ];

        // Check cache configuration
        $cacheDriver = config('cache.default');
        $productionCacheDrivers = ['redis', 'memcached'];
        $cacheOk = in_array($cacheDriver, $productionCacheDrivers);
        $checks[] = [
            'Cache Driver',
            $cacheOk ? '✅' : '⚠️',
            "Current: {$cacheDriver} " . ($cacheOk ? '(production-ready)' : '(consider Redis/Memcached)')
        ];

        // Check queue configuration
        $queueDriver = config('queue.default');
        $productionQueueDrivers = ['redis', 'database', 'sqs'];
        $queueOk = in_array($queueDriver, $productionQueueDrivers);
        $checks[] = [
            'Queue Driver',
            $queueOk ? '✅' : '⚠️',
            "Current: {$queueDriver} " . ($queueOk ? '(production-ready)' : '(consider Redis/Database)')
        ];

        // Check session configuration
        $sessionDriver = config('session.driver');
        $productionSessionDrivers = ['redis', 'database'];
        $sessionOk = in_array($sessionDriver, $productionSessionDrivers);
        $checks[] = [
            'Session Driver',
            $sessionOk ? '✅' : '⚠️',
            "Current: {$sessionDriver} " . ($sessionOk ? '(production-ready)' : '(consider Redis/Database)')
        ];

        // Check logging
        $logChannel = config('logging.default');
        $checks[] = [
            'Logging',
            !empty($logChannel) ? '✅' : '❌',
            "Channel: {$logChannel}"
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
        $this->newLine();

        return !collect($checks)->contains(function($check) {
            return $check[1] === '❌';
        });
    }

    private function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }
}
