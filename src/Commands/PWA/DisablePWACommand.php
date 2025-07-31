<?php

namespace ArtflowStudio\Tenancy\Commands\PWA;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantPWAService;
use Illuminate\Console\Command;
use Exception;

class DisablePWACommand extends Command
{
    protected $signature = 'tenant:pwa:disable 
                            {--tenant= : Tenant ID to disable PWA for}
                            {--all : Disable PWA for all tenants}
                            {--interactive : Interactive mode to select tenant}
                            {--remove-files : Remove PWA files from disk}';

    protected $description = 'Disable PWA (Progressive Web App) for tenant(s)';

    protected TenantPWAService $pwaService;

    public function __construct(TenantPWAService $pwaService)
    {
        parent::__construct();
        $this->pwaService = $pwaService;
    }

    public function handle(): int
    {
        $this->info('🛑 Disable PWA for Tenant(s)');
        $this->newLine();

        try {
            // Determine which tenant(s) to disable PWA for
            if ($this->option('all')) {
                return $this->disableForAllTenants();
            } elseif ($this->option('tenant')) {
                return $this->disableForTenant($this->option('tenant'));
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
        $this->info('📋 Select tenant(s) to disable PWA for:');
        $this->newLine();

        // Only show tenants with PWA enabled
        $tenants = Tenant::where('pwa_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with PWA enabled found');
            return 1;
        }

        // Build choices array
        $choices = [];
        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            $choices[$tenant->id] = "ID: {$tenant->id} | {$tenant->name} | {$domain}";
        }
        $choices['all'] = '🌐 Disable for ALL enabled tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant to disable PWA', $choices);

        if ($selected === 'cancel') {
            $this->info('Operation cancelled');
            return 0;
        }

        if ($selected === 'all') {
            return $this->disableForAllTenants();
        }

        return $this->disableForTenant($selected);
    }

    private function disableForTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant not found: {$tenantId}");
            return 1;
        }

        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        
        $this->info("🔧 Disabling PWA for: {$tenant->name} ({$domain})");

        // Check if PWA is enabled
        if (!$tenant->pwa_enabled) {
            $this->warn('⚠️  PWA is not enabled for this tenant');
            return 1;
        }

        // Ask about removing files
        $removeFiles = $this->option('remove-files');
        if (!$removeFiles && $this->option('interactive')) {
            $removeFiles = $this->confirm('Remove PWA files from disk?', false);
        }

        try {
            $this->pwaService->disablePWA($tenant, $removeFiles);
            
            $this->newLine();
            $this->info('✅ PWA disabled successfully!');
            
            if ($removeFiles) {
                $this->info('🗑️  PWA files removed from disk');
            } else {
                $this->comment('💡 PWA files remain on disk (use --remove-files to delete)');
            }
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Failed to disable PWA: {$e->getMessage()}");
            return 1;
        }
    }

    private function disableForAllTenants(): int
    {
        $tenants = Tenant::where('pwa_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with PWA enabled found');
            return 1;
        }

        $this->info("🌐 Disabling PWA for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $removeFiles = $this->option('remove-files');
        if (!$removeFiles) {
            $removeFiles = $this->confirm('Remove PWA files from disk for all tenants?', false);
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'unknown';
            
            try {
                $this->line("🔧 Disabling PWA for {$tenant->name} ({$domain})...");
                $this->pwaService->disablePWA($tenant, $removeFiles);
                $successCount++;
            } catch (Exception $e) {
                $this->warn("   ⚠️  Failed: {$e->getMessage()}");
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Disabled', $successCount],
                ['❌ Failed', $failedCount]
            ]
        );

        return $failedCount > 0 ? 1 : 0;
    }
}
