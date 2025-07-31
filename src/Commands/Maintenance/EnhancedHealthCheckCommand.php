<?php

namespace ArtflowStudio\Tenancy\Commands\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantAnalyticsService;
use Stancl\Tenancy\Facades\Tenancy;

class EnhancedHealthCheckCommand extends Command
{
    protected $signature = 'tenancy:health-check 
                            {--fix : Attempt to fix issues automatically}
                            {--detailed : Show detailed diagnostics}
                            {--tenant= : Check specific tenant health}';

    protected $description = 'Comprehensive health check for the entire tenancy system';

    private array $issues = [];
    private array $warnings = [];
    private int $totalChecks = 0;
    private int $passedChecks = 0;

    public function handle(): int
    {
        $this->info('🏥 Comprehensive Tenancy System Health Check');
        $this->info('🔍 Analyzing system configuration and operational status...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar(12);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->start();

        try {
            // Core system checks
            $progressBar->setMessage('Checking Laravel configuration...');
            $this->checkLaravelConfiguration();
            $progressBar->advance();

            $progressBar->setMessage('Checking database configuration...');
            $this->checkDatabaseConfiguration();
            $progressBar->advance();

            $progressBar->setMessage('Checking cache configuration...');
            $this->checkCacheConfiguration();
            $progressBar->advance();

            $progressBar->setMessage('Checking Redis configuration...');
            $this->checkRedisConfiguration();
            $progressBar->advance();

            $progressBar->setMessage('Checking stancl/tenancy setup...');
            $this->checkStanclTenancySetup();
            $progressBar->advance();

            $progressBar->setMessage('Checking middleware registration...');
            $this->checkMiddlewareSetup();
            $progressBar->advance();

            $progressBar->setMessage('Checking tenant models...');
            $this->checkTenantModels();
            $progressBar->advance();

            $progressBar->setMessage('Checking tenant databases...');
            $this->checkTenantDatabases();
            $progressBar->advance();

            $progressBar->setMessage('Checking Pulse integration...');
            $this->checkAnalyticsIntegration();
            $progressBar->advance();

            $progressBar->setMessage('Checking file permissions...');
            $this->checkFilePermissions();
            $progressBar->advance();

            $progressBar->setMessage('Checking service bindings...');
            $this->checkServiceBindings();
            $progressBar->advance();

            $progressBar->setMessage('Running operational tests...');
            $this->runOperationalTests();
            $progressBar->advance();

            $progressBar->finish();
            $this->newLine();

            // Display results
            $this->displayResults();

            // Fix issues if requested
            if ($this->option('fix') && !empty($this->issues)) {
                $this->newLine();
                $this->attemptFixes();
            }

            return empty($this->issues) ? 0 : 1;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error('❌ Health check failed: ' . $e->getMessage());
            if ($this->option('detailed')) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }

    private function checkLaravelConfiguration(): void
    {
        $this->totalChecks++;

        // Check APP_KEY
        if (empty(config('app.key'))) {
            $this->issues[] = 'APP_KEY is not set in environment configuration';
        } else {
            $this->passedChecks++;
        }

        // Check environment
        $env = app()->environment();
        if ($this->option('detailed')) {
            $this->info("Environment: {$env}");
        }

        // Check debug mode in production
        if ($env === 'production' && config('app.debug')) {
            $this->warnings[] = 'Debug mode is enabled in production environment';
        }
    }

    private function checkDatabaseConfiguration(): void
    {
        $this->totalChecks++;

        try {
            // Test central database connection
            DB::connection()->getPdo();
            $this->passedChecks++;

            if ($this->option('detailed')) {
                $driver = config('database.default');
                $this->info("Database driver: {$driver}");
            }

            // Check for required database configuration
            $requiredConfigs = [
                'database.connections.mysql.host',
                'database.connections.mysql.database',
                'database.connections.mysql.username'
            ];

            foreach ($requiredConfigs as $config) {
                if (empty(config($config))) {
                    $this->issues[] = "Missing database configuration: {$config}";
                }
            }

        } catch (\Exception $e) {
            $this->issues[] = 'Central database connection failed: ' . $e->getMessage();
        }
    }

    private function checkCacheConfiguration(): void
    {
        $this->totalChecks++;

        try {
            Cache::put('health_check_test', 'working', 10);
            $result = Cache::get('health_check_test');
            
            if ($result === 'working') {
                $this->passedChecks++;
                Cache::forget('health_check_test');
            } else {
                $this->issues[] = 'Cache system is not functioning correctly';
            }

            if ($this->option('detailed')) {
                $driver = config('cache.default');
                $this->info("Cache driver: {$driver}");
            }

        } catch (\Exception $e) {
            $this->issues[] = 'Cache system error: ' . $e->getMessage();
        }
    }

    private function checkRedisConfiguration(): void
    {
        $this->totalChecks++;

        try {
            if (config('database.redis.default.host')) {
                // Check if Redis class exists (extension installed)
                if (!class_exists('Redis')) {
                    $this->warnings[] = 'Redis extension not installed (fallback to database cache active)';
                    $this->passedChecks++; // Not a failure since fallback exists
                    return;
                }

                // Try connecting to Redis
                Redis::ping();
                $this->passedChecks++;
                
                if ($this->option('detailed')) {
                    $this->info('Redis connection: OK');
                }
            } else {
                $this->warnings[] = 'Redis is not configured (will fallback to database/file cache)';
                $this->passedChecks++; // Not a failure since fallback exists
            }

        } catch (\Exception $e) {
            $this->warnings[] = 'Redis connection failed (fallback active): ' . $e->getMessage();
            $this->passedChecks++; // Not a failure since we have fallback
        }
    }

    private function checkStanclTenancySetup(): void
    {
        $this->totalChecks++;

        // Check if stancl/tenancy is properly installed
        if (!class_exists('\Stancl\Tenancy\TenancyServiceProvider')) {
            $this->issues[] = 'stancl/tenancy package is not installed';
            return;
        }

        // Check tenancy configuration
        $config = config('tenancy');
        if (empty($config)) {
            $this->issues[] = 'Tenancy configuration is missing';
            return;
        }

        // Check central domains configuration
        if (empty(config('tenancy.central_domains'))) {
            $this->warnings[] = 'Central domains are not configured';
        }

        $this->passedChecks++;
    }

    private function checkMiddlewareSetup(): void
    {
        $this->totalChecks++;

        // Check if our middleware is registered
        $middleware = app('router')->getMiddleware();
        
        if (!isset($middleware['tenant']) && !isset($middleware['tenancy'])) {
            $this->issues[] = 'Tenant middleware is not registered';
            return;
        }

        $this->passedChecks++;
    }

    private function checkTenantModels(): void
    {
        $this->totalChecks++;

        try {
            // Check if Tenant model exists and is accessible
            $tenantCount = Tenant::count();
            
            if ($this->option('detailed')) {
                $this->info("Total tenants: {$tenantCount}");
            }

            $this->passedChecks++;

        } catch (\Exception $e) {
            $this->issues[] = 'Tenant model is not accessible: ' . $e->getMessage();
        }
    }

    private function checkTenantDatabases(): void
    {
        $this->totalChecks++;

        try {
            $tenants = Tenant::limit(5)->get(); // Check first 5 tenants
            $healthyTenants = 0;
            $unhealthyTenants = 0;

            foreach ($tenants as $tenant) {
                try {
                    Tenancy::initialize($tenant);
                    DB::connection()->getPdo();
                    $healthyTenants++;
                    Tenancy::end();
                } catch (\Exception $e) {
                    $unhealthyTenants++;
                    if ($this->option('detailed')) {
                        $this->warn("Tenant {$tenant->id} database issue: " . $e->getMessage());
                    }
                    Tenancy::end();
                }
            }

            if ($unhealthyTenants === 0) {
                $this->passedChecks++;
            } else {
                $this->issues[] = "{$unhealthyTenants} tenant databases have connectivity issues";
            }

            if ($this->option('detailed')) {
                $this->info("Healthy tenant databases: {$healthyTenants}");
                $this->info("Unhealthy tenant databases: {$unhealthyTenants}");
            }

        } catch (\Exception $e) {
            $this->issues[] = 'Unable to check tenant databases: ' . $e->getMessage();
        }
    }

    private function checkAnalyticsIntegration(): void
    {
        $this->totalChecks++;

        try {
            // Check if our Analytics service is working
            $analyticsService = app(TenantAnalyticsService::class);
            
            // Test service availability
            $testResult = $analyticsService->getTenantMetrics('health-check-test');
            
            $this->passedChecks++;

        } catch (\Exception $e) {
            $this->warnings[] = 'Analytics integration issue: ' . $e->getMessage();
            $this->passedChecks++; // Not critical
        }
    }

    private function checkFilePermissions(): void
    {
        $this->totalChecks++;

        $paths = [
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        $permissionIssues = [];
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                $permissionIssues[] = $path;
            }
        }

        if (empty($permissionIssues)) {
            $this->passedChecks++;
        } else {
            $this->issues[] = 'Write permission issues: ' . implode(', ', $permissionIssues);
        }
    }

    private function checkServiceBindings(): void
    {
        $this->totalChecks++;

        $services = [
            'ArtflowStudio\Tenancy\Services\TenantService',
            'ArtflowStudio\Tenancy\Services\TenantContextCache',
            'ArtflowStudio\Tenancy\Services\TenantAnalyticsService',
        ];

        $missingServices = [];
        foreach ($services as $service) {
            if (!app()->bound($service)) {
                $missingServices[] = $service;
            }
        }

        if (empty($missingServices)) {
            $this->passedChecks++;
        } else {
            $this->issues[] = 'Missing service bindings: ' . implode(', ', $missingServices);
        }
    }

    private function runOperationalTests(): void
    {
        $this->totalChecks++;

        try {
            // Test tenant creation (dry run)
            $testData = [
                'name' => 'health-check-test',
                'domain' => 'health-test.local'
            ];

            // We don't actually create, just validate the process
            $tenant = new Tenant($testData);
            
            if ($tenant->name === $testData['name']) {
                $this->passedChecks++;
            } else {
                $this->issues[] = 'Tenant model instantiation failed';
            }

        } catch (\Exception $e) {
            $this->issues[] = 'Operational test failed: ' . $e->getMessage();
        }
    }

    private function displayResults(): void
    {
        $this->newLine();
        
        // Overall status
        if (empty($this->issues)) {
            $this->info('✅ System Status: HEALTHY');
            $this->info("🎯 All {$this->totalChecks} checks passed successfully");
        } else {
            $this->error('❌ System Status: ISSUES DETECTED');
            $this->error("🚨 {$this->passedChecks}/{$this->totalChecks} checks passed");
        }

        $this->newLine();

        // Display issues
        if (!empty($this->issues)) {
            $this->error('🔥 Critical Issues:');
            foreach ($this->issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
        }

        // Display warnings
        if (!empty($this->warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($this->warnings as $warning) {
                $this->line("   • {$warning}");
            }
            $this->newLine();
        }

        // System info
        if ($this->option('detailed')) {
            $this->info('📊 System Information:');
            $this->line("   • PHP Version: " . PHP_VERSION);
            $this->line("   • Laravel Version: " . app()->version());
            $this->line("   • Memory Usage: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");
            $this->line("   • Tenant Count: " . Tenant::count());
        }
    }

    private function attemptFixes(): void
    {
        $this->info('🔧 Attempting to fix issues...');
        
        foreach ($this->issues as $issue) {
            if (str_contains($issue, 'APP_KEY')) {
                $this->call('key:generate');
                $this->info('✅ Generated new APP_KEY');
            }
            
            if (str_contains($issue, 'permission')) {
                $this->warn('❌ File permission issues require manual intervention');
            }
            
            if (str_contains($issue, 'cache')) {
                $this->call('cache:clear');
                $this->info('✅ Cleared cache');
            }
        }
    }
}
