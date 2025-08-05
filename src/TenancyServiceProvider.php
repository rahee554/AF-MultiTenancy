<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Commands\CreateTestTenantsCommand;
use ArtflowStudio\Tenancy\Commands\TestPerformanceCommand;
use ArtflowStudio\Tenancy\Commands\HealthCheckCommand;
use ArtflowStudio\Tenancy\Commands\ComprehensiveTenancyTestCommand;
use ArtflowStudio\Tenancy\Commands\InstallTenancyCommand;
use ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolver;
use ArtflowStudio\Tenancy\Http\Middleware\SmartTenancyInitializer;
use ArtflowStudio\Tenancy\Http\Middleware\SimpleTenantMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware;
use ArtflowStudio\Tenancy\Commands\WarmUpCacheCommand;
use ArtflowStudio\Tenancy\Commands\DiagnoseDatabaseCommand;
use ArtflowStudio\Tenancy\Commands\FixTenantDatabasesCommand;
use ArtflowStudio\Tenancy\Commands\ValidateTenancySystemCommand;
use ArtflowStudio\Tenancy\Commands\EnhancedTestPerformanceCommand;
use ArtflowStudio\Tenancy\Commands\TenantIsolationTestCommand;
use ArtflowStudio\Tenancy\Commands\TenantConnectionTestCommand;
use ArtflowStudio\Tenancy\Commands\TenantStressTestCommand;
use ArtflowStudio\Tenancy\Commands\TestMiddlewareCommand;
use ArtflowStudio\Tenancy\Commands\CheckRouteConfigCommand;
use ArtflowStudio\Tenancy\Console\Commands\TestTenantAuthentication;
use ArtflowStudio\Tenancy\Console\Commands\DebugAuthenticationFlow;
use ArtflowStudio\Tenancy\Console\Commands\TestAuthContext;
use ArtflowStudio\Tenancy\Console\Commands\AddTenantAwareToModels;
use ArtflowStudio\Tenancy\Console\Commands\ComprehensiveDatabaseTest;
use ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware;
use ArtflowStudio\Tenancy\Database\DynamicDatabaseConfigManager;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register stancl/tenancy service provider first
        $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);
        
        // Register our event service provider for tenancy events
        $this->app->register(\ArtflowStudio\Tenancy\Providers\EventServiceProvider::class);
        
        // Register stancl/tenancy configuration first
        $this->mergeConfigFrom(__DIR__ . '/../config/stancl-tenancy.php', 'tenancy');
        
        // Bind TenantService
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Bind high-performance database manager (after stancl registration)
        $this->app->singleton(
            \Stancl\Tenancy\Contracts\TenantDatabaseManager::class,
            \ArtflowStudio\Tenancy\Database\HighPerformanceMySQLDatabaseManager::class
        );

        // Merge our configuration with defaults
        $this->mergeConfigFrom(__DIR__ . '/../config/artflow-tenancy.php', 'artflow-tenancy');
        
        // CRITICAL: Register middleware aliases EARLY in register() method to avoid "Target class [universal] does not exist" errors
        $this->registerMiddlewareEarly();
        
        // DISABLE automatic dynamic database configuration to prevent conflicts
        // Use the DiagnoseDatabaseCommand --fix flag to apply when needed
        // DynamicDatabaseConfigManager::initialize();
    }

    /**
     * Register middleware aliases early to prevent "Target class [universal] does not exist" errors
     */
    protected function registerMiddlewareEarly(): void
    {
        // CRITICAL: Register universal middleware alias IMMEDIATELY in register() method
        // This prevents "Target class [universal] does not exist" errors with Livewire
        $router = $this->app['router'];
        $router->aliasMiddleware('universal', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
        
        // Also register tenant middleware early
        $router->aliasMiddleware('tenant', \ArtflowStudio\Tenancy\Http\Middleware\SimpleTenantMiddleware::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config files with performance optimizations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
            ], 'artflow-tenancy-config');
            
            $this->publishes([
                __DIR__ . '/../config/stancl-tenancy.php' => config_path('tenancy.php'),
            ], 'tenancy-config');
            
            $this->publishes([
                __DIR__ . '/../docs' => base_path('docs/tenancy'),
            ], 'tenancy-docs');
            
            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/tenancy'),
            ], 'tenancy-stubs');
            
            // Publish all tenancy files at once
            $this->publishes([
                __DIR__ . '/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
                __DIR__ . '/../config/stancl-tenancy.php' => config_path('tenancy.php'),
                __DIR__ . '/../docs' => base_path('docs/tenancy'),
                __DIR__ . '/../stubs' => base_path('stubs/tenancy'),
            ], 'tenancy-all');
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantCommand::class,
                CreateTestTenantsCommand::class,
                TestPerformanceCommand::class,
                HealthCheckCommand::class,
                ComprehensiveTenancyTestCommand::class,
                InstallTenancyCommand::class,
                WarmUpCacheCommand::class,
                DiagnoseDatabaseCommand::class,
                FixTenantDatabasesCommand::class,
                ValidateTenancySystemCommand::class,
                EnhancedTestPerformanceCommand::class,
                TenantIsolationTestCommand::class,
                TenantConnectionTestCommand::class,
                TenantStressTestCommand::class,
                TestMiddlewareCommand::class,
                CheckRouteConfigCommand::class,
                TestTenantAuthentication::class,
                DebugAuthenticationFlow::class,
                TestAuthContext::class,
                AddTenantAwareToModels::class,
                ComprehensiveDatabaseTest::class,
            ]);
        }

        // Load routes
        $this->loadRoutes();
        
        // Register middleware
        $this->registerMiddleware();
        
        // Configure Livewire for tenancy
        $this->configureLivewireForTenancy();
        
        // Configure cached lookup for performance
        $this->configureCachedLookup();
        
        // Auto-setup if needed
        $this->autoSetup();
    }

    /**
     * Auto-register middleware
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        
        // Register simplified tenant middleware
        $router->aliasMiddleware('tenant', SimpleTenantMiddleware::class);
        
        // Register CRITICAL universal middleware for stancl/tenancy UniversalRoutes feature
        $router->aliasMiddleware('universal', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
        
        // Register authentication-specific tenant middleware (lightweight)
        $router->aliasMiddleware('tenant.auth', TenantAuthMiddleware::class);
        
        // Register other middleware aliases
        $router->aliasMiddleware('tenancy.api', ApiAuthMiddleware::class);
        $router->aliasMiddleware('central.tenant', CentralDomainMiddleware::class);
        $router->aliasMiddleware('smart.domain', SmartDomainResolver::class);
        $router->aliasMiddleware('tenant.homepage', HomepageRedirectMiddleware::class);
        
        // Legacy compatibility (deprecated - use 'tenant' instead)
        $router->aliasMiddleware('tenant.legacy', TenantMiddleware::class);
        $router->aliasMiddleware('smart.tenant', SimpleTenantMiddleware::class);
        
        // Register middleware group for tenant routes (without homepage middleware)
        $router->middlewareGroup('tenant', [
            SimpleTenantMiddleware::class,
        ]);
        
        // Register central domain middleware group
        $router->middlewareGroup('central', [
            'web',
            CentralDomainMiddleware::class,
        ]);
        
        // Register API middleware group
        $router->middlewareGroup('tenancy.api', [
            'api',
            ApiAuthMiddleware::class,
        ]);
    }

    /**
     * Load package routes
     */
    protected function loadRoutes(): void
    {
        // Load consolidated tenancy routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/af-tenancy.php');
    }

    /**
     * Configure cached lookup for performance optimization
     */
    protected function configureCachedLookup(): void
    {
        $config = config('tenancy.database.cached_lookup', [
            'enabled' => true,
            'ttl' => 3600,
            'store' => 'redis',
        ]);
        
        if ($config['enabled']) {
            // Enable cached lookup on domain resolver
            \Stancl\Tenancy\Resolvers\DomainTenantResolver::$shouldCache = true;
            \Stancl\Tenancy\Resolvers\DomainTenantResolver::$cacheTTL = $config['ttl'];
            \Stancl\Tenancy\Resolvers\DomainTenantResolver::$cacheStore = $config['store'];
            
            // Enable cached lookup on path resolver
            \Stancl\Tenancy\Resolvers\PathTenantResolver::$shouldCache = true;
            \Stancl\Tenancy\Resolvers\PathTenantResolver::$cacheTTL = $config['ttl'];
            \Stancl\Tenancy\Resolvers\PathTenantResolver::$cacheStore = $config['store'];
            
            // Enable cached lookup on request data resolver
            \Stancl\Tenancy\Resolvers\RequestDataTenantResolver::$shouldCache = true;
            \Stancl\Tenancy\Resolvers\RequestDataTenantResolver::$cacheTTL = $config['ttl'];
            \Stancl\Tenancy\Resolvers\RequestDataTenantResolver::$cacheStore = $config['store'];
        }
    }

    /**
     * Configure Livewire for tenancy
     */
    protected function configureLivewireForTenancy(): void
    {
        // Only configure if Livewire is installed
        if (!class_exists(\Livewire\Livewire::class)) {
            return;
        }

        // Configure Livewire update route with tenant middleware for AJAX requests
        \Livewire\Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware([
                    'web',
                    'universal', // This is our registered alias for InitializeTenancyByDomain
                    \Stancl\Tenancy\Middleware\ScopeSessions::class, // Prevent session forgery
                ]);
        });
        
        // Configure file uploads for tenant support if needed
        if (class_exists(\Livewire\Features\SupportFileUploads\FilePreviewController::class)) {
            \Livewire\Features\SupportFileUploads\FilePreviewController::$middleware = [
                'web', 
                'universal', 
                \Stancl\Tenancy\Middleware\ScopeSessions::class,
            ];
        }
        
        // Update file upload middleware in Livewire config
        $this->updateLivewireFileUploadMiddleware();
    }

    /**
     * Update Livewire file upload middleware configuration
     */
    protected function updateLivewireFileUploadMiddleware(): void
    {
        // Only update if config exists
        if (config()->has('livewire.temporary_file_upload.middleware')) {
            config([
                'livewire.temporary_file_upload.middleware' => [
                    'throttle:60,1',
                    'universal',
                    \Stancl\Tenancy\Middleware\ScopeSessions::class,
                ],
            ]);
        }
    }

    /**
     * Auto-setup configuration if needed
     */
    protected function autoSetup(): void
    {
        // Auto-publish config if it doesn't exist
        if (!file_exists(config_path('artflow-tenancy.php'))) {
            $this->publishes([
                __DIR__ . '/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
            ], 'config');
        }
    }
}
