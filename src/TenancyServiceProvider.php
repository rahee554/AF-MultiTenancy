<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind TenantService
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Register separate artflow config (don't merge with stancl/tenancy)
        $this->mergeConfigFrom(__DIR__ . '/../config/artflow-tenancy.php', 'artflow-tenancy');
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

        // Auto-setup stancl/tenancy integration
        $this->configureStanclIntegration();

        // Auto-publish and migrate on package install
        $this->autoSetup();
    }

    /**
     * Configure proper stancl/tenancy integration
     */
    protected function configureStanclIntegration(): void
    {
        // Ensure stancl/tenancy uses our enhanced Tenant model
        config([
            'tenancy.tenant_model' => \ArtflowStudio\Tenancy\Models\Tenant::class,
        ]);
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
        
        // Register middleware group for easy use
        $router->middlewareGroup('tenant', [
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
            ]);
        }
    }

    /**
     * Register publishable assets
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish tenancy routes (recommended for customization)
            $this->publishes([
                __DIR__ . '/../routes/tenancy.php' => base_path('routes/tenancy.php'),
            ], 'tenancy-routes');

            // Publish tenancy config (required)
            $this->publishes([
                __DIR__ . '/../config/tenancy.php' => config_path('tenancy.php'),
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
                __DIR__ . '/../routes/tenancy.php' => base_path('routes/tenancy.php'),
                __DIR__ . '/../config/tenancy.php' => config_path('tenancy.php'),
            ], 'tenancy');
        }
    }

    /**
     * Auto-setup for initial package installation
     */
    protected function autoSetup(): void
    {
        if ($this->app->runningInConsole() && !config('tenancy')) {
            // Auto-publish critical configuration if not already published
            if (!file_exists(config_path('tenancy.php'))) {
                \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                    '--tag' => 'tenancy-config',
                    '--force' => true
                ]);
            }
        }
    }
}
