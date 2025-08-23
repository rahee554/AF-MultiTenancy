<?php

namespace ArtflowStudio\Tenancy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Services\TenantContextCache;

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
                // Installation Commands
                Commands\Installation\InstallTenancyCommand::class,
                
                // Core Commands
                Commands\Core\CreateTenantCommand::class,
                Commands\Core\SwitchCacheDriverCommand::class,
                Commands\Core\FindUnusedFilesCommand::class,
                
                // Tenant Management Commands
                Commands\Tenancy\TenantCommand::class,
                
                // Database Commands
                Commands\Database\TenantDatabaseCommand::class,
                
                // Main Testing Command
                Commands\Testing\ComprehensiveTenancyTestCommand::class,
                
                // Auth Testing Commands
                Commands\Testing\Auth\TestTenantAuthentication::class,
                Commands\Testing\Auth\TestAuthContext::class,
                Commands\Testing\Auth\DebugAuthenticationFlow::class,
                Commands\Testing\Auth\TestSanctumCommand::class,
                
                // Database Testing Commands
                Commands\Testing\Database\TenantIsolationTestCommand::class,
                Commands\Testing\Database\FixTenantDatabasesCommand::class,
                Commands\Testing\Database\TestCachedLookupCommand::class,
                
                // Performance Testing Commands (if they exist)
                Commands\Testing\Performance\TestPerformanceCommand::class,
                Commands\Testing\Performance\EnhancedTestPerformanceCommand::class,
                Commands\Testing\Performance\TenantStressTestCommand::class,
                
                // Redis Testing Commands (if they exist)
                Commands\Testing\Redis\TestRedisCommand::class,
                Commands\Testing\Redis\RedisStressTestCommand::class,
                Commands\Testing\Redis\InstallRedisCommand::class,
                Commands\Testing\Redis\EnableRedisCommand::class,
                Commands\Testing\Redis\ConfigureRedisCommand::class,
                
                // System Testing Commands (if they exist)
                Commands\Testing\System\TestSystemCommand::class,
                Commands\Testing\System\ServerCompatibilityCommand::class,
                Commands\Testing\System\ValidateTenancySystemCommand::class,
                Commands\Testing\System\TestMiddlewareCommand::class,
                
                // FastPanel Commands
                Commands\FastPanel\CreateTenantCommand::class,
                Commands\FastPanel\ListDatabasesCommand::class,
                Commands\FastPanel\ListUsersCommand::class,
                Commands\FastPanel\SyncDatabaseCommand::class,
                Commands\FastPanel\VerifyDeploymentCommand::class,
                
                // Maintenance Commands
                Commands\Maintenance\WarmUpCacheCommand::class,
                Commands\Maintenance\HealthCheckCommand::class,
                Commands\Maintenance\TenantMaintenanceModeCommand::class,
                
                // Backup Commands
                Commands\Backup\TenantBackupCommand::class,
                Commands\Backup\BackupManagementCommand::class,
            ]);
        }

        $this->registerMiddleware();
        $this->configureLivewire();
        $this->configureRedis();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CRITICAL: Register stancl/tenancy service provider FIRST
        // This ensures all stancl/tenancy bootstrappers and core functionality is available
        $this->app->register(\Stancl\Tenancy\TenancyServiceProvider::class);

        // Merge our configurations with stancl/tenancy
        $this->mergeConfigFrom(__DIR__ . '/../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__ . '/../config/artflow-tenancy.php', 'artflow-tenancy');

        // Register our services
        $this->app->singleton(TenantService::class);
        $this->app->singleton(TenantContextCache::class);
        $this->app->singleton(Services\CachedTenantResolver::class);
        $this->app->singleton(Services\TenantMaintenanceMode::class);
        $this->app->singleton(Services\TenantSanctumService::class);
        $this->app->singleton(Services\TenantBackupService::class);

        // Register our event service provider
        $this->app->register(Providers\EventServiceProvider::class);
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
        $router->aliasMiddleware('af-tenant', Http\Middleware\TenantMiddleware::class);
        $router->aliasMiddleware('central', Http\Middleware\CentralDomainMiddleware::class);
        $router->aliasMiddleware('early-identification', Http\Middleware\EarlyIdentificationMiddleware::class);
        
        // Universal middleware - works for both central and tenant domains
        $router->aliasMiddleware('universal.web', Http\Middleware\UniversalWebMiddleware::class);
        
        // Tenant maintenance middleware
        $router->aliasMiddleware('tenant.maintenance', Http\Middleware\TenantMaintenanceMiddleware::class);
        $router->aliasMiddleware('tenant.homepage', Http\Middleware\HomepageRedirectMiddleware::class);
        $router->aliasMiddleware('tenant.auth', Http\Middleware\TenantAuthMiddleware::class);
        $router->aliasMiddleware('tenant.api', Http\Middleware\ApiAuthMiddleware::class);

        // MIDDLEWARE GROUPS - Using stancl/tenancy patterns
        
        // ✨ UNIVERSAL: For routes that should work for BOTH central and tenant
        $router->middlewareGroup('universal.web', [
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'universal.web',              // Our universal middleware (tries tenant, falls back to central)
        ]);
        
        // For CENTRAL domain routes ONLY (management, admin interface)
        $router->middlewareGroup('central.web', [
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'central',                    // Our central domain check
        ]);

        // For TENANT domain routes with full session scoping (RECOMMENDED for tenant-only routes)
        $router->middlewareGroup('tenant.web', [
            'web',                        // Laravel web middleware
            'tenant',                     // Initialize tenancy by domain (stancl/tenancy)
            'tenant.prevent-central',     // Prevent access from central domains (stancl/tenancy)
            'tenant.scope-sessions',      // Scope sessions per tenant (stancl/tenancy) - CRITICAL for Livewire
            'af-tenant',                  // Our enhancements (status checks, logging)
        ]);

        // For TENANT API routes  
        $router->middlewareGroup('tenant.api', [
            'api',                        // Laravel API middleware
            'tenant',                     // Initialize tenancy by domain
            'tenant.prevent-central',     // Prevent access from central domains
            'tenant.api',                 // Our API enhancements
        ]);

        // Special group for tenant AUTH routes with enhanced logging
        $router->middlewareGroup('tenant.auth.web', [
            'web',                        // Laravel web middleware
            'tenant',                     // Initialize tenancy by domain
            'tenant.prevent-central',     // Prevent access from central domains
            'tenant.scope-sessions',      // Scope sessions per tenant
            'tenant.auth',                // Our auth enhancements with logging
        ]);
    }    /**
     * Configure Livewire for multi-tenancy
     */
    protected function configureLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            $this->app->booted(function () {
                // Livewire middleware for tenancy
                Livewire::addPersistentMiddleware([
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
                ]);
            });
        }
    }

    /**
     * Configure Redis for multi-tenancy
     */
    protected function configureRedis(): void
    {
        if (config('artflow-tenancy.redis.per_tenant_database', false)) {
            $this->app->booted(function () {
                // Configure Redis database selection based on tenant
                if (class_exists('\Illuminate\Redis\RedisManager')) {
                    $this->configureTenantRedis();
                }
            });
        }
    }

    /**
     * Configure tenant-specific Redis settings
     */
    protected function configureTenantRedis(): void
    {
        // This will be called when tenant context changes
        tenancy()->hook('tenant.initialized', function ($tenant) {
            $databaseOffset = config('artflow-tenancy.redis.database_offset', 10);
            $tenantId = $tenant->id;
            
            // Calculate tenant-specific Redis database number
            $tenantDatabase = $databaseOffset + (crc32($tenantId) % 100);
            
            // Update Redis configuration for this tenant
            config([
                'database.redis.default.database' => $tenantDatabase,
                'database.redis.cache.database' => $tenantDatabase,
                'cache.prefix' => config('artflow-tenancy.redis.prefix_pattern', 'tenant_{tenant_id}_')
                    ? str_replace('{tenant_id}', $tenantId, config('artflow-tenancy.redis.prefix_pattern'))
                    : "tenant_{$tenantId}_",
            ]);
        });

        // Reset to central Redis when tenant context ends
        tenancy()->hook('tenant.ended', function () {
            config([
                'database.redis.default.database' => 0,
                'database.redis.cache.database' => 0,
                'cache.prefix' => config('artflow-tenancy.redis.central_prefix', 'central_'),
            ]);
        });
    }
}
