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
                          {action? : Action: enable, disable, status, list (optional - will prompt if not provided)}
                          {--tenant= : Tenant ID}
                          {--all : Apply to all tenants}
                          {--message= : Maintenance message}
                          {--allowed-ips= : Comma-separated list of allowed IPs}
                          {--bypass-key= : Bypass key for maintenance mode}
                          {--retry-after= : Retry-After header value in seconds}
                          {--admin-contact= : Admin contact information}
                          {--auto-accept : Auto accept all prompts (for automation)}';

    protected $description = 'Manage maintenance mode for tenants - Interactive interface';

    protected $maintenanceService;

    public function __construct(TenantMaintenanceMode $maintenanceService)
    {
        parent::__construct();
        $this->maintenanceService = $maintenanceService;
    }

    public function handle()
    {
        $this->displayHeader();
        
        $action = $this->argument('action');

        // If no action provided, make it interactive
        if (!$action) {
            $action = $this->selectAction();
        }

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
     * Display command header
     */
    protected function displayHeader()
    {
        $this->info('🔧 Tenant Maintenance Mode Manager');
        $this->info('=====================================');
        $this->newLine();
    }

    /**
     * Interactive action selection
     */
    protected function selectAction()
    {
        $this->info('What would you like to do?');
        $this->line('  [0] 🔧 Enable maintenance mode');
        $this->line('  [1] ✅ Disable maintenance mode'); 
        $this->line('  [2] 📊 Check maintenance status');
        $this->line('  [3] 📋 List tenants in maintenance');
        $this->newLine();

        $choice = $this->ask('Select option by number', '3');
        
        switch ($choice) {
            case '0':
                return 'enable';
            case '1':
                return 'disable';
            case '2':
                return 'status';
            case '3':
                return 'list';
            default:
                $this->error('Invalid choice. Please select 0-3.');
                return $this->selectAction();
        }
    }

    /**
     * Enable maintenance mode - Interactive version
     */
    protected function enableMaintenance()
    {
        $this->info('🔧 Enable Maintenance Mode');
        $this->line('========================');
        $this->newLine();

        // Check if options are provided or make it interactive
        $tenantId = $this->option('tenant');
        $applyToAll = $this->option('all');
        $autoAccept = $this->option('auto-accept');

        // Interactive tenant selection if not specified
        if (!$tenantId && !$applyToAll) {
            $tenantSelection = $this->selectTenantForMaintenance();
            
            if ($tenantSelection === 'all') {
                $applyToAll = true;
            } elseif ($tenantSelection === 'cancel') {
                $this->info('Operation cancelled');
                return 0;
            } else {
                $tenantId = $tenantSelection;
            }
        }

        // Get maintenance options interactively
        $options = $this->getMaintenanceOptions();

        // Confirmation
        if (!$autoAccept) {
            if ($applyToAll) {
                if (!$this->confirm('⚠️  Enable maintenance mode for ALL tenants?', false)) {
                    $this->info('Operation cancelled');
                    return 0;
                }
            } else {
                if (!$this->confirm("Enable maintenance mode for tenant: {$tenantId}?", true)) {
                    $this->info('Operation cancelled');
                    return 0;
                }
            }
        }

        if ($applyToAll) {
            return $this->enableMaintenanceForAll($options);
        } else {
            return $this->enableMaintenanceForTenant($tenantId, $options);
        }
    }

    /**
     * Interactive tenant selection for maintenance
     */
    protected function selectTenantForMaintenance()
    {
        $this->info('📋 Select Tenant for Maintenance');
        $this->line('');

        // Get all tenants
        $tenants = Tenant::select('id', 'name', 'data')->get();
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found');
            return 'cancel';
        }

        // Display tenants in a nice table
        $this->displayTenantsTable($tenants);

        $this->info('Available options:');
        $this->line('  [all] 🌐 ALL TENANTS');
        $this->line('  [cancel] ❌ Cancel Operation');
        $this->newLine();

        // Add individual tenants
        foreach ($tenants as $index => $tenant) {
            $name = $tenant->name ?? 'Unnamed';
            $domains = $this->getTenantDomains($tenant);
            $domain = $domains[0] ?? 'No domain';
            
            $this->line("  [{$index}] {$name} ({$domain})");
        }

        $this->newLine();
        $choice = $this->ask('Select option by number or type "all"/"cancel"', 'cancel');

        if ($choice === 'all') {
            return 'all';
        } elseif ($choice === 'cancel') {
            return 'cancel';
        } elseif (is_numeric($choice) && isset($tenants[$choice])) {
            return $tenants[$choice]->id;
        } else {
            $this->error('Invalid selection.');
            return $this->selectTenantForMaintenance();
        }
    }

    /**
     * Display tenants in a formatted table
     */
    protected function displayTenantsTable($tenants)
    {
        $tableData = [];
        
        foreach ($tenants as $index => $tenant) {
            $name = $tenant->name ?? 'Unnamed';
            $domains = $this->getTenantDomains($tenant);
            $domain = $domains[0] ?? 'No domain';
            $database = $tenant->database ?? 'N/A';
            
            // Check if in maintenance
            $status = $this->maintenanceService->isInMaintenanceMode($tenant->id) ? '🔧 MAINTENANCE' : '✅ Active';
            
            $tableData[] = [
                $index,
                $name,
                $domain,
                $database,
                substr($tenant->id, 0, 8) . '...',
                $status
            ];
        }

        $this->table(
            ['#', 'Name', 'Domain', 'Database', 'ID', 'Status'],
            $tableData
        );
        
        $this->newLine();
    }

    /**
     * Get tenant domains from data
     */
    protected function getTenantDomains($tenant)
    {
        $data = is_array($tenant->data) ? $tenant->data : [];
        return $data['domains'] ?? [];
    }

    /**
     * Get maintenance options interactively
     */
    protected function getMaintenanceOptions()
    {
        $this->info('🛠️  Maintenance Configuration');
        $this->line('');

        // Get message
        $message = $this->option('message') ?: $this->ask(
            'Maintenance message',
            'This tenant is temporarily unavailable for maintenance.'
        );

        // Get retry after
        $retryAfter = $this->option('retry-after') ?: $this->ask(
            'Retry after (seconds)',
            '3600'
        );

        // Optional configurations
        $configureAdvanced = $this->confirm('Configure advanced options? (IPs, bypass key, contact)', false);
        
        $allowedIps = [];
        $bypassKey = null;
        $adminContact = null;

        if ($configureAdvanced) {
            // Allowed IPs
            $ipsInput = $this->option('allowed-ips') ?: $this->ask('Allowed IPs (comma-separated, leave empty for none)');
            if ($ipsInput) {
                $allowedIps = array_map('trim', explode(',', $ipsInput));
            }

            // Bypass key
            $bypassKey = $this->option('bypass-key') ?: $this->ask('Bypass key (leave empty for none)');

            // Admin contact
            $adminContact = $this->option('admin-contact') ?: $this->ask('Admin contact info (leave empty for none)');
        }

        return [
            'message' => $message,
            'allowed_ips' => $allowedIps,
            'bypass_key' => $bypassKey,
            'retry_after' => (int) $retryAfter,
            'admin_contact' => $adminContact,
        ];
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
     * Disable maintenance mode - Interactive version
     */
    protected function disableMaintenance()
    {
        $this->info('✅ Disable Maintenance Mode');
        $this->line('=========================');
        $this->newLine();

        $tenantId = $this->option('tenant');
        $applyToAll = $this->option('all');
        $autoAccept = $this->option('auto-accept');

        // Interactive tenant selection if not specified
        if (!$tenantId && !$applyToAll) {
            $tenantSelection = $this->selectTenantForDisabling();
            
            if ($tenantSelection === 'all') {
                $applyToAll = true;
            } elseif ($tenantSelection === 'cancel') {
                $this->info('Operation cancelled');
                return 0;
            } else {
                $tenantId = $tenantSelection;
            }
        }

        // Confirmation
        if (!$autoAccept) {
            if ($applyToAll) {
                if (!$this->confirm('⚠️  Disable maintenance mode for ALL tenants?', false)) {
                    $this->info('Operation cancelled');
                    return 0;
                }
            } else {
                if (!$this->confirm("Disable maintenance mode for tenant: {$tenantId}?", true)) {
                    $this->info('Operation cancelled');
                    return 0;
                }
            }
        }

        if ($applyToAll) {
            return $this->disableMaintenanceForAll();
        } else {
            return $this->disableMaintenanceForTenant($tenantId);
        }
    }

    /**
     * Interactive tenant selection for disabling maintenance
     */
    protected function selectTenantForDisabling()
    {
        $this->info('📋 Select Tenant to Disable Maintenance');
        $this->line('');

        // Get tenants currently in maintenance
        $tenantsInMaintenance = $this->maintenanceService->getTenantsInMaintenance();
        
        if (empty($tenantsInMaintenance)) {
            $this->warn('No tenants are currently in maintenance mode');
            return 'cancel';
        }

        // Get all tenants for full display
        $allTenants = Tenant::select('id', 'name', 'data')->get();
        
        // Display all tenants with maintenance status
        $this->displayTenantsTableWithMaintenanceStatus($allTenants);

        $this->info('Available options:');
        $this->line('  [all] 🌐 ALL TENANTS (disable all)');
        $this->line('  [cancel] ❌ Cancel Operation');
        $this->newLine();

        // Add tenants in maintenance
        $maintenanceIndex = 0;
        foreach ($tenantsInMaintenance as $tenantData) {
            $tenant = $allTenants->firstWhere('id', $tenantData['id']);
            if ($tenant) {
                $name = $tenant->name ?? 'Unnamed';
                $domains = $this->getTenantDomains($tenant);
                $domain = $domains[0] ?? 'No domain';
                
                $this->line("  [{$maintenanceIndex}] 🔧 {$name} ({$domain}) - IN MAINTENANCE");
                $maintenanceIndex++;
            }
        }

        $this->newLine();
        $choice = $this->ask('Select option by number or type "all"/"cancel"', 'cancel');

        if ($choice === 'all') {
            return 'all';
        } elseif ($choice === 'cancel') {
            return 'cancel';
        } elseif (is_numeric($choice) && isset($tenantsInMaintenance[$choice])) {
            return $tenantsInMaintenance[$choice]['id'];
        } else {
            $this->error('Invalid selection.');
            return $this->selectTenantForDisabling();
        }
    }

    /**
     * Display tenants table with maintenance status
     */
    protected function displayTenantsTableWithMaintenanceStatus($tenants)
    {
        $tableData = [];
        $maintenanceIndex = 0;
        
        foreach ($tenants as $tenant) {
            $name = $tenant->name ?? 'Unnamed';
            $domains = $this->getTenantDomains($tenant);
            $domain = $domains[0] ?? 'No domain';
            $database = $tenant->database ?? 'N/A';
            
            $isInMaintenance = $this->maintenanceService->isInMaintenanceMode($tenant->id);
            $status = $isInMaintenance ? '🔧 MAINTENANCE' : '✅ Active';
            $index = $isInMaintenance ? $maintenanceIndex++ : '-';
            
            $tableData[] = [
                $index,
                $name,
                $domain,
                $database,
                substr($tenant->id, 0, 8) . '...',
                $status
            ];
        }

        $this->table(
            ['#', 'Name', 'Domain', 'Database', 'ID', 'Status'],
            $tableData
        );
        
        $this->newLine();
        $this->info('Only tenants in MAINTENANCE mode can be selected for disabling');
        $this->newLine();
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
     * Show maintenance status - Interactive version
     */
    protected function showStatus()
    {
        $this->info('📊 Maintenance Status Check');
        $this->line('========================');
        $this->newLine();

        $tenantId = $this->option('tenant');

        // Interactive tenant selection if not specified
        if (!$tenantId) {
            $tenantId = $this->selectTenantForStatus();
            
            if ($tenantId === 'cancel') {
                $this->info('Operation cancelled');
                return 0;
            }
        }

        $this->info("Maintenance status for tenant: {$tenantId}");
        $this->line('');

        $inMaintenance = $this->maintenanceService->isInMaintenanceMode($tenantId);

        if ($inMaintenance) {
            $this->line("   🔧 Status: IN MAINTENANCE");
            $this->line("   ⏰ Since: " . now()->toDateTimeString());
        } else {
            $this->line("   ✅ Status: ACTIVE");
        }

        return 0;
    }

    /**
     * Interactive tenant selection for status check
     */
    protected function selectTenantForStatus()
    {
        $this->info('📋 Select Tenant for Status Check');
        $this->line('');

        $tenants = Tenant::select('id', 'name', 'data')->get();
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found');
            return 'cancel';
        }

        $this->displayTenantsTable($tenants);

        $this->info('Available options:');
        $this->line('  [cancel] ❌ Cancel Operation');
        $this->newLine();

        foreach ($tenants as $index => $tenant) {
            $name = $tenant->name ?? 'Unnamed';
            $domains = $this->getTenantDomains($tenant);
            $domain = $domains[0] ?? 'No domain';
            
            $this->line("  [{$index}] {$name} ({$domain})");
        }

        $this->newLine();
        $choice = $this->ask('Select tenant by number or type "cancel"', 'cancel');

        if ($choice === 'cancel') {
            return 'cancel';
        } elseif (is_numeric($choice) && isset($tenants[$choice])) {
            return $tenants[$choice]->id;
        } else {
            $this->error('Invalid selection.');
            return $this->selectTenantForStatus();
        }
    }

    /**
     * List all tenants in maintenance mode - Enhanced version
     */
    protected function listTenantsInMaintenance()
    {
        $this->info('📋 Tenants in Maintenance Mode');
        $this->line('=============================');
        $this->newLine();

        $tenantsInMaintenance = $this->maintenanceService->getTenantsInMaintenance();

        if (empty($tenantsInMaintenance)) {
            $this->line("   ✅ No tenants are currently in maintenance mode");
            $this->newLine();
            
            // Offer to show all tenants
            if ($this->confirm('Would you like to see all tenants?', false)) {
                $this->showAllTenantsStatus();
            }
            
            return 0;
        }

        // Display maintenance tenants in table format
        $tableData = [];
        foreach ($tenantsInMaintenance as $index => $tenantData) {
            $tenant = Tenant::find($tenantData['id']);
            $name = $tenant ? ($tenant->name ?? 'Unnamed') : 'Unknown';
            $domains = $tenant ? $this->getTenantDomains($tenant) : [];
            $domain = $domains[0] ?? 'No domain';
            
            $tableData[] = [
                $index,
                $name,
                $domain,
                substr($tenantData['id'], 0, 12) . '...',
                'MAINTENANCE'
            ];
        }

        $this->table(
            ['#', 'Name', 'Domain', 'Tenant ID', 'Status'],
            $tableData
        );

        $this->newLine();
        $this->info("Total: " . count($tenantsInMaintenance) . " tenants in maintenance mode");
        $this->newLine();

        // Interactive options
        $this->offerMaintenanceActions($tenantsInMaintenance);

        return 0;
    }

    /**
     * Show all tenants with their status
     */
    protected function showAllTenantsStatus()
    {
        $this->info('📊 All Tenants Status');
        $this->line('==================');
        $this->newLine();

        $tenants = Tenant::select('id', 'name', 'data')->get();
        $this->displayTenantsTable($tenants);
    }

    /**
     * Offer interactive actions for maintenance management
     */
    protected function offerMaintenanceActions($tenantsInMaintenance)
    {
        if (empty($tenantsInMaintenance)) {
            return;
        }

        $this->info('What would you like to do?');
        $this->line('  [0] ✅ Disable maintenance for ALL tenants');
        $this->line('  [1] 🎯 Disable maintenance for specific tenant');
        $this->line('  [2] 🔍 Show detailed maintenance info');
        $this->line('  [3] ❌ Nothing, just exit');
        $this->newLine();

        $choice = $this->ask('Select option by number', '3');

        switch ($choice) {
            case '0':
                if ($this->confirm('⚠️  Disable maintenance mode for ALL tenants?', false)) {
                    $this->disableMaintenanceForAll();
                }
                break;

            case '1':
                $this->selectAndDisableSpecificTenant($tenantsInMaintenance);
                break;

            case '2':
                $this->showDetailedMaintenanceInfo($tenantsInMaintenance);
                break;

            default:
                $this->info('Operation completed');
                break;
        }
    }

    /**
     * Select and disable maintenance for specific tenant
     */
    protected function selectAndDisableSpecificTenant($tenantsInMaintenance)
    {
        $this->info('Select tenant to disable maintenance:');
        $this->line('  [cancel] ❌ Cancel');
        $this->newLine();
        
        foreach ($tenantsInMaintenance as $index => $tenantData) {
            $tenant = Tenant::find($tenantData['id']);
            $name = $tenant ? ($tenant->name ?? 'Unnamed') : 'Unknown';
            $this->line("  [{$index}] {$name} ({$tenantData['id']})");
        }

        $this->newLine();
        $choice = $this->ask('Select tenant by number or type "cancel"', 'cancel');
        
        if ($choice === 'cancel') {
            return;
        } elseif (is_numeric($choice) && isset($tenantsInMaintenance[$choice])) {
            $selected = $tenantsInMaintenance[$choice]['id'];
            if ($this->confirm("Disable maintenance for tenant: {$selected}?", true)) {
                $this->disableMaintenanceForTenant($selected);
            }
        } else {
            $this->error('Invalid selection.');
            $this->selectAndDisableSpecificTenant($tenantsInMaintenance);
        }
    }

    /**
     * Show detailed maintenance information
     */
    protected function showDetailedMaintenanceInfo($tenantsInMaintenance)
    {
        foreach ($tenantsInMaintenance as $index => $tenantData) {
            $this->line('');
            $this->info("🔧 Tenant: {$tenantData['id']}");
            $this->line("   Status: IN MAINTENANCE");
            $this->line("   Since: " . now()->toDateTimeString());
            
            if (!empty($tenantData['maintenance_data'])) {
                $data = $tenantData['maintenance_data'];
                if (isset($data['message'])) {
                    $this->line("   Message: {$data['message']}");
                }
            }
        }
        $this->newLine();
    }

    /**
     * Display maintenance info after enabling
     */
    protected function displayMaintenanceInfo(string $tenantId, array $options)
    {
        $this->line('');
        $this->info('🔧 Maintenance Mode Enabled Successfully!');
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
}
