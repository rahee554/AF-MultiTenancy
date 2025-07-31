<?php

namespace ArtflowStudio\Tenancy\Commands\SEO;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantSEOService;
use Illuminate\Console\Command;
use Exception;

class DisableSEOCommand extends Command
{
    protected $signature = 'tenant:seo:disable 
                            {--tenant= : Tenant ID to disable SEO for}
                            {--all : Disable SEO for all tenants}
                            {--interactive : Interactive mode to select tenant}
                            {--remove-files : Remove SEO files from storage}';

    protected $description = 'Disable SEO for tenant(s)';

    protected TenantSEOService $seoService;

    public function __construct(TenantSEOService $seoService)
    {
        parent::__construct();
        $this->seoService = $seoService;
    }

    public function handle(): int
    {
        $this->info('🔍 Disable SEO for Tenant(s)');
        $this->newLine();

        try {
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
        $this->info('📋 Select tenant(s) to disable SEO for:');
        $this->newLine();

        $tenants = Tenant::where('seo_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with SEO enabled found');
            return 1;
        }

        $choices = [];
        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            $choices[$tenant->id] = "ID: {$tenant->id} | {$tenant->name} | {$domain}";
        }
        $choices['all'] = '🌐 Disable for ALL tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant to disable SEO', $choices);

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
            $this->error("❌ Tenant with ID '{$tenantId}' not found");
            return 1;
        }

        $this->info("🔧 Disabling SEO for: {$tenant->name}");
        $domain = $tenant->domains()->first()->domain ?? 'no-domain';
        $this->line("   Domain: {$domain}");
        $this->newLine();

        $removeFiles = $this->option('remove-files') || 
                       $this->confirm('Remove SEO files from storage?', false);

        try {
            $this->seoService->disableSEO($tenant, $removeFiles);
            
            $this->info('✅ SEO disabled successfully!');
            
            if ($removeFiles) {
                $this->line('   📁 SEO files removed from storage');
            } else {
                $this->line('   📁 SEO files kept in storage (can be re-enabled)');
            }
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Failed to disable SEO: {$e->getMessage()}");
            return 1;
        }
    }

    private function disableForAllTenants(): int
    {
        $tenants = Tenant::where('seo_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with SEO enabled found');
            return 1;
        }

        $this->info("🌐 Disabling SEO for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $removeFiles = $this->option('remove-files') || 
                       $this->confirm('Remove SEO files from storage for all?', false);

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            try {
                $this->seoService->disableSEO($tenant, $removeFiles);
                
                $domain = $tenant->domains()->first()->domain ?? 'no-domain';
                $this->line("   ✅ {$tenant->name} ({$domain})");
                $successCount++;
            } catch (Exception $e) {
                $this->error("   ❌ {$tenant->name}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("✅ Success: {$successCount}, ❌ Failed: {$failCount}");

        return $failCount > 0 ? 1 : 0;
    }
}
