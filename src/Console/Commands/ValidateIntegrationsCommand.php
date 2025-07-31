<?php

namespace ArtflowStudio\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Http\Controllers\Api\MultiProjectApiController;

class ValidateIntegrationsCommand extends Command
{
    protected $signature = 'tenancy:validate-integrations 
                           {--fix : Attempt to fix issues automatically}';

    protected $description = 'Validate all Horizon, Telescope, Octane, and Livewire integrations';

    public function handle(): int
    {
        $this->info('🔍 Validating Artflow Studio Tenancy Integrations...');
        $this->newLine();

        $allPassed = true;
        $issues = [];

        // Test database connectivity
        $allPassed &= $this->testDatabaseConnectivity($issues);
        
        // Test Redis connectivity (for Horizon/Octane)
        $allPassed &= $this->testRedisConnectivity($issues);
        
        // Test Telescope integration
        $allPassed &= $this->testTelescopeIntegration($issues);
        
        // Test Horizon integration
        $allPassed &= $this->testHorizonIntegration($issues);
        
        // Test Octane integration
        $allPassed &= $this->testOctaneIntegration($issues);
        
        // Test Livewire components
        $allPassed &= $this->testLivewireComponents($issues);
        
        // Test API routes
        $allPassed &= $this->testApiRoutes($issues);
        
        // Test multi-project features
        $allPassed &= $this->testMultiProjectFeatures($issues);

        $this->newLine();
        $this->displayResults($allPassed, $issues);

        return $allPassed ? 0 : 1;
    }

    protected function testDatabaseConnectivity(array &$issues): bool
    {
        $this->info('🔗 Testing Database Connectivity...');
        
        try {
            DB::connection()->getPdo();
            $this->line('   ✅ Central database connection: OK');
            
            // Test tenant database creation capability
            $testResult = DB::select('SELECT 1 as test');
            if (!empty($testResult)) {
                $this->line('   ✅ Database queries: OK');
                return true;
            }
        } catch (\Exception $e) {
            $issues[] = "Database connectivity failed: {$e->getMessage()}";
            $this->line("   ❌ Database connection failed: {$e->getMessage()}");
            return false;
        }
        
        return true;
    }

    protected function testRedisConnectivity(array &$issues): bool
    {
        $this->info('🔗 Testing Redis Connectivity...');
        
        try {
            if (config('queue.default') === 'redis') {
                Queue::connection()->size('default');
                $this->line('   ✅ Redis queue connection: OK');
            } else {
                $this->line('   ⚠️  Redis queue not configured (queue driver: ' . config('queue.default') . ')');
            }
            
            // Test Redis cache
            if (config('cache.default') === 'redis') {
                Cache::put('tenancy_test', 'test_value', 60);
                $value = Cache::get('tenancy_test');
                if ($value === 'test_value') {
                    $this->line('   ✅ Redis cache connection: OK');
                    Cache::forget('tenancy_test');
                } else {
                    $issues[] = 'Redis cache test failed';
                    $this->line('   ❌ Redis cache test failed');
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            $issues[] = "Redis connectivity failed: {$e->getMessage()}";
            $this->line("   ❌ Redis connection failed: {$e->getMessage()}");
            return false;
        }
    }

    protected function testTelescopeIntegration(array &$issues): bool
    {
        $this->info('🔭 Testing Telescope Integration...');
        
        $telescopeEnabled = config('artflow-tenancy.integrations.telescope.enabled', false);
        
        if (!$telescopeEnabled) {
            $this->line('   ⚠️  Telescope integration disabled in config');
            return true;
        }
        
        // Check if Telescope package is installed
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            $issues[] = 'Laravel Telescope package not installed';
            $this->line('   ❌ Laravel Telescope package not installed');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Run: composer require laravel/telescope');
            }
            return false;
        }
        
        // Check if EnhancedTelescopeTags feature is enabled
        $features = config('tenancy.features', []);
        $hasEnhancedTelescope = in_array(\ArtflowStudio\Tenancy\Features\EnhancedTelescopeTags::class, $features);
        
        if (!$hasEnhancedTelescope) {
            $issues[] = 'EnhancedTelescopeTags feature not enabled in tenancy config';
            $this->line('   ❌ EnhancedTelescopeTags feature not enabled');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Add \\ArtflowStudio\\Tenancy\\Features\\EnhancedTelescopeTags::class to config/tenancy.php features array');
            }
            return false;
        }
        
        $this->line('   ✅ Telescope integration: OK');
        return true;
    }

    protected function testHorizonIntegration(array &$issues): bool
    {
        $this->info('🏔️ Testing Horizon Integration...');
        
        $horizonEnabled = config('artflow-tenancy.integrations.horizon.enabled', false);
        
        if (!$horizonEnabled) {
            $this->line('   ⚠️  Horizon integration disabled in config');
            return true;
        }
        
        // Check if Horizon package is installed
        if (!class_exists(\Laravel\Horizon\Horizon::class)) {
            $issues[] = 'Laravel Horizon package not installed';
            $this->line('   ❌ Laravel Horizon package not installed');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Run: composer require laravel/horizon');
            }
            return false;
        }
        
        // Check if HorizonTags feature is enabled
        $features = config('tenancy.features', []);
        $hasHorizonTags = in_array(\ArtflowStudio\Tenancy\Features\HorizonTags::class, $features);
        
        if (!$hasHorizonTags) {
            $issues[] = 'HorizonTags feature not enabled in tenancy config';
            $this->line('   ❌ HorizonTags feature not enabled');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Add \\ArtflowStudio\\Tenancy\\Features\\HorizonTags::class to config/tenancy.php features array');
            }
            return false;
        }
        
        $this->line('   ✅ Horizon integration: OK');
        return true;
    }

    protected function testOctaneIntegration(array &$issues): bool
    {
        $this->info('🚀 Testing Octane Integration...');
        
        $octaneEnabled = config('artflow-tenancy.integrations.octane.enabled', false);
        
        if (!$octaneEnabled) {
            $this->line('   ⚠️  Octane integration disabled in config');
            return true;
        }
        
        // Check if Octane package is installed
        if (!class_exists(\Laravel\Octane\Octane::class)) {
            $issues[] = 'Laravel Octane package not installed';
            $this->line('   ❌ Laravel Octane package not installed');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Run: composer require laravel/octane');
            }
            return false;
        }
        
        // Check if OctaneIntegration feature is enabled
        $features = config('tenancy.features', []);
        $hasOctaneIntegration = in_array(\ArtflowStudio\Tenancy\Features\OctaneIntegration::class, $features);
        
        if (!$hasOctaneIntegration) {
            $issues[] = 'OctaneIntegration feature not enabled in tenancy config';
            $this->line('   ❌ OctaneIntegration feature not enabled');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Add \\ArtflowStudio\\Tenancy\\Features\\OctaneIntegration::class to config/tenancy.php features array');
            }
            return false;
        }
        
        $this->line('   ✅ Octane integration: OK');
        return true;
    }

    protected function testLivewireComponents(array &$issues): bool
    {
        $this->info('⚡ Testing Livewire Components...');
        
        // Check if Livewire is installed
        if (!class_exists(\Livewire\Livewire::class)) {
            $issues[] = 'Livewire package not installed';
            $this->line('   ❌ Livewire package not installed');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Run: composer require livewire/livewire');
            }
            return false;
        }
        
        // Test if key Livewire components exist
        $components = [
            'Dashboard' => \ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard::class,
            'TenantsIndex' => \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex::class,
            'CreateTenant' => \ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant::class,
            'ViewTenant' => \ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant::class,
        ];
        
        foreach ($components as $name => $class) {
            if (!class_exists($class)) {
                $issues[] = "Livewire component {$name} not found: {$class}";
                $this->line("   ❌ {$name} component missing");
                return false;
            }
        }
        
        $this->line('   ✅ Livewire components: OK');
        return true;
    }

    protected function testApiRoutes(array &$issues): bool
    {
        $this->info('🌐 Testing API Routes...');
        
        $requiredRoutes = [
            'api.tenancy.tenants.index',
            'api.tenancy.tenants.store', 
            'api.tenancy.tenants.show',
            'api.tenancy.health',
            'api.tenancy.stats',
        ];
        
        $allRoutesExist = true;
        
        foreach ($requiredRoutes as $routeName) {
            if (!Route::has($routeName)) {
                $issues[] = "API route missing: {$routeName}";
                $this->line("   ❌ Route missing: {$routeName}");
                $allRoutesExist = false;
            }
        }
        
        if ($allRoutesExist) {
            $this->line('   ✅ API routes: OK');
        }
        
        return $allRoutesExist;
    }

    protected function testMultiProjectFeatures(array &$issues): bool
    {
        $this->info('🏢 Testing Multi-Project Features...');
        
        $dashboardEnabled = config('artflow-tenancy.dashboard.enabled', false);
        
        if (!$dashboardEnabled) {
            $this->line('   ⚠️  Multi-project dashboard disabled in config');
            return true;
        }
        
        // Check required config values
        $projectId = config('artflow-tenancy.project.id');
        $projectName = config('artflow-tenancy.project.name');
        $apiKey = config('artflow-tenancy.project.api_key');
        
        if (!$projectId || !$projectName || !$apiKey) {
            $issues[] = 'Multi-project configuration incomplete (missing project.id, project.name, or project.api_key)';
            $this->line('   ❌ Multi-project configuration incomplete');
            
            if ($this->option('fix')) {
                $this->line('   🔧 Set ARTFLOW_PROJECT_ID, ARTFLOW_PROJECT_NAME, ARTFLOW_PROJECT_API_KEY in .env');
            }
            return false;
        }
        
        // Test MultiProjectDashboardService
        try {
            $service = app(\ArtflowStudio\Tenancy\Services\MultiProjectDashboardService::class);
            $this->line('   ✅ MultiProjectDashboardService: OK');
        } catch (\Exception $e) {
            $issues[] = "MultiProjectDashboardService failed: {$e->getMessage()}";
            $this->line("   ❌ MultiProjectDashboardService failed: {$e->getMessage()}");
            return false;
        }
        
        $this->line('   ✅ Multi-project features: OK');
        return true;
    }

    protected function displayResults(bool $allPassed, array $issues): void
    {
        if ($allPassed) {
            $this->info('🎉 All integrations validated successfully!');
            $this->newLine();
            $this->info('Next steps:');
            $this->line('• Test creating a tenant via Livewire interface');
            $this->line('• Check Telescope dashboard for tenant-tagged entries');
            $this->line('• Monitor Horizon for tenant-tagged jobs');
            $this->line('• Test Octane with: php artisan octane:start');
        } else {
            $this->error('❌ Validation failed. Issues found:');
            $this->newLine();
            
            foreach ($issues as $issue) {
                $this->line("• {$issue}");
            }
            
            $this->newLine();
            $this->info('💡 Run with --fix flag to see suggested fixes:');
            $this->line('php artisan tenancy:validate-integrations --fix');
        }
    }
}
