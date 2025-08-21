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
// Command classes are auto-discovered from src/Commands (recursive). Avoid hard imports here to remain flexible.

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/af-tenancy.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'af-tenancy');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'artflow-tenancy');
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

        // Publish admin routes (optional admin UI)
        $this->publishes([
            __DIR__ . '/../routes/admin.php' => base_path('routes/tenancy-admin.php'),
        ], 'af-tenancy-routes');

        if ($this->app->runningInConsole()) {
            // Auto-discover command classes under src/Commands and src/Console/Commands
            $commandClasses = [];

            $baseDir = __DIR__ . '/Commands';
            if (is_dir($baseDir)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir));
                foreach ($it as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $filePath = $file->getPathname();
                        $relative = substr($filePath, strlen($baseDir) + 1); // path after Commands/
                        $relative = str_replace(['\\', '/'], '\\', $relative);
                        $class = '\\ArtflowStudio\\Tenancy\\Commands\\' . str_replace('\\', '\\', substr($relative, 0, -4));
                        // Convert path separators to namespace separators
                        $class = str_replace('\\', '\\', $class);
                        if (class_exists($class) || @class_exists($class)) {
                            $commandClasses[] = $class;
                        }
                    }
                }
            }

            $consoleCommandsDir = __DIR__ . '/Console/Commands';
            if (is_dir($consoleCommandsDir)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($consoleCommandsDir));
                foreach ($it as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $filePath = $file->getPathname();
                        $relative = substr($filePath, strlen($consoleCommandsDir) + 1);
                        $relative = str_replace(['\\', '/'], '\\', $relative);
                        $class = '\\ArtflowStudio\\Tenancy\\Console\\Commands\\' . str_replace('\\', '\\', substr($relative, 0, -4));
                        if (class_exists($class) || @class_exists($class)) {
                            $commandClasses[] = $class;
                        }
                    }
                }
            }

            // Fallback: register known commands already imported above if discovery missed them
            $fallback = [
                // Core tenant commands
                \ArtflowStudio\Tenancy\Commands\Core\CreateTenantCommand::class,

                // Tenancy group
                \ArtflowStudio\Tenancy\Commands\Tenancy\InstallTenancyCommand::class,
                \ArtflowStudio\Tenancy\Commands\Tenancy\TenantCommand::class,
                \ArtflowStudio\Tenancy\Commands\Tenancy\HealthCheckCommand::class,
                \ArtflowStudio\Tenancy\Commands\Tenancy\CreateTestTenantsCommand::class,
                \ArtflowStudio\Tenancy\Commands\Tenancy\LinkAssetsCommand::class,
                \ArtflowStudio\Tenancy\Commands\Tenancy\FastPanelCommand::class,

                // FastPanel group
                \ArtflowStudio\Tenancy\Commands\FastPanel\CreateTenantCommand::class,
                \ArtflowStudio\Tenancy\Commands\FastPanel\ListUsersCommand::class,
                \ArtflowStudio\Tenancy\Commands\FastPanel\ListDatabasesCommand::class,
                \ArtflowStudio\Tenancy\Commands\FastPanel\SyncDatabaseCommand::class,

                // Database group
                \ArtflowStudio\Tenancy\Commands\Database\TenantDatabaseCommand::class,
                \ArtflowStudio\Tenancy\Commands\Database\FixTenantDatabasesCommand::class,
                \ArtflowStudio\Tenancy\Commands\Database\CheckPrivilegesCommand::class,

                // Testing group
                \ArtflowStudio\Tenancy\Commands\Testing\TestSystemCommand::class,
                \ArtflowStudio\Tenancy\Commands\Testing\TestPerformanceCommand::class,
                \ArtflowStudio\Tenancy\Commands\Testing\QuickInstallTestCommand::class,
                \ArtflowStudio\Tenancy\Commands\Testing\ServerCompatibilityCommand::class,
                
                // Integration validation
                \ArtflowStudio\Tenancy\Console\Commands\ValidateIntegrationsCommand::class,
                \ArtflowStudio\Tenancy\Commands\Testing\ComprehensiveTestCommand::class,
            ];

            $allCommands = array_values(array_unique(array_merge($commandClasses, $fallback)));

            if (!empty($allCommands)) {
                $this->commands($allCommands);
            }
        }

        $this->registerMiddleware();
        $this->configureLivewire();
        $this->registerAdminFeatures();
    }

    protected function registerAdminFeatures(): void
    {
        // Conditionally register admin routes when configured
        if (config('artflow-tenancy.admin.enabled', false)) {
            $adminRoutes = __DIR__ . '/../routes/admin.php';
            if (file_exists($adminRoutes)) {
                $this->loadRoutesFrom($adminRoutes);
            }
        }

        // If Livewire exists, optionally register components explicitly
        if (class_exists(\Livewire\Livewire::class)) {
            // Register critical dotted aliases first to ensure compiled views referencing
            // long dotted component names can always be resolved even if other
            // registrations fail during static analysis.
            try {
                \Livewire\Livewire::component('artflow-studio.tenancy.http.livewire.admin.queue-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring::class);
            } catch (\Throwable $_e) {
                // ignore - best-effort
            }

            try {
                \Livewire\Livewire::component('af-tenancy.admin.dashboard', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard::class);
                \Livewire\Livewire::component('af-tenancy.admin.tenants-index', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex::class);
                \Livewire\Livewire::component('af-tenancy.admin.create-tenant', \ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant::class);
                \Livewire\Livewire::component('af-tenancy.admin.view-tenant', \ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant::class);

                // Additional admin components
                \Livewire\Livewire::component('af-tenancy.admin.queue-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring::class);
                \Livewire\Livewire::component('af-tenancy.admin.system-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring::class);
                \Livewire\Livewire::component('af-tenancy.admin.tenant-analytics', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics::class);

                // Also register the dotted FQCN style aliases that may be referenced in compiled views
                \Livewire\Livewire::component('artflow-studio.tenancy.http.livewire.admin.queue-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring::class);
                \Livewire\Livewire::component('artflow-studio.tenancy.http.livewire.admin.system-monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring::class);
                \Livewire\Livewire::component('artflow-studio.tenancy.http.livewire.admin.tenant-analytics', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics::class);
            } catch (\Throwable $e) {
                // Ignore registration errors during static analysis or when Livewire not fully booted
            }
        }
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
            // Register persistent middleware immediately
            Livewire::addPersistentMiddleware([
                \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            ]);

            // Resolve missing dotted component names by converting dotted names
            // into a studly FQCN. This ensures compiled snapshots referencing
            // vendor dotted component names can be instantiated.
            try {
                Livewire::resolveMissingComponent(function ($name) {
                    // Convert dotted name to studly class segments
                    $segments = collect(explode('.', $name))->map(fn ($seg) => (string) \Illuminate\Support\Str::studly($seg));
                    $class = '\\' . $segments->join('\\');

                    if (class_exists($class) && is_subclass_of($class, \Livewire\Component::class)) {
                        return $class;
                    }

                    return null;
                });
            } catch (\Throwable $_e) {
                // best-effort
            }

            // Ensure persistent middleware is also added after application boot
            $this->app->booted(function () {
                // Note: nothing additional required here for now, but keep hook
                // to align with previous behavior.
            });
        }
    }
}
