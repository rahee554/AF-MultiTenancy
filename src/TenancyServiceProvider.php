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
use ArtflowStudio\Tenancy\Commands\WarmUpCacheCommand;
use ArtflowStudio\Tenancy\Http\Middleware\HomepageRedirectMiddleware;

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
                InstallTenancyCommand::class,
                WarmUpCacheCommand::class,
            ]);
        }

        // Load routes
        $this->loadRoutes();
        
        // Register middleware
        $this->registerMiddleware();
        
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
        
        // Register middleware aliases
        $router->aliasMiddleware('tenant', TenantMiddleware::class);
        $router->aliasMiddleware('tenancy.api', ApiAuthMiddleware::class);
        $router->aliasMiddleware('central.tenant', CentralDomainMiddleware::class);
        $router->aliasMiddleware('smart.domain', SmartDomainResolver::class);
        $router->aliasMiddleware('tenant.homepage', HomepageRedirectMiddleware::class);
        
        // Register middleware group for tenant routes
        $router->middlewareGroup('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            TenantMiddleware::class,
            HomepageRedirectMiddleware::class,
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
