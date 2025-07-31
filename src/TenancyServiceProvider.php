<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Middleware\TenantMiddleware;

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

        // Merge package config
        $this->mergeConfigFrom(__DIR__ . '/../config/tenancy.php', 'tenancy');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tenancy');

        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('tenant', TenantMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__ . '/../config/tenancy.php' => config_path('tenancy.php'),
            ], 'tenancy-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/tenancy'),
            ], 'tenancy-views');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'tenancy-migrations');
        }
    }
}
