<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Commands\CreateTestTenantsCommand;
use ArtflowStudio\Tenancy\Commands\TestPerformanceCommand;
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
        
        // Register stancl/tenancy configuration first
        $this->mergeConfigFrom(__DIR__ . '/../config/stancl-tenancy.php', 'tenancy');
        
        // Bind TenantService
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Merge package config with stancl/tenancy config
        $this->mergeConfigFrom(__DIR__ . '/../config/tenancy.php', 'artflow-tenancy');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-register middleware
        $this->registerMiddleware();
        
        // Load package routes
        $this->loadPackageRoutes();
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tenancy');

        // Register commands
        $this->registerCommands();

        // Register publishables
        $this->registerPublishables();

        // Auto-publish and migrate on package install
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
            'throttle:api',
            ApiAuthMiddleware::class,
        ]);
    }

    /**
     * Load package routes
     */
    protected function loadPackageRoutes(): void
    {
        Route::group([
            'namespace' => 'ArtflowStudio\Tenancy\Http\Controllers',
        ], function () {
            // Load tenancy routes (admin + API)
            require __DIR__ . '/../routes/tenancy.php';
        });
    }

    /**
     * Register commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantCommand::class,
                CreateTestTenantsCommand::class,
                TestPerformanceCommand::class,
                \ArtflowStudio\Tenancy\Commands\InstallPackageCommand::class,
            ]);
        }
    }

    /**
     * Register publishable assets
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish stancl/tenancy config (required for proper integration)
            $this->publishes([
                __DIR__ . '/../config/stancl-tenancy.php' => config_path('tenancy.php'),
            ], 'tenancy-stancl-config');

            // Publish tenancy routes (recommended for customization)
            $this->publishes([
                __DIR__ . '/../routes/tenancy.php' => base_path('routes/tenancy.php'),
            ], 'tenancy-routes');

            // Publish artflow tenancy config (optional)
            $this->publishes([
                __DIR__ . '/../config/tenancy.php' => config_path('artflow-tenancy.php'),
            ], 'tenancy-config');

            // Publish views (optional - for dashboard customization)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/tenancy'),
            ], 'tenancy-views');

            // Publish migrations (optional - package auto-runs them)
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'tenancy-migrations');

            // Publish all assets at once
            $this->publishes([
                __DIR__ . '/../config/stancl-tenancy.php' => config_path('tenancy.php'),
                __DIR__ . '/../config/tenancy.php' => config_path('artflow-tenancy.php'),
                __DIR__ . '/../routes/tenancy.php' => base_path('routes/tenancy.php'),
            ], 'tenancy');
        }
    }

    /**
     * Auto-setup for initial package installation
     */
    protected function autoSetup(): void
    {
        if ($this->app->runningInConsole() && !config('tenancy')) {
            // Auto-publish critical stancl/tenancy configuration if not already published
            if (!file_exists(config_path('tenancy.php'))) {
                \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                    '--tag' => 'tenancy-stancl-config',
                    '--force' => true
                ]);
            }
        }
    }
}
