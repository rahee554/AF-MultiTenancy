<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class EnhancedCacheTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var string|null Original cache driver */
    protected $originalDriver;

    /** @var string|null Original cache connection */
    protected $originalConnection;

    /** @var string|null Original cache table */
    protected $originalTable;

    /** @var string|null Original cache prefix */
    protected $originalPrefix;

    public function bootstrap(Tenant $tenant)
    {
        $isolationMode = config('artflow-tenancy.cache.isolation_mode', 'tags');

        if ($isolationMode === 'database') {
            $this->bootstrapDatabaseIsolation($tenant);
        } elseif ($isolationMode === 'prefix') {
            $this->bootstrapPrefixIsolation($tenant);
        }
        // Default 'tags' mode is handled by stancl/tenancy's CacheTenancyBootstrapper
    }

    protected function bootstrapDatabaseIsolation(Tenant $tenant)
    {
        // Store original configuration
        $this->originalDriver = config('cache.default');
        $this->originalConnection = config('cache.stores.database.connection');
        $this->originalTable = config('cache.stores.database.table');

        // Set cache to use tenant database
        if (config('cache.default') === 'database') {
            config([
                'cache.stores.database.connection' => 'tenant',
                'cache.stores.database.table' => config('artflow-tenancy.cache.table', 'cache'),
            ]);

            // Clear cache manager to force recreation
            app()->forgetInstance('cache');
            app()->forgetInstance('cache.store');
        }
    }

    protected function bootstrapPrefixIsolation(Tenant $tenant)
    {
        // Store original prefix
        $this->originalPrefix = config('cache.prefix');

        // Set tenant-specific cache prefix
        $tenantPrefix = config('artflow-tenancy.cache.prefix_pattern', 'tenant_{tenant_id}_');
        $prefix = str_replace('{tenant_id}', $tenant->getTenantKey(), $tenantPrefix);

        config(['cache.prefix' => $prefix]);

        // Clear cache manager to apply new prefix
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
    }

    public function revert()
    {
        $isolationMode = config('artflow-tenancy.cache.isolation_mode', 'tags');

        if ($isolationMode === 'database' && $this->originalDriver) {
            // Restore original cache configuration
            config([
                'cache.default' => $this->originalDriver,
                'cache.stores.database.connection' => $this->originalConnection,
                'cache.stores.database.table' => $this->originalTable,
            ]);

            // Clear cache manager
            app()->forgetInstance('cache');
            app()->forgetInstance('cache.store');
        } elseif ($isolationMode === 'prefix' && $this->originalPrefix !== null) {
            // Restore original prefix
            config(['cache.prefix' => $this->originalPrefix]);

            // Clear cache manager
            app()->forgetInstance('cache');
            app()->forgetInstance('cache.store');
        }

        // Reset stored values
        $this->originalDriver = null;
        $this->originalConnection = null;
        $this->originalTable = null;
        $this->originalPrefix = null;
    }
}
