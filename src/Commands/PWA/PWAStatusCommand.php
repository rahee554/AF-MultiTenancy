<?php

namespace ArtflowStudio\Tenancy\Commands\PWA;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantPWAService;
use Illuminate\Console\Command;
use Exception;

class PWAStatusCommand extends Command
{
    protected $signature = 'tenant:pwa:status 
                            {--tenant= : Tenant ID to check status for}
                            {--all : Show status for all tenants}
                            {--interactive : Interactive mode to select tenant}';

    protected $description = 'Check PWA status for tenant(s)';

    protected TenantPWAService $pwaService;

    public function __construct(TenantPWAService $pwaService)
    {
        parent::__construct();
        $this->pwaService = $pwaService;
    }

    public function handle(): int
    {
        $this->info('📊 PWA Status Check');
        $this->newLine();

        try {
            // Determine which tenant(s) to check
            if ($this->option('all')) {
                return $this->statusForAllTenants();
            } elseif ($this->option('tenant')) {
                return $this->statusForTenant($this->option('tenant'));
            } elseif ($this->option('interactive')) {
                return $this->interactiveMode();
            } else {
                $this->error('❌ Please specify --tenant=ID, --all, or --interactive');
                return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function interactiveMode(): int
    {
        $this->info('📋 Select tenant to check PWA status:');
        $this->newLine();

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found');
            return 1;
        }

        // Build choices array
        $choices = [];
        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            $status = $tenant->pwa_enabled ? '✅ PWA Enabled' : '❌ PWA Disabled';
            $choices[$tenant->id] = "ID: {$tenant->id} | {$tenant->name} | {$domain} | {$status}";
        }
        $choices['all'] = '🌐 Show status for ALL tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant', $choices);

        if ($selected === 'cancel') {
            $this->info('Operation cancelled');
            return 0;
        }

        if ($selected === 'all') {
            return $this->statusForAllTenants();
        }

        return $this->statusForTenant($selected);
    }

    private function statusForTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant not found: {$tenantId}");
            return 1;
        }

        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        
        $this->info("📊 PWA Status for: {$tenant->name} ({$domain})");
        $this->newLine();

        $status = $this->pwaService->getPWAStatus($tenant);

        // Basic Status
        $this->table(
            ['Property', 'Value'],
            [
                ['Tenant ID', $tenant->id],
                ['Tenant Name', $tenant->name],
                ['Domain', $status['domain']],
                ['PWA Enabled', $status['enabled'] ? '✅ Yes' : '❌ No'],
                ['Storage Path', $status['storage_path']],
                ['Public URL', $status['public_path']]
            ]
        );

        // File Status
        $this->newLine();
        $this->info('📁 PWA Files:');
        $this->table(
            ['File', 'Status'],
            [
                ['manifest.json', $status['files']['manifest'] ? '✅ Exists' : '❌ Missing'],
                ['sw.js (Service Worker)', $status['files']['service_worker'] ? '✅ Exists' : '❌ Missing'],
                ['offline.html', $status['files']['offline_page'] ? '✅ Exists' : '❌ Missing']
            ]
        );

        // Configuration
        if (!empty($status['config'])) {
            $this->newLine();
            $this->info('⚙️  PWA Configuration:');
            
            $configRows = [];
            foreach ($status['config'] as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $configRows[] = [$key, $value];
            }
            
            $this->table(['Setting', 'Value'], $configRows);
        }

        // Overall Health
        $this->newLine();
        $allFilesExist = $status['files']['manifest'] && 
                         $status['files']['service_worker'] && 
                         $status['files']['offline_page'];
        
        if ($status['enabled'] && $allFilesExist) {
            $this->info('✅ PWA is fully configured and operational');
        } elseif ($status['enabled'] && !$allFilesExist) {
            $this->warn('⚠️  PWA is enabled but some files are missing. Run: php artisan tenant:pwa:enable --tenant=' . $tenant->id);
        } else {
            $this->comment('💡 PWA is disabled. Enable with: php artisan tenant:pwa:enable --tenant=' . $tenant->id);
        }

        return 0;
    }

    private function statusForAllTenants(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found');
            return 1;
        }

        $this->info("📊 PWA Status for All Tenants ({$tenants->count()} total)");
        $this->newLine();

        $rows = [];
        $enabledCount = 0;
        $disabledCount = 0;
        $healthyCount = 0;
        $unhealthyCount = 0;

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            $status = $this->pwaService->getPWAStatus($tenant);
            
            $pwaStatus = $status['enabled'] ? '✅ Enabled' : '❌ Disabled';
            
            $allFilesExist = $status['files']['manifest'] && 
                             $status['files']['service_worker'] && 
                             $status['files']['offline_page'];
            
            $health = '❌ N/A';
            if ($status['enabled']) {
                if ($allFilesExist) {
                    $health = '✅ Healthy';
                    $healthyCount++;
                } else {
                    $health = '⚠️  Missing Files';
                    $unhealthyCount++;
                }
                $enabledCount++;
            } else {
                $disabledCount++;
            }
            
            $cacheStrategy = $status['config']['cache_strategy'] ?? 'N/A';
            
            $rows[] = [
                $tenant->id,
                $tenant->name,
                $domain,
                $pwaStatus,
                $health,
                $cacheStrategy
            ];
        }

        $this->table(
            ['ID', 'Name', 'Domain', 'PWA Status', 'Health', 'Cache Strategy'],
            $rows
        );

        // Summary
        $this->newLine();
        $this->info('📈 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tenants', $tenants->count()],
                ['✅ PWA Enabled', $enabledCount],
                ['❌ PWA Disabled', $disabledCount],
                ['✅ Healthy', $healthyCount],
                ['⚠️  Unhealthy', $unhealthyCount]
            ]
        );

        return 0;
    }
}
