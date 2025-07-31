<?php

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Stancl\Tenancy\Contracts\TenantBootstrapper;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Laravel Horizon Tenancy Bootstrapper
 * 
 * Provides tenant isolation for Laravel Horizon:
 * - Tenant-specific queue names
 * - Tenant-specific supervisor configuration
 * - Tenant context preservation in queued jobs
 */
class HorizonTenancyBootstrapper implements TenantBootstrapper
{
    /**
     * Bootstrap Horizon for tenant context
     */
    public function bootstrap(TenantWithDatabase $tenant): void
    {
        if (!$this->isHorizonAvailable()) {
            return;
        }

        try {
            $tenantId = $tenant->getTenantKey();
            
            Log::debug('Horizon Bootstrap: Configuring for tenant', [
                'tenant_id' => $tenantId
            ]);
            
            // Configure tenant-specific queue names
            $this->configureTenantQueues($tenantId);
            
            // Set tenant context in queue configuration
            $this->setTenantQueueContext($tenantId);
            
            Log::info('Horizon Bootstrap: Successfully configured for tenant', [
                'tenant_id' => $tenantId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Horizon Bootstrap: Failed to configure for tenant', [
                'tenant_id' => $tenant->getTenantKey(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Revert Horizon configuration to central state
     */
    public function revert(): void
    {
        if (!$this->isHorizonAvailable()) {
            return;
        }

        try {
            Log::debug('Horizon Bootstrap: Reverting to central configuration');
            
            // Restore original queue configuration
            $this->restoreOriginalQueueConfig();
            
            Log::info('Horizon Bootstrap: Successfully reverted to central configuration');
            
        } catch (\Exception $e) {
            Log::error('Horizon Bootstrap: Failed to revert configuration', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if Laravel Horizon is available
     */
    protected function isHorizonAvailable(): bool
    {
        return class_exists(\Laravel\Horizon\HorizonServiceProvider::class);
    }

    /**
     * Configure tenant-specific queue names
     */
    protected function configureTenantQueues(string $tenantId): void
    {
        // Store original queue configuration if not already stored
        if (!app()->has('queue.original.config')) {
            app()->instance('queue.original.config', [
                'default_queue' => config('queue.connections.redis.queue', 'default'),
                'failed_queue' => config('queue.failed.database', 'mysql'),
            ]);
        }
        
        // Configure tenant-specific queue names
        $tenantQueue = "tenant_{$tenantId}_default";
        $tenantFailedQueue = "tenant_{$tenantId}_failed";
        
        config([
            'queue.connections.redis.queue' => $tenantQueue,
            'queue.connections.database.queue' => $tenantQueue,
            'horizon.defaults.supervisor-1.queue' => $tenantQueue,
        ]);
        
        // Configure Horizon supervisor with tenant-specific settings
        $this->configureHorizonSupervisor($tenantId, $tenantQueue);
    }

    /**
     * Configure Horizon supervisor for tenant
     */
    protected function configureHorizonSupervisor(string $tenantId, string $queueName): void
    {
        $supervisorKey = "tenant_{$tenantId}_supervisor";
        
        // Merge tenant-specific supervisor configuration
        $supervisorConfig = config('horizon.supervisors', []);
        $supervisorConfig[$supervisorKey] = [
            'connection' => 'redis',
            'queue' => [$queueName],
            'balance' => config('artflow-tenancy.horizon.balance_strategy', 'simple'),
            'processes' => config('artflow-tenancy.horizon.processes_per_tenant', 1),
            'tries' => config('artflow-tenancy.horizon.max_tries', 3),
            'nice' => config('artflow-tenancy.horizon.nice', 0),
            'timeout' => config('artflow-tenancy.horizon.timeout', 60),
            'memory' => config('artflow-tenancy.horizon.memory_limit', 64),
        ];
        
        config(['horizon.supervisors' => $supervisorConfig]);
    }

    /**
     * Set tenant context in queue configuration
     */
    protected function setTenantQueueContext(string $tenantId): void
    {
        // Set tenant context that will be available in queued jobs
        config([
            'tenancy.queue.tenant_id' => $tenantId,
            'tenancy.queue.auto_initialize' => true,
        ]);
    }

    /**
     * Restore original queue configuration
     */
    protected function restoreOriginalQueueConfig(): void
    {
        if (app()->has('queue.original.config')) {
            $originalConfig = app('queue.original.config');
            
            config([
                'queue.connections.redis.queue' => $originalConfig['default_queue'],
                'queue.connections.database.queue' => $originalConfig['default_queue'],
                'queue.failed.database' => $originalConfig['failed_queue'],
            ]);
        }
        
        // Clear tenant context
        config([
            'tenancy.queue.tenant_id' => null,
            'tenancy.queue.auto_initialize' => false,
        ]);
    }
}
