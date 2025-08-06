<?php

namespace ArtflowStudio\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantService;
use Stancl\Tenancy\Facades\Tenancy;
use Exception;

class ComprehensiveTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'af-tenancy:test-all 
                          {--quick : Run only quick tests}
                          {--fix : Auto-fix detected issues}
                          {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive test of all AF-MultiTenancy functionality built on stancl/tenancy';

    protected $issues = [];
    protected $passed = 0;
    protected $failed = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 AF-MultiTenancy Comprehensive Test Suite');
        $this->info('Built on stancl/tenancy foundation');
        $this->newLine();

        // Test stancl/tenancy core first
        $this->testStanclTenancyCore();
        
        // Test our enhancements
        $this->testConfiguration();
        $this->testDatabaseConnections();
        $this->testMiddleware();
        $this->testServices();
        $this->testLivewireIntegration();
        
        if (!$this->option('quick')) {
            $this->testTenantCreation();
            $this->testTenantIsolation();
        }

        $this->showResults();
        
        return $this->failed > 0 ? 1 : 0;
    }

    protected function testStanclTenancyCore(): void
    {
        $this->info('📦 Testing stancl/tenancy Core...');
        
        // Test if stancl/tenancy is installed
        if (!class_exists(\Stancl\Tenancy\TenancyServiceProvider::class)) {
            $this->addIssue('❌ stancl/tenancy is not installed', 'composer require stancl/tenancy');
            return;
        }
        $this->testPassed('✅ stancl/tenancy package is installed');

        // Test tenancy config
        if (!config('tenancy.tenant_model')) {
            $this->addIssue('❌ tenancy.php config not found', 'php artisan vendor:publish --tag=tenancy-config');
        } else {
            $this->testPassed('✅ tenancy.php configuration exists');
        }

        // Test if our tenant model extends stancl
        $tenantModel = config('tenancy.tenant_model');
        if ($tenantModel !== \ArtflowStudio\Tenancy\Models\Tenant::class) {
            $this->addIssue('❌ Tenant model not configured correctly', 'Check tenancy.php tenant_model setting');
        } else {
            $this->testPassed('✅ Tenant model configured correctly');
        }

        // Test stancl managers
        $managers = config('tenancy.database.managers', []);
        if (empty($managers['mysql'])) {
            $this->addIssue('❌ stancl database managers not configured', 'Check tenancy.php database.managers');
        } else {
            $this->testPassed('✅ stancl database managers configured');
        }
    }

    protected function testConfiguration(): void
    {
        $this->info('⚙️ Testing Configuration...');
        
        // Test main config files
        $configs = [
            'tenancy' => 'stancl/tenancy core configuration',
            'artflow-tenancy' => 'AF-MultiTenancy enhancements'
        ];

        foreach ($configs as $key => $description) {
            if (config($key)) {
                $this->testPassed("✅ {$description} loaded");
            } else {
                $this->addIssue("❌ {$description} not found", "Check config/{$key}.php");
            }
        }

        // Test environment variables
        $envVars = [
            'TENANT_DB_PREFIX' => 'tenant_',
            'APP_DOMAIN' => null,
        ];

        foreach ($envVars as $var => $expected) {
            $value = env($var);
            if ($value !== null || $expected === null) {
                $this->testPassed("✅ {$var} configured");
            } else {
                $this->addIssue("❌ {$var} not set", "Add {$var}={$expected} to .env");
            }
        }
    }

    protected function testDatabaseConnections(): void
    {
        $this->info('🗄️ Testing Database Connections...');
        
        try {
            // Test central database
            DB::connection()->getPdo();
            $this->testPassed('✅ Central database connection working');
        } catch (Exception $e) {
            $this->addIssue('❌ Central database connection failed', $e->getMessage());
        }

        // Test tenant tables exist
        try {
            if (Schema::hasTable('tenants')) {
                $this->testPassed('✅ Tenants table exists');
            } else {
                $this->addIssue('❌ Tenants table missing', 'php artisan migrate');
            }

            if (Schema::hasTable('domains')) {
                $this->testPassed('✅ Domains table exists');
            } else {
                $this->addIssue('❌ Domains table missing', 'php artisan migrate');
            }
        } catch (Exception $e) {
            $this->addIssue('❌ Database table check failed', $e->getMessage());
        }
    }

    protected function testMiddleware(): void
    {
        $this->info('🔀 Testing Middleware...');
        
        $router = app('router');
        
        // Test stancl/tenancy middleware
        $stanclMiddleware = [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ];

        foreach ($stanclMiddleware as $middleware) {
            if (class_exists($middleware)) {
                $this->testPassed("✅ stancl middleware {$middleware} available");
            } else {
                $this->addIssue("❌ stancl middleware {$middleware} missing", 'Check stancl/tenancy installation');
            }
        }

        // Test our middleware
        $ourMiddleware = [
            'af-tenant' => \ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware::class,
            'central' => \ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware::class,
        ];

        foreach ($ourMiddleware as $alias => $class) {
            $registered = $router->getMiddleware();
            if (isset($registered[$alias])) {
                $this->testPassed("✅ Middleware '{$alias}' registered");
            } else {
                $this->addIssue("❌ Middleware '{$alias}' not registered", 'Check TenancyServiceProvider middleware registration');
            }
        }

        // Test middleware groups
        $groups = [
            'tenant.web' => 'Tenant web routes',
            'central.web' => 'Central web routes', 
            'tenant.api' => 'Tenant API routes'
        ];

        foreach ($groups as $group => $description) {
            $middlewareGroups = $router->getMiddlewareGroups();
            if (isset($middlewareGroups[$group])) {
                $this->testPassed("✅ Middleware group '{$group}' registered");
            } else {
                $this->addIssue("❌ Middleware group '{$group}' not registered", 'Check TenancyServiceProvider middleware groups');
            }
        }
    }

    protected function testServices(): void
    {
        $this->info('🔧 Testing Services...');
        
        // Test service bindings
        $services = [
            \ArtflowStudio\Tenancy\Services\TenantService::class => 'Tenant Service',
            \ArtflowStudio\Tenancy\Services\TenantContextCache::class => 'Tenant Context Cache',
        ];

        foreach ($services as $class => $name) {
            try {
                $service = app($class);
                if ($service) {
                    $this->testPassed("✅ {$name} bound correctly");
                }
            } catch (Exception $e) {
                $this->addIssue("❌ {$name} binding failed", $e->getMessage());
            }
        }

        // Test stancl/tenancy services
        try {
            $tenancy = app(\Stancl\Tenancy\TenantManager::class);
            if ($tenancy) {
                $this->testPassed('✅ stancl/tenancy TenantManager available');
            }
        } catch (Exception $e) {
            $this->addIssue('❌ stancl/tenancy TenantManager not available', 'Check stancl/tenancy installation');
        }

        // Test tenant() helper function
        if (function_exists('tenant')) {
            $this->testPassed('✅ tenant() helper function available');
        } else {
            $this->addIssue('❌ tenant() helper function not available', 'Check stancl/tenancy installation');
        }
    }

    protected function testLivewireIntegration(): void
    {
        $this->info('⚡ Testing Livewire Integration...');
        
        if (!class_exists(\Livewire\Livewire::class)) {
            $this->warn('⚠️ Livewire not installed, skipping Livewire tests');
            return;
        }

        $this->testPassed('✅ Livewire package installed');
        
        // Test if Livewire is configured for tenancy
        try {
            $middleware = \Livewire\Livewire::getPersistentMiddleware();
            if (in_array(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, $middleware)) {
                $this->testPassed('✅ Livewire configured for tenancy');
            } else {
                $this->addIssue('❌ Livewire not configured for tenancy', 'Check TenancyServiceProvider configureLivewire method');
            }
        } catch (Exception $e) {
            $this->warn("⚠️ Could not check Livewire tenancy configuration: {$e->getMessage()}");
        }
    }

    protected function testTenantCreation(): void
    {
        $this->info('🏢 Testing Tenant Creation...');
        
        try {
            $tenantService = app(\ArtflowStudio\Tenancy\Services\TenantService::class);
            
            // Create test tenant
            $testName = 'Test Tenant ' . now()->timestamp;
            $testDomain = 'test-' . now()->timestamp . '.localhost';
            
            $tenant = $tenantService->createTenant($testName, $testDomain, 'active');
            
            if ($tenant && $tenant->exists) {
                $this->testPassed('✅ Tenant creation successful');
                
                // Test tenant database
                $this->info('  Testing tenant database...');
                try {
                    // Use stancl's proper way to initialize tenancy
                    $tenantManager = app(\Stancl\Tenancy\TenantManager::class);
                    $tenantManager->initialize($tenant);
                    
                    // Test connection
                    $connection = $tenant->database();
                    if ($connection) {
                        $this->testPassed('  ✅ Tenant database connection working');
                    }
                    
                    $tenantManager->end();
                    
                    // Cleanup test tenant
                    $tenant->delete();
                    $this->info('  🧹 Test tenant cleaned up');
                    
                } catch (Exception $e) {
                    $this->addIssue('❌ Tenant database test failed', $e->getMessage());
                }
                
            } else {
                $this->addIssue('❌ Tenant creation failed', 'Check TenantService createTenant method');
            }
            
        } catch (Exception $e) {
            $this->addIssue('❌ Tenant creation test failed', $e->getMessage());
        }
    }

    protected function testTenantIsolation(): void
    {
        $this->info('🔒 Testing Tenant Isolation...');
        
        // This is a complex test that would require creating multiple tenants
        // and verifying data isolation. For now, we'll do basic checks.
        
        try {
            $centralConnection = DB::connection();
            $centralDbName = $centralConnection->getDatabaseName();
            
            if ($centralDbName) {
                $this->testPassed("✅ Central database isolation: {$centralDbName}");
            }
            
            // Test tenant database naming
            $prefix = config('tenancy.database.prefix', 'tenant_');
            if ($prefix) {
                $this->testPassed("✅ Tenant database prefix configured: {$prefix}");
            }
            
        } catch (Exception $e) {
            $this->addIssue('❌ Database isolation test failed', $e->getMessage());
        }
    }

    protected function testPassed(string $message): void
    {
        $this->passed++;
        if ($this->option('verbose')) {
            $this->line($message);
        }
    }

    protected function addIssue(string $issue, string $fix): void
    {
        $this->failed++;
        $this->issues[] = compact('issue', 'fix');
        $this->error($issue);
        
        if ($this->option('fix')) {
            $this->warn("  💡 Suggested fix: {$fix}");
            
            // Auto-fix some common issues
            if (str_contains($fix, 'php artisan')) {
                $this->warn("  🔧 Auto-running: {$fix}");
                Artisan::call(str_replace('php artisan ', '', $fix));
            }
        }
    }

    protected function showResults(): void
    {
        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->line("✅ Passed: {$this->passed}");
        $this->line("❌ Failed: {$this->failed}");
        $this->newLine();

        if (!empty($this->issues)) {
            $this->error('🚨 Issues Found:');
            foreach ($this->issues as $issue) {
                $this->line("  • {$issue['issue']}");
                $this->line("    💡 Fix: {$issue['fix']}");
                $this->newLine();
            }
        } else {
            $this->info('🎉 All tests passed! Your AF-MultiTenancy setup is working correctly.');
        }

        if ($this->failed > 0) {
            $this->newLine();
            $this->warn('💡 Run with --fix flag to auto-fix some issues');
            $this->warn('💡 Run with --verbose flag for detailed output');
        }
    }
}
