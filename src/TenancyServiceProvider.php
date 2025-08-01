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
use ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware;

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
            ]);
        }

        // Load routes
        $this->loadRoutes();
        
        // Register middleware
        $this->registerMiddleware();
        
        // Auto-setup if needed
        $this->autoSetup();
    }

    /**
     * Auto-register middleware
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        
        // Register middleware aliases
        $router->aliasMiddleware('tenant', TenantMiddleware::class);
        $router->aliasMiddleware('tenancy.api', ApiAuthMiddleware::class);
        
        // Register middleware group for tenant routes
        $router->middlewareGroup('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            TenantMiddleware::class,
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
        // Load web routes for admin interface
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        
        // Load API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
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
