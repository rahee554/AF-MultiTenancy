<?php

namespace ArtflowStudio\Tenancy\Commands\Maintenance;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Services\TenantMaintenanceMode;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Tenant Maintenance Mode Command
 * 
 * Manage maintenance mode for individual tenants
 */
class TenantMaintenanceModeCommand extends Command
{
    protected $signature = 'tenants:maintenance 
                          {action : Action: enable, disable, status, list}
                          {--tenant= : Tenant ID}
                          {--all : Apply to all tenants}
                          {--message= : Maintenance message}
                          {--allowed-ips= : Comma-separated list of allowed IPs}
                          {--bypass-key= : Bypass key for maintenance mode}
                          {--retry-after= : Retry-After header value in seconds}
                          {--admin-contact= : Admin contact information}';

    protected $description = 'Manage maintenance mode for tenants';

    protected $maintenanceService;

    public function __construct(TenantMaintenanceMode $maintenanceService)
    {
        parent::__construct();
        $this->maintenanceService = $maintenanceService;
    }

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'enable':
                return $this->enableMaintenance();
            case 'disable':
                return $this->disableMaintenance();
            case 'status':
                return $this->showStatus();
            case 'list':
                return $this->listTenantsInMaintenance();
            default:
                $this->error("Invalid action: {$action}");
                $this->line("Available actions: enable, disable, status, list");
                return 1;
        }
    }

    /**
     * Enable maintenance mode
     */
    protected function enableMaintenance()
    {
        $tenantId = $this->option('tenant');
        $applyToAll = $this->option('all');

        if (!$tenantId && !$applyToAll) {
            $this->error('Please specify --tenant=ID or --all');
            return 1;
        }

        $options = [
            'message' => $this->option('message') ?: 'This tenant is temporarily unavailable for maintenance.',
            'allowed_ips' => $this->option('allowed-ips') ? explode(',', $this->option('allowed-ips')) : [],
            'bypass_key' => $this->option('bypass-key'),
            'retry_after' => $this->option('retry-after') ? (int) $this->option('retry-after') : 3600,
            'admin_contact' => $this->option('admin-contact'),
        ];

        if ($applyToAll) {
            return $this->enableMaintenanceForAll($options);
        } else {
            return $this->enableMaintenanceForTenant($tenantId, $options);
        }
    }

    /**
     * Enable maintenance for specific tenant
     */
    protected function enableMaintenanceForTenant(string $tenantId, array $options)
    {
        $this->info("Enabling maintenance mode for tenant: {$tenantId}");

        // Verify tenant exists
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return 1;
        }

        $success = $this->maintenanceService->enableForTenant($tenantId, $options);

        if ($success) {
            $this->info("✅ Maintenance mode enabled for tenant: {$tenantId}");
            $this->displayMaintenanceInfo($tenantId, $options);
        } else {
            $this->error("❌ Failed to enable maintenance mode for tenant: {$tenantId}");
            return 1;
        }

        return 0;
    }

    /**
     * Enable maintenance for all tenants
     */
    protected function enableMaintenanceForAll(array $options)
    {
        $this->info("Enabling maintenance mode for ALL tenants");

        if (!$this->confirm('Are you sure you want to enable maintenance mode for ALL tenants?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        Tenant::chunk(50, function ($tenants) use ($options, &$successCount, &$failureCount) {
            foreach ($tenants as $tenant) {
                $success = $this->maintenanceService->enableForTenant($tenant->id, $options);
                
                if ($success) {
                    $successCount++;
                    $this->line("✅ {$tenant->id}");
                } else {
                    $failureCount++;
                    $this->line("❌ {$tenant->id}");
                }
            }
        });

        $this->info("Maintenance mode enabled for {$successCount} tenants");
        if ($failureCount > 0) {
            $this->warn("Failed to enable for {$failureCount} tenants");
        }

        return 0;
    }

    /**
     * Disable maintenance mode
     */
    protected function disableMaintenance()
    {
        $tenantId = $this->option('tenant');
        $applyToAll = $this->option('all');

        if (!$tenantId && !$applyToAll) {
            $this->error('Please specify --tenant=ID or --all');
            return 1;
        }

        if ($applyToAll) {
            return $this->disableMaintenanceForAll();
        } else {
            return $this->disableMaintenanceForTenant($tenantId);
        }
    }

    /**
     * Disable maintenance for specific tenant
     */
    protected function disableMaintenanceForTenant(string $tenantId)
    {
        $this->info("Disabling maintenance mode for tenant: {$tenantId}");

        $success = $this->maintenanceService->disableForTenant($tenantId);

        if ($success) {
            $this->info("✅ Maintenance mode disabled for tenant: {$tenantId}");
        } else {
            $this->error("❌ Failed to disable maintenance mode for tenant: {$tenantId}");
            return 1;
        }

        return 0;
    }

    /**
     * Disable maintenance for all tenants
     */
    protected function disableMaintenanceForAll()
    {
        $this->info("Disabling maintenance mode for ALL tenants");

        if (!$this->confirm('Are you sure you want to disable maintenance mode for ALL tenants?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $tenantsInMaintenance = $this->maintenanceService->getTenantsInMaintenance();
        $successCount = 0;
        $failureCount = 0;

        foreach ($tenantsInMaintenance as $tenantData) {
            $success = $this->maintenanceService->disableForTenant($tenantData['id']);
            
            if ($success) {
                $successCount++;
                $this->line("✅ {$tenantData['id']}");
            } else {
                $failureCount++;
                $this->line("❌ {$tenantData['id']}");
            }
        }

        $this->info("Maintenance mode disabled for {$successCount} tenants");
        if ($failureCount > 0) {
            $this->warn("Failed to disable for {$failureCount} tenants");
        }

        return 0;
    }

    /**
     * Show maintenance status for tenant
     */
    protected function showStatus()
    {
        $tenantId = $this->option('tenant');

        if (!$tenantId) {
            $this->error('Please specify --tenant=ID');
            return 1;
        }

        $this->info("Maintenance status for tenant: {$tenantId}");

        $inMaintenance = $this->maintenanceService->isInMaintenanceMode($tenantId);

        if ($inMaintenance) {
            $this->line("   🔧 Status: IN MAINTENANCE");
            
            // Get maintenance data for details
            $maintenanceData = $this->maintenanceService->getMaintenanceData($tenantId);
            if ($maintenanceData) {
                $this->displayMaintenanceDetails($maintenanceData);
            }
        } else {
            $this->line("   ✅ Status: ACTIVE");
        }

        return 0;
    }

    /**
     * List all tenants in maintenance mode
     */
    protected function listTenantsInMaintenance()
    {
        $this->info("Tenants currently in maintenance mode:");

        $tenantsInMaintenance = $this->maintenanceService->getTenantsInMaintenance();

        if (empty($tenantsInMaintenance)) {
            $this->line("   No tenants are currently in maintenance mode");
            return 0;
        }

        $this->line('');
        foreach ($tenantsInMaintenance as $tenantData) {
            $this->line("🔧 {$tenantData['id']}");
            
            if (!empty($tenantData['maintenance_data'])) {
                $data = $tenantData['maintenance_data'];
                $this->line("   Message: {$data['message']}");
                $this->line("   Enabled at: {$data['enabled_at']}");
                
                if (!empty($data['admin_contact'])) {
                    $this->line("   Contact: {$data['admin_contact']}");
                }
            }
            
            $this->line('');
        }

        $this->info("Total: " . count($tenantsInMaintenance) . " tenants in maintenance mode");

        return 0;
    }

    /**
     * Display maintenance info after enabling
     */
    protected function displayMaintenanceInfo(string $tenantId, array $options)
    {
        $this->line('');
        $this->line('Maintenance Mode Details:');
        $this->line("  Message: {$options['message']}");
        
        if (!empty($options['allowed_ips'])) {
            $this->line("  Allowed IPs: " . implode(', ', $options['allowed_ips']));
        }
        
        if (!empty($options['bypass_key'])) {
            $this->line("  Bypass Key: {$options['bypass_key']}");
            $this->line("  Bypass URL: ?bypass_key={$options['bypass_key']}");
        }
        
        $this->line("  Retry After: {$options['retry_after']} seconds");
        
        if (!empty($options['admin_contact'])) {
            $this->line("  Admin Contact: {$options['admin_contact']}");
        }
        
        $this->line('');
    }

    /**
     * Display detailed maintenance info
     */
    protected function displayMaintenanceDetails(array $maintenanceData)
    {
        $this->line("   Message: {$maintenanceData['message']}");
        $this->line("   Enabled at: {$maintenanceData['enabled_at']}");
        
        if (!empty($maintenanceData['allowed_ips'])) {
            $this->line("   Allowed IPs: " . implode(', ', $maintenanceData['allowed_ips']));
        }
        
        if (!empty($maintenanceData['bypass_key'])) {
            $this->line("   Bypass Key: {$maintenanceData['bypass_key']}");
        }
        
        $this->line("   Retry After: {$maintenanceData['retry_after']} seconds");
        
        if (!empty($maintenanceData['admin_contact'])) {
            $this->line("   Admin Contact: {$maintenanceData['admin_contact']}");
        }
    }
}
