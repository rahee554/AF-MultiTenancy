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
use ArtflowStudio\Tenancy\Http\Middleware\SmartDomainResolverMiddleware;
use ArtflowStudio\Tenancy\Commands\InstallTenancyCommand;
use ArtflowStudio\Tenancy\Commands\TenantCommand;
use ArtflowStudio\Tenancy\Commands\TenantDatabaseCommand;
use ArtflowStudio\Tenancy\Commands\HealthCheckCommand;
use ArtflowStudio\Tenancy\Commands\TestSystemCommand;
use ArtflowStudio\Tenancy\Commands\TestPerformanceCommand;
use ArtflowStudio\Tenancy\Commands\ComprehensiveTenancyTestCommand;
use ArtflowStudio\Tenancy\Commands\QuickInstallTestCommand;

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
                TenantDatabaseCommand::class,
                HealthCheckCommand::class,
                TestSystemCommand::class,
                TestPerformanceCommand::class,
                ComprehensiveTenancyTestCommand::class,
                \ArtflowStudio\Tenancy\Commands\ComprehensiveTestCommand::class,
                QuickInstallTestCommand::class,
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
        // Register stancl/tenancy service provider if not already loaded
        $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);

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
     * Register middleware that works WITH stancl/tenancy
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        // Register stancl/tenancy core middleware aliases for convenience
        $router->aliasMiddleware('tenant', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
        $router->aliasMiddleware('tenant.prevent-central', \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class);
        $router->aliasMiddleware('tenant.scope-sessions', \Stancl\Tenancy\Middleware\ScopeSessions::class);

        // Register our enhanced middleware aliases
        $router->aliasMiddleware('af-tenant', TenantMiddleware::class);
        $router->aliasMiddleware('central', CentralDomainMiddleware::class);
        $router->aliasMiddleware('tenant.homepage', HomepageRedirectMiddleware::class);
        $router->aliasMiddleware('tenant.auth', \ArtflowStudio\Tenancy\Http\Middleware\TenantAuthMiddleware::class);
        $router->aliasMiddleware('tenant.api', ApiAuthMiddleware::class);
        $router->aliasMiddleware('smart-domain', SmartDomainResolverMiddleware::class);

        // CRITICAL: Define middleware groups that work with stancl/tenancy patterns
        
        // For CENTRAL domain routes (management, admin interface)
        // These routes are ONLY available on central domains and have no tenant context
        $router->middlewareGroup('central.web', [
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'central',                    // Our central domain check
        ]);

        // For TENANT domain routes with full session scoping (main tenant application)
        // These routes are ONLY available on tenant domains with full tenant isolation
        $router->middlewareGroup('tenant.web', [
            'web',                        // Laravel web middleware (includes sessions, CSRF, etc.)
            'tenant',                     // Initialize tenancy by domain (stancl/tenancy)
            'tenant.prevent-central',     // Prevent access from central domains (stancl/tenancy)
            'tenant.scope-sessions',      // Scope sessions per tenant (stancl/tenancy) - CRITICAL for Livewire
            'af-tenant',                  // Our enhancements (status checks, logging)
        ]);

        // For TENANT API routes  
        $router->middlewareGroup('tenant.api', [
            'api',                        // Laravel API middleware (no sessions, just API stuff)
            'tenant',                     // Initialize tenancy by domain
            'tenant.prevent-central',     // Prevent access from central domains
            'tenant.api',                 // Our API enhancements
        ]);

        // Special group for tenant AUTH routes that need enhanced logging
        $router->middlewareGroup('tenant.auth.web', [
            'web',                        // Laravel web middleware
            'tenant',                     // Initialize tenancy by domain
            'tenant.prevent-central',     // Prevent access from central domains
            'tenant.scope-sessions',      // Scope sessions per tenant
            'tenant.auth',                // Our auth enhancements with logging
        ]);

        // ✨ NEW: Smart Domain Resolver Middleware Group
        // This intelligently detects if domain is central or tenant and applies appropriate context
        // Perfect for shared routes like /login, /dashboard that work on both domain types
        $router->middlewareGroup('central.tenant.web', [
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'smart-domain',               // Our smart domain detection and context application
        ]);
    }    /**
     * Configure Livewire for multi-tenancy
     */
    protected function configureLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            // Configure Livewire to work properly with tenants
            $this->app->booted(function () {
                // Livewire middleware for tenancy
                // Note: Session scoping is handled by ScopeSessions middleware in the middleware groups
                Livewire::addPersistentMiddleware([
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
                ]);
            });
        }
    }
}
