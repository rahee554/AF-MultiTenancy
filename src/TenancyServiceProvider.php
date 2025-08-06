<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Services\TenantContextCache;
use ArtflowStudio\Tenancy\Http\Middleware\TenantMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\CentralDomainMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware;
use ArtflowStudio\Tenancy\Http\Middleware\ApiAuthMiddleware;
use ArtflowStudio\Tenancy\Commands\InstallTenancyCommand;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Commands\HealthCheckCommand;
use ArtflowStudio\Tenancy\Commands\TestSystemCommand;
use ArtflowStudio\Tenancy\Commands\TestPerformanceCommand;
use ArtflowStudio\Tenancy\Commands\ComprehensiveTenancyTestCommand;
//use ArtflowStudio\Tenancy\Commands\QuickInstallTestCommand;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/af-tenancy.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'af-tenancy');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        $this->publishes([
            __DIR__ . '/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
        ], 'af-tenancy-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/af-tenancy'),
        ], 'af-tenancy-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallTenancyCommand::class,
                TenantCommand::class,
                HealthCheckCommand::class,
                TestSystemCommand::class,
                TestPerformanceCommand::class,
                ComprehensiveTenancyTestCommand::class,
                \ArtflowStudio\Tenancy\Commands\ComprehensiveTestCommand::class,
            ]);
        }

        $this->registerMiddleware();
        $this->configureLivewire();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // First, register stancl/tenancy service provider
        if (!$this->app->providerIsLoaded(\Stancl\Tenancy\TenancyServiceProvider::class)) {
            $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);
        }

        // Merge our configurations with stancl/tenancy
        $this->mergeConfigFrom(__DIR__ . '/../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__ . '/../config/artflow-tenancy.php', 'artflow-tenancy');

        // Register our services
        $this->app->singleton(TenantService::class);
        $this->app->singleton(TenantContextCache::class);

        // Register our event service provider
        $this->app->register(\ArtflowStudio\Tenancy\Providers\EventServiceProvider::class);
    }

    /**
     * Register middleware
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        // Register individual middleware
        $router->aliasMiddleware('af-tenant', TenantMiddleware::class);
        $router->aliasMiddleware('central', CentralDomainMiddleware::class);
        $router->aliasMiddleware('tenant.homepage', HomepageRedirectMiddleware::class);
        $router->aliasMiddleware('tenant.api', ApiAuthMiddleware::class);

        // Register middleware groups that work WITH stancl/tenancy
        $router->middlewareGroup('tenant.web', [
            'web',
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            TenantMiddleware::class, // Our enhancements
        ]);

        $router->middlewareGroup('central.web', [
            'web',
        ]);

        $router->middlewareGroup('tenant.api', [
            'api',
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            ApiAuthMiddleware::class,
        ]);
    }

    /**
     * Configure Livewire for multi-tenancy
     */
    protected function configureLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            // Configure Livewire to work properly with tenants
            $this->app->booted(function () {
                // Fix session issues in multi-tenant environment
                if (function_exists('tenant') && tenant()) {
                    $tenant = tenant();
                    
                    // Set tenant-specific session configuration
                    config([
                        'session.cookie' => config('session.cookie') . '_' . $tenant->getKey(),
                    ]);
                }
            });

            // Register Livewire middleware for tenancy
            if (file_exists(__DIR__ . '/Livewire')) {
                Livewire::addPersistentMiddleware([
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                ]);
            }
        }
    }
}
