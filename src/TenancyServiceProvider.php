<?php

namespace ArtflowStudio\Tenancy;

use ArtflowStudio\Tenancy\Services\TenantContextCache;
use ArtflowStudio\Tenancy\Services\TenantService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load helper functions
        require_once __DIR__.'/helpers.php';

        // Register tenant lifecycle events
        $this->registerTenantEvents();

        // Register stancl/tenancy event listeners
        $this->bootStanclTenancyEvents();

        // CRITICAL: Register global tenancy initialization middleware for 'web' middleware group.
        // This must run BEFORE the session middleware to ensure that sessions are scoped
        // correctly to the tenant's database. This is essential for both HTTP requests
        // and Livewire AJAX requests (/livewire/update) which bypass route middleware.
        // Uses prependMiddlewareToGroup to ensure highest priority in the 'web' group.
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->prependMiddlewareToGroup('web', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);


        $this->loadRoutesFrom(__DIR__.'/../routes/af-admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/af-tenancy.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/af-admin-api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'artflow-tenancy');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        $this->publishes([
            __DIR__.'/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
        ], 'af-tenancy-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/af-tenancy'),
        ], 'af-tenancy-views');

        // Publish migrations (excluding documentation and stubs)
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'af-tenancy-migrations');

        // Publish only essential files (excluding docs and stubs folders)
        $this->publishes([
            __DIR__.'/../config/artflow-tenancy.php' => config_path('artflow-tenancy.php'),
            __DIR__.'/../config/tenancy.php' => config_path('tenancy.php'),
        ], 'af-tenancy-essential');

        // Publish public assets (css/js/media) so host applications can copy them
        // into their public/vendor path via `php artisan vendor:publish --tag=af-tenancy-assets`.
        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/artflow-studio/tenancy'),
        ], 'af-tenancy-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                // Installation Commands
                Commands\Installation\InstallTenancyCommand::class,

                // Tenant Directory Commands
                Commands\Tenant\TenantDirectoriesCommand::class,
                Commands\Tenant\TenantGitTrackCommand::class,

                // Core Commands
                Commands\Core\CreateTenantCommand::class,
                Commands\Core\DeleteTenantCommand::class,
                Commands\Core\SwitchCacheDriverCommand::class,
                Commands\Core\FindUnusedFilesCommand::class,
                Commands\Core\SyncFastPanelDatabaseCommand::class,

                // Database Commands
                Commands\Database\TenantDatabaseCommand::class,
                Commands\Database\CheckPrivilegesCommand::class,
                Commands\Database\DebugTenantConnectionCommand::class,
                Commands\Database\DiagnoseDatabaseCommand::class,
                Commands\Database\FixTenantDatabasesCommand::class,
                Commands\Database\TenantConnectionTestCommand::class,
                Commands\Database\TenantConnectionPoolCommand::class,

                // Diagnostics Commands
                Commands\Diagnostics\TenancyPerformanceDiagnosticCommand::class,

                // Tenancy Commands
                Commands\Tenancy\TenantCommand::class,
                Commands\Tenancy\CreateTestTenantsCommand::class,
                Commands\Tenancy\CheckRouteConfigCommand::class,
                Commands\Tenancy\FastPanelCommand::class,
                Commands\Tenancy\LinkAssetsCommand::class,

                // FastPanel Commands
                Commands\FastPanel\CreateTenantCommand::class,
                Commands\FastPanel\ListDatabasesCommand::class,
                Commands\FastPanel\ListUsersCommand::class,
                Commands\FastPanel\SyncDatabaseCommand::class,
                Commands\FastPanel\VerifyDeploymentCommand::class,

                // Maintenance Commands
                Commands\Maintenance\WarmUpCacheCommand::class,
                Commands\Maintenance\HealthCheckCommand::class,
                Commands\Maintenance\EnhancedHealthCheckCommand::class,
                Commands\Maintenance\TenantMaintenanceModeCommand::class,
                Commands\Maintenance\ClearStaleCacheCommand::class,

                // Backup Commands
                Commands\Backup\TenantBackupCommand::class,
                Commands\Backup\BackupManagementCommand::class,

                // Testing Commands - Master Test Suite
                Commands\Testing\MasterTestCommand::class,
                Commands\Testing\ComprehensiveTenancyTestCommand::class,
                Commands\Testing\CreateTestTenantsCommand::class,
                Commands\Testing\TenantTestManagerCommand::class,

                // Testing - Auth Commands
                Commands\Testing\Auth\TestSanctumCommand::class,

                // Testing - Database Commands
                Commands\Testing\Database\TenantIsolationTestCommand::class,
                Commands\Testing\Database\FixTenantDatabasesCommand::class,
                Commands\Testing\Database\TestCachedLookupCommand::class,

                // Testing - Performance Commands
                Commands\Testing\Performance\TestPerformanceCommand::class,
                Commands\Testing\Performance\EnhancedTestPerformanceCommand::class,
                Commands\Testing\Performance\TenantStressTestCommand::class,

                // Testing - Redis Commands
                Commands\Testing\Redis\TestRedisCommand::class,
                Commands\Testing\Redis\RedisStressTestCommand::class,
                Commands\Testing\Redis\InstallRedisCommand::class,
                Commands\Testing\Redis\EnableRedisCommand::class,
                Commands\Testing\Redis\ConfigureRedisCommand::class,

                // Testing - System Commands
                Commands\Testing\System\TestSystemCommand::class,
                Commands\Testing\System\ServerCompatibilityCommand::class,
                Commands\Testing\System\ValidateTenancySystemCommand::class,
                Commands\Testing\System\TestMiddlewareCommand::class,

                // Testing - API Commands
                Commands\Testing\Api\TestApiEndpointsCommand::class,
                Commands\Testing\Api\SimpleApiTestCommand::class,
                Commands\Testing\Api\DetailedApiTestCommand::class,

                // Analytics Commands - NEW
                Commands\Analytics\TenantAnalyticsCommand::class,

                // System Commands - NEW
                Commands\System\CacheSetupCommand::class,

                // Performance Testing Commands - NEW
                Commands\Testing\Performance\MasterPerformanceTestCommand::class,
                Commands\Testing\Performance\TenancyPerformanceTestCommand::class,
                Commands\Testing\Performance\DatabaseStressTestCommand::class,
                Commands\Testing\Performance\ConnectionPoolTestCommand::class,
                Commands\Testing\Performance\CachePerformanceTestCommand::class,

                // PWA Commands - NEW
                Commands\PWA\EnablePWACommand::class,
                Commands\PWA\DisablePWACommand::class,
                Commands\PWA\PWAStatusCommand::class,
                Commands\PWA\TestPWACommand::class,

                // SEO Commands - NEW
                Commands\SEO\EnableSEOCommand::class,
                Commands\SEO\DisableSEOCommand::class,
                Commands\SEO\SEOStatusCommand::class,
                Commands\SEO\GenerateSitemapCommand::class,
            ]);
        }

        $this->registerMiddleware();
        $this->configureLivewire();
        $this->configureRedis();
        $this->loadViews();
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
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__.'/../config/artflow-tenancy.php', 'artflow-tenancy');

        // Register all services
        $this->registerServices();
    }

    /**
     * Register all our enhanced services
     */
    private function registerServices()
    {
        // Register our core services
        $this->app->singleton(TenantService::class);
        $this->app->singleton(TenantContextCache::class);
        $this->app->singleton(Services\TenantMaintenanceMode::class);
        $this->app->singleton(Services\TenantSanctumService::class);
        $this->app->singleton(Services\TenantBackupService::class);
        $this->app->singleton(Services\TenantPWAService::class);
        $this->app->singleton(Services\TenantSEOService::class);
        $this->app->singleton(Services\TenantAssetService::class);

        // Register new enhanced services
        $this->app->singleton(Services\TenantAnalyticsService::class, function ($app) {
            return new Services\TenantAnalyticsService;
        });

        $this->app->singleton(Services\TenantResourceQuotaService::class, function ($app) {
            return new Services\TenantResourceQuotaService;
        });

        // Bind analytics service for convenience
        $this->app->alias(Services\TenantAnalyticsService::class, 'tenant.analytics');

        // Bind quota service for convenience
        $this->app->alias(Services\TenantResourceQuotaService::class, 'tenant.quotas');

        // Register database managers for dependency injection
        $this->registerDatabaseManagers();

        // Register our event service provider
        $this->app->register(Providers\EventServiceProvider::class);

        // Ensure tenancy middleware has highest priority (from App\Providers\TenancyServiceProvider)
        $this->makeTenancyMiddlewareHighestPriority();
    }

    /**
     * Register tenant database managers
     */
    protected function registerDatabaseManagers(): void
    {
        // Register database managers that can be resolved from container
        $this->app->bind(
            \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class
        );

        $this->app->bind(
            \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
            \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class
        );

        $this->app->bind(
            \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class
        );

        $this->app->bind(
            \Stancl\Tenancy\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::class,
            \Stancl\Tenancy\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager::class
        );

        $this->app->bind(
            \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
            \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class
        );
    }

    /**
     * Make tenancy middleware highest priority (formerly in App\Providers\TenancyServiceProvider)
     */
    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,

            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByPath::class,
            \Stancl\Tenancy\Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
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
        $router->aliasMiddleware('asset.bypass', Http\Middleware\AssetBypassMiddleware::class);
        $router->aliasMiddleware('tenant.smart', Http\Middleware\SmartDomainResolverMiddleware::class);

        // Register tenant homepage middleware
        $router->aliasMiddleware('tenant.homepage', Http\Middleware\TenantHomepageMiddleware::class);

        // Universal middleware - works for both central and tenant domains
        $router->aliasMiddleware('universal.web', Http\Middleware\UniversalWebMiddleware::class);
        $router->aliasMiddleware('universal', Http\Middleware\UniversalWebMiddleware::class);

        // Tenant maintenance middleware
        $router->aliasMiddleware('tenant.maintenance', Http\Middleware\TenantMaintenanceMiddleware::class);
        $router->aliasMiddleware('tenant.homepage', Http\Middleware\HomepageRedirectMiddleware::class);
        $router->aliasMiddleware('tenant.auth', Http\Middleware\TenantAuthMiddleware::class);
        $router->aliasMiddleware('tenant.api', Http\Middleware\ApiAuthMiddleware::class);
        $router->aliasMiddleware('tenant.pwa', Http\Middleware\TenantPWAMiddleware::class);

        // CRITICAL: Stale session detection (prevents 403 Forbidden after DB recreation)
        $router->aliasMiddleware('tenant.detect-stale', Http\Middleware\DetectStaleSessionMiddleware::class);

        // Register middleware groups
        $this->registerMiddlewareGroups($router);
    }

    /**
     * Register middleware groups for central, tenant, and universal routes
     */
    protected function registerMiddlewareGroups(Router $router): void
    {
        // ✨ UNIVERSAL: For routes that should work for BOTH central and tenant
        $router->middlewareGroup('universal.web', [
            'web',                                                    // Laravel web middleware (sessions, CSRF, etc.)
            Http\Middleware\UniversalWebMiddleware::class,            // Universal middleware that tries tenant initialization
        ]);

        // For CENTRAL domain routes ONLY (management, admin interface)
        $router->middlewareGroup('central.web', [
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'central',                    // Our central domain check
        ]);

        // For TENANT domain routes with full session scoping - OFFICIAL stancl/tenancy pattern
        $router->middlewareGroup('tenant.web', [
            'web',                        // Laravel web middleware
            'tenant',                     // Initialize tenancy by domain (stancl/tenancy)
            'tenant.prevent-central',     // Prevent access from central domains (stancl/tenancy)
            'tenant.scope-sessions',      // Scope sessions per tenant (stancl/tenancy) - CRITICAL for Livewire
            'tenant.detect-stale',        // CRITICAL: Detect stale sessions after DB recreation
        ]);

        // For TENANT API routes - OFFICIAL stancl/tenancy pattern
        $router->middlewareGroup('tenant.api', [
            'api',                        // Laravel API middleware
            'tenant',                     // Initialize tenancy by domain
            'tenant.prevent-central',     // Prevent access from central domains
        ]);

        // ✨ UNIVERSAL AUTH: For auth routes that should work for BOTH central and tenant
        // CRITICAL: Tenancy initialization MUST run before session middleware
        $router->middlewareGroup('universal.auth', [
            'tenant.auth',                // Initialize tenancy FIRST
            'web',                        // Laravel web middleware (sessions, CSRF, etc.)
            'tenant.scope-sessions',      // Scope sessions per tenant for Livewire
        ]);
    }

    /**
     * Configure Livewire for multi-tenancy
     */
    protected function configureLivewire(): void
    {
        if (class_exists(Livewire::class)) {
            $this->app->booted(function () {
                // Register Livewire components
                Livewire::component('tenancy.admin.dashboard', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard::class);
                Livewire::component('tenancy.admin.tenants-index', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex::class);
                Livewire::component('tenancy.admin.view-tenant', \ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant::class);
                Livewire::component('tenancy.admin.create-tenant', \ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant::class);
                Livewire::component('tenancy.admin.tenant-analytics', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics::class);
                Livewire::component('tenancy.admin.queue-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring::class);
                Livewire::component('tenancy.admin.system-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring::class);

                // Livewire middleware for tenancy
                Livewire::addPersistentMiddleware([
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                    // Removed: PreventAccessFromCentralDomains - it causes issues with universal routes
                    // \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
                    // Removed: ScopeSessions - it needs to run with the full HTTP middleware chain for proper session handling
                ]);

                // CRITICAL: Bootstrap tenancy for Livewire component method calls
                // Using Livewire's lifecycle hooks to ensure tenancy is initialized
                // before component methods are executed
                \Livewire\on('mount', function ($component, $params, $key, $parent) {
                    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
                });

                // CRITICAL: Bootstrap tenancy for AJAX calls (call event)
                // This ensures _livewire/update requests also have tenancy initialized
                \Livewire\on('call', function ($component, $method, $params, $addEffect, $earlyReturn) {
                    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
                });

                // CRITICAL: Bootstrap tenancy for hydration
                // This ensures tenancy is initialized when component state is hydrated
                \Livewire\on('hydrate', function ($component, $memo) {
                    \ArtflowStudio\Tenancy\Livewire\TenancyBootstrapperHook::bootstrap();
                });
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

    /**
     * Register tenant lifecycle events (formerly in App\Providers\AppServiceProvider)
     */
    private function registerTenantEvents(): void
    {
        $tenantModel = \ArtflowStudio\Tenancy\Models\Tenant::class;

        // When a tenant is created, ensure directories are created
        \Illuminate\Support\Facades\Event::listen("eloquent.created: {$tenantModel}", function (\ArtflowStudio\Tenancy\Models\Tenant $tenant) {
            $this->createTenantDirectories($tenant);
        });

        // When a tenant is deleted, clean up directories
        \Illuminate\Support\Facades\Event::listen("eloquent.deleted: {$tenantModel}", function (\ArtflowStudio\Tenancy\Models\Tenant $tenant) {
            $this->deleteTenantDirectories($tenant);
        });
    }

    /**
     * Create all necessary directories for a new tenant
     */
    private function createTenantDirectories(\ArtflowStudio\Tenancy\Models\Tenant $tenant): void
    {
        $domain = $tenant->domains?->first()?->domain ?? $tenant->id;

        // Public directories
        $publicSubdirs = [
            'assets',      // General assets
            'pwa',         // PWA files
            'pwa/icons',   // PWA icons
            'seo',         // SEO files
            'documents',   // Downloadable documents
            'media',       // Media files
        ];

        $publicPath = base_path("storage/app/public/tenants/{$domain}");

        foreach ($publicSubdirs as $subdir) {
            $path = "{$publicPath}/{$subdir}";
            \Illuminate\Support\Facades\File::ensureDirectoryExists($path);
            // Create .gitkeep to preserve empty directories in git
            \Illuminate\Support\Facades\File::put("{$path}/.gitkeep", '');
        }

        // Private directories
        $privateSubdirs = [
            'backups',     // Database backups
            'logs',        // Tenant-specific logs
            'cache',       // Tenant cache
            'temp',        // Temporary files
            'documents',   // Private documents
            'uploads',     // Private uploads
            'config',      // Tenant-specific config
        ];

        $privatePath = base_path("storage/app/private/tenants/{$domain}");

        foreach ($privateSubdirs as $subdir) {
            $path = "{$privatePath}/{$subdir}";
            \Illuminate\Support\Facades\File::ensureDirectoryExists($path);
            \Illuminate\Support\Facades\File::put("{$path}/.gitkeep", '');
        }
    }

    /**
     * Delete all directories for a deleted tenant
     */
    private function deleteTenantDirectories(\ArtflowStudio\Tenancy\Models\Tenant $tenant): void
    {
        $domain = $tenant->domains?->first()?->domain ?? $tenant->id;

        $publicPath = base_path("storage/app/public/tenants/{$domain}");
        $privatePath = base_path("storage/app/private/tenants/{$domain}");

        if (\Illuminate\Support\Facades\File::isDirectory($publicPath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($publicPath);
        }

        if (\Illuminate\Support\Facades\File::isDirectory($privatePath)) {
            \Illuminate\Support\Facades\File::deleteDirectory($privatePath);
        }
    }

    /**
     * Boot stancl/tenancy events (formerly in App\Providers\TenancyServiceProvider)
     */
    private function bootStanclTenancyEvents(): void
    {
        $events = $this->getStanclTenancyEvents();

        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof \Stancl\JobPipeline\JobPipeline) {
                    $listener = $listener->toListener();
                }

                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }
    }

    /**
     * Get stancl/tenancy event mappings (formerly in App\Providers\TenancyServiceProvider::events())
     */
    private function getStanclTenancyEvents(): array
    {
        return [
            // Tenant events
            \Stancl\Tenancy\Events\CreatingTenant::class => [],
            \Stancl\Tenancy\Events\TenantCreated::class => [
                \Stancl\JobPipeline\JobPipeline::make([
                    \Stancl\Tenancy\Jobs\CreateDatabase::class,
                    \Stancl\Tenancy\Jobs\MigrateDatabase::class,
                    // \Stancl\Tenancy\Jobs\SeedDatabase::class,
                ])->send(function (\Stancl\Tenancy\Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],
            \Stancl\Tenancy\Events\SavingTenant::class => [],
            \Stancl\Tenancy\Events\TenantSaved::class => [],
            \Stancl\Tenancy\Events\UpdatingTenant::class => [],
            \Stancl\Tenancy\Events\TenantUpdated::class => [],
            \Stancl\Tenancy\Events\DeletingTenant::class => [],
            \Stancl\Tenancy\Events\TenantDeleted::class => [
                \Stancl\JobPipeline\JobPipeline::make([
                    \Stancl\Tenancy\Jobs\DeleteDatabase::class,
                ])->send(function (\Stancl\Tenancy\Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // Domain events
            \Stancl\Tenancy\Events\CreatingDomain::class => [],
            \Stancl\Tenancy\Events\DomainCreated::class => [],
            \Stancl\Tenancy\Events\SavingDomain::class => [],
            \Stancl\Tenancy\Events\DomainSaved::class => [],
            \Stancl\Tenancy\Events\UpdatingDomain::class => [],
            \Stancl\Tenancy\Events\DomainUpdated::class => [],
            \Stancl\Tenancy\Events\DeletingDomain::class => [],
            \Stancl\Tenancy\Events\DomainDeleted::class => [],

            // Database events
            \Stancl\Tenancy\Events\DatabaseCreated::class => [],
            \Stancl\Tenancy\Events\DatabaseMigrated::class => [],
            \Stancl\Tenancy\Events\DatabaseSeeded::class => [],
            \Stancl\Tenancy\Events\DatabaseRolledBack::class => [],
            \Stancl\Tenancy\Events\DatabaseDeleted::class => [],

            // Tenancy events
            \Stancl\Tenancy\Events\InitializingTenancy::class => [],
            \Stancl\Tenancy\Events\TenancyInitialized::class => [
                \Stancl\Tenancy\Listeners\BootstrapTenancy::class,
            ],

            \Stancl\Tenancy\Events\EndingTenancy::class => [],
            \Stancl\Tenancy\Events\TenancyEnded::class => [
                \Stancl\Tenancy\Listeners\RevertToCentralContext::class,
            ],

            \Stancl\Tenancy\Events\BootstrappingTenancy::class => [],
            \Stancl\Tenancy\Events\TenancyBootstrapped::class => [],
            \Stancl\Tenancy\Events\RevertingToCentralContext::class => [],
            \Stancl\Tenancy\Events\RevertedToCentralContext::class => [],

            // Resource syncing
            \Stancl\Tenancy\Events\SyncedResourceSaved::class => [
                \Stancl\Tenancy\Listeners\UpdateSyncedResource::class,
            ],

            \Stancl\Tenancy\Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    /**
     * Load package views
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'af-tenancy');
    }
}
