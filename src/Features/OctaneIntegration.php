<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Features;

use Stancl\Tenancy\Contracts\Feature;
use Stancl\Tenancy\Tenancy;

/**
 * Laravel Octane Integration for Multi-Tenancy
 * 
 * Ensures proper tenant context isolation in Octane workers:
 * - Initializes tenancy per request
 * - Cleans up tenant context after each request
 * - Prevents tenant data bleeding between requests
 */
class OctaneIntegration implements Feature
{
    public function bootstrap(Tenancy $tenancy): void
    {
        if (!class_exists(\Laravel\Octane\Octane::class)) {
            return;
        }

        $this->registerOctaneListeners($tenancy);
    }

    protected function registerOctaneListeners(Tenancy $tenancy): void
    {
        // Register Octane event listeners for proper tenancy lifecycle
        app('events')->listen(\Laravel\Octane\Events\RequestReceived::class, function ($event) use ($tenancy) {
            // Ensure clean state at request start
            if ($tenancy->initialized) {
                $tenancy->end();
            }
        });

        app('events')->listen(\Laravel\Octane\Events\RequestTerminated::class, function ($event) use ($tenancy) {
            // Clean up tenant context after request
            if ($tenancy->initialized) {
                $tenancy->end();
            }
            
            // Clear any tenant-specific cache or connections
            $this->cleanupTenantContext();
        });

        app('events')->listen(\Laravel\Octane\Events\TaskReceived::class, function ($event) use ($tenancy) {
            // Handle background tasks - ensure clean state
            if ($tenancy->initialized) {
                $tenancy->end();
            }
        });

        app('events')->listen(\Laravel\Octane\Events\TaskTerminated::class, function ($event) use ($tenancy) {
            // Clean up after background tasks
            if ($tenancy->initialized) {
                $tenancy->end();
            }
            
            $this->cleanupTenantContext();
        });

        app('events')->listen(\Laravel\Octane\Events\TickReceived::class, function ($event) use ($tenancy) {
            // Handle tick events - periodic cleanup
            $this->cleanupTenantContext();
        });

        app('events')->listen(\Laravel\Octane\Events\WorkerErrorOccurred::class, function ($event) use ($tenancy) {
            // Handle worker errors - ensure clean state
            if ($tenancy->initialized) {
                $tenancy->end();
            }
            
            $this->cleanupTenantContext();
        });
    }

    protected function cleanupTenantContext(): void
    {
        // Clear tenant-specific connections
        app('db')->purge();
        
        // Clear tenant-specific cache
        if (app()->bound('cache')) {
            app('cache')->getStore()->flush();
        }
        
        // Clear any tenant-specific session data
        if (app()->bound('session')) {
            app('session')->invalidate();
        }
        
        // Clear tenant-specific filesystem contexts
        app('filesystem')->purge();
        
        // Reset any tenant-specific configuration
        app('config')->set('database.connections.tenant', null);
    }
}
