<?php

namespace ArtflowStudio\Tenancy\Commands\PWA;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantPWAService;
use Illuminate\Console\Command;
use Exception;

class EnablePWACommand extends Command
{
    protected $signature = 'tenant:pwa:enable 
                            {--tenant= : Tenant ID to enable PWA for}
                            {--all : Enable PWA for all tenants}
                            {--interactive : Interactive mode to select tenant}
                            {--cache-strategy=network-first : Cache strategy (network-first|cache-first|stale-while-revalidate)}
                            {--theme-color=#667eea : Theme color for PWA}
                            {--background-color=#ffffff : Background color for PWA}';

    protected $description = 'Enable PWA (Progressive Web App) for tenant(s)';

    protected TenantPWAService $pwaService;

    public function __construct(TenantPWAService $pwaService)
    {
        parent::__construct();
        $this->pwaService = $pwaService;
    }

    public function handle(): int
    {
        $this->info('🚀 Enable PWA for Tenant(s)');
        $this->newLine();

        try {
            // Determine which tenant(s) to enable PWA for
            if ($this->option('all')) {
                return $this->enableForAllTenants();
            } elseif ($this->option('tenant')) {
                return $this->enableForTenant($this->option('tenant'));
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
        $this->info('📋 Select tenant(s) to enable PWA for:');
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
        $choices['all'] = '🌐 Enable for ALL tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant to enable PWA', $choices);

        if ($selected === 'cancel') {
            $this->info('Operation cancelled');
            return 0;
        }

        if ($selected === 'all') {
            return $this->enableForAllTenants();
        }

        return $this->enableForTenant($selected);
    }

    private function enableForTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant not found: {$tenantId}");
            return 1;
        }

        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        
        $this->info("🔧 Enabling PWA for: {$tenant->name} ({$domain})");

        // Check if already enabled
        if ($tenant->pwa_enabled) {
            if (!$this->confirm('⚠️  PWA is already enabled. Regenerate?', false)) {
                $this->info('Operation cancelled');
                return 0;
            }
        }

        // Get custom config from options
        $config = [];
        
        if ($this->option('cache-strategy')) {
            $config['cache_strategy'] = $this->option('cache-strategy');
        }
        
        if ($this->option('theme-color')) {
            $config['theme_color'] = $this->option('theme-color');
        }
        
        if ($this->option('background-color')) {
            $config['background_color'] = $this->option('background-color');
        }

        // Ask for additional configuration in interactive mode
        if ($this->option('interactive')) {
            $config = $this->collectPWAConfig($tenant, $config);
        }

        try {
            $this->pwaService->enablePWA($tenant, $config);
            
            $this->newLine();
            $this->info('✅ PWA enabled successfully!');
            $this->newLine();
            
            $this->displayPWAInfo($tenant);
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Failed to enable PWA: {$e->getMessage()}");
            return 1;
        }
    }

    private function enableForAllTenants(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found');
            return 1;
        }

        $this->info("🌐 Enabling PWA for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->first()->domain ?? 'unknown';
            
            if ($tenant->pwa_enabled) {
                $this->line("⏭️  Skipping {$tenant->name} ({$domain}) - already enabled");
                $skippedCount++;
                continue;
            }

            try {
                $this->line("🔧 Enabling PWA for {$tenant->name} ({$domain})...");
                $this->pwaService->enablePWA($tenant);
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
                ['✅ Enabled', $successCount],
                ['⏭️  Skipped', $skippedCount],
                ['❌ Failed', $failedCount]
            ]
        );

        return $failedCount > 0 ? 1 : 0;
    }

    private function collectPWAConfig(Tenant $tenant, array $baseConfig = []): array
    {
        $this->info('⚙️  PWA Configuration:');
        $this->newLine();

        $config = $baseConfig;

        // App Name
        $config['name'] = $this->ask('App Name', $config['name'] ?? $tenant->name);
        
        // Short Name
        $config['short_name'] = $this->ask('Short Name (max 12 chars)', $config['short_name'] ?? substr($tenant->name, 0, 12));
        
        // Description
        $config['description'] = $this->ask('Description', $config['description'] ?? "Progressive Web App for {$tenant->name}");
        
        // Cache Strategy
        $config['cache_strategy'] = $this->choice(
            'Cache Strategy',
            ['network-first', 'cache-first', 'stale-while-revalidate'],
            $config['cache_strategy'] ?? 'network-first'
        );
        
        // Theme Color
        $config['theme_color'] = $this->ask('Theme Color (hex)', $config['theme_color'] ?? '#667eea');
        
        // Background Color
        $config['background_color'] = $this->ask('Background Color (hex)', $config['background_color'] ?? '#ffffff');
        
        // Display Mode
        $config['display'] = $this->choice(
            'Display Mode',
            ['standalone', 'fullscreen', 'minimal-ui', 'browser'],
            $config['display'] ?? 'standalone'
        );

        return $config;
    }

    private function displayPWAInfo(Tenant $tenant): void
    {
        $status = $this->pwaService->getPWAStatus($tenant);

        $this->info('📋 PWA Information:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Tenant', $tenant->name],
                ['Domain', $status['domain']],
                ['Status', $status['enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['Manifest', $status['files']['manifest'] ? '✅ Generated' : '❌ Missing'],
                ['Service Worker', $status['files']['service_worker'] ? '✅ Generated' : '❌ Missing'],
                ['Offline Page', $status['files']['offline_page'] ? '✅ Generated' : '❌ Missing'],
                ['Storage Path', $status['storage_path']],
                ['Public URL', $status['public_path']]
            ]
        );

        $this->newLine();
        $this->comment('💡 Next Steps:');
        $this->line('   1. Include @include(\'af-tenancy::components.pwa\') in your layout <head>');
        $this->line('   2. Test PWA: php artisan tenant:pwa:test --tenant=' . $tenant->id);
        $this->line('   3. Check status: php artisan tenant:pwa:status --tenant=' . $tenant->id);
    }
}
