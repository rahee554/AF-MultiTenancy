<?php

namespace ArtflowStudio\Tenancy\Pulse\Recorders;

use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Facades\Pulse;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenancyEnded;
use ArtflowStudio\Tenancy\Events\TenantRequestProcessed;
use ArtflowStudio\Tenancy\Events\TenantDatabaseQueried;
use ArtflowStudio\Tenancy\Events\TenantCacheAccessed;

class TenantRecorder
{
    /**
     * The events to listen for.
     *
     * @var array<int, class-string>
     */
    public array $listen = [
        TenancyInitialized::class,
        TenancyEnded::class,
        TenantRequestProcessed::class,
        TenantDatabaseQueried::class,
        TenantCacheAccessed::class,
    ];

    /**
     * Record tenant initialization.
     */
    public function recordTenancyInitialized(TenancyInitialized $event): void
    {
        $config = Config::get('pulse.recorders.'.static::class);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $tenant = $event->tenancy->tenant;
        $tenantName = $tenant->name ?? $tenant->id;

        Pulse::record(
            type: 'tenant_initialization',
            key: $tenantName,
            value: 1,
            timestamp: now()->timestamp
        )->count();

        // Tag with tenant identifier
        Pulse::record(
            type: 'tenant_activity',
            key: $tenantName,
            value: 1,
            timestamp: now()->timestamp
        )->sum()->count();
    }

    /**
     * Record tenant request processing.
     */
    public function recordTenantRequestProcessed(TenantRequestProcessed $event): void
    {
        $config = Config::get('pulse.recorders.'.static::class);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $tenantName = $event->tenantName;
        $duration = $event->duration;

        // Record request count and duration
        Pulse::record(
            type: 'tenant_requests',
            key: $tenantName,
            value: 1,
            timestamp: now()->timestamp
        )->count();

        Pulse::record(
            type: 'tenant_request_duration',
            key: $tenantName,
            value: $duration,
            timestamp: now()->timestamp
        )->avg()->max();
    }

    /**
     * Record tenant database query.
     */
    public function recordTenantDatabaseQueried(TenantDatabaseQueried $event): void
    {
        $config = Config::get('pulse.recorders.'.static::class);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $tenantName = $event->tenantName;
        $queryTime = $event->queryTime;

        Pulse::record(
            type: 'tenant_database_queries',
            key: $tenantName,
            value: 1,
            timestamp: now()->timestamp
        )->count();

        Pulse::record(
            type: 'tenant_query_time',
            key: $tenantName,
            value: $queryTime,
            timestamp: now()->timestamp
        )->avg()->max();
    }

    /**
     * Record tenant cache access.
     */
    public function recordTenantCacheAccessed(TenantCacheAccessed $event): void
    {
        $config = Config::get('pulse.recorders.'.static::class);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $tenantName = $event->tenantName;
        $operation = $event->operation; // 'get', 'put', 'forget'

        Pulse::record(
            type: 'tenant_cache_operations',
            key: "{$tenantName}:{$operation}",
            value: 1,
            timestamp: now()->timestamp
        )->count();
    }

    /**
     * Record any tenant event.
     */
    public function record($event): void
    {
        $method = 'record' . class_basename($event);
        
        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }
}
