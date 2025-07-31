<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class SessionTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var string|null Original session driver */
    protected $originalDriver;

    /** @var string|null Original session connection */
    protected $originalConnection;

    /** @var string|null Original session table */
    protected $originalTable;

    public function bootstrap(Tenant $tenant)
    {
        // Store original session configuration
        $this->originalDriver = config('session.driver');
        $this->originalConnection = config('session.connection');
        $this->originalTable = config('session.table');

        // Only modify if using database sessions
        if (config('session.driver') === 'database') {
            // Set session to use tenant database connection
            config([
                'session.connection' => 'tenant',
                'session.table' => config('artflow-tenancy.session.table', 'sessions'),
            ]);

            // Clear any existing session store to force recreation
            if (app()->bound('session.store')) {
                app()->forgetInstance('session.store');
            }

            // Rebuild session manager to use new configuration
            app()->forgetInstance('session');
            app()->singleton('session', function ($app) {
                return $app['session.manager']->driver();
            });
        }
    }

    public function revert()
    {
        if ($this->originalDriver && config('session.driver') === 'database') {
            // Restore original session configuration
            config([
                'session.driver' => $this->originalDriver,
                'session.connection' => $this->originalConnection,
                'session.table' => $this->originalTable,
            ]);

            // Clear session store to force recreation with original config
            if (app()->bound('session.store')) {
                app()->forgetInstance('session.store');
            }

            // Rebuild session manager with original configuration
            app()->forgetInstance('session');
            app()->singleton('session', function ($app) {
                return $app['session.manager']->driver();
            });
        }

        // Reset stored values
        $this->originalDriver = null;
        $this->originalConnection = null;
        $this->originalTable = null;
    }
}
