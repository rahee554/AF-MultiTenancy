<?php

namespace ArtflowStudio\Tenancy\Commands\SEO;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantSEOService;
use Illuminate\Console\Command;
use Exception;

class EnableSEOCommand extends Command
{
    protected $signature = 'tenant:seo:enable 
                            {--tenant= : Tenant ID to enable SEO for}
                            {--all : Enable SEO for all tenants}
                            {--interactive : Interactive mode to select tenant}
                            {--allow-all : Allow all search engines (default: true)}
                            {--disallow= : Comma-separated paths to disallow}';

    protected $description = 'Enable SEO (robots.txt, sitemap.xml) for tenant(s)';

    protected TenantSEOService $seoService;

    public function __construct(TenantSEOService $seoService)
    {
        parent::__construct();
        $this->seoService = $seoService;
    }

    public function handle(): int
    {
        $this->info('🔍 Enable SEO for Tenant(s)');
        $this->newLine();

        try {
            // Determine which tenant(s) to enable SEO for
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
        $this->info('📋 Select tenant(s) to enable SEO for:');
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
            $status = $tenant->seo_enabled ? '✅ SEO Enabled' : '❌ SEO Disabled';
            $choices[$tenant->id] = "ID: {$tenant->id} | {$tenant->name} | {$domain} | {$status}";
        }
        $choices['all'] = '🌐 Enable for ALL tenants';
        $choices['cancel'] = '❌ Cancel';

        $this->newLine();
        $selected = $this->choice('Select tenant to enable SEO', $choices);

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
            $this->error("❌ Tenant with ID '{$tenantId}' not found");
            return 1;
        }

        $this->info("🔧 Enabling SEO for: {$tenant->name}");
        $domain = $tenant->domains()->first()->domain ?? 'no-domain';
        $this->line("   Domain: {$domain}");
        $this->newLine();

        // Build configuration from options
        $config = $this->buildConfig($tenant);

        try {
            $this->seoService->enableSEO($tenant, $config);
            
            $this->info('✅ SEO enabled successfully!');
            $this->newLine();
            
            $this->displaySEOInfo($tenant);
            
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Failed to enable SEO: {$e->getMessage()}");
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

        $this->info("🌐 Enabling SEO for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            try {
                $config = $this->buildConfig($tenant);
                $this->seoService->enableSEO($tenant, $config);
                
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

    private function buildConfig(Tenant $tenant): array
    {
        $domain = $tenant->domains()->first()->domain ?? 'localhost';
        
        $config = [
            'allow_all' => true,  // Always allow by default
            'sitemap_url' => "https://{$domain}/sitemap.xml",
        ];

        // Add disallow paths if provided
        if ($disallowPaths = $this->option('disallow')) {
            $config['disallow_paths'] = array_map('trim', explode(',', $disallowPaths));
        } else {
            $config['disallow_paths'] = ['/admin', '/api', '/login', '/register'];
        }

        return $config;
    }

    private function displaySEOInfo(Tenant $tenant): void
    {
        $status = $this->seoService->getSEOStatus($tenant);

        $this->table(
            ['SEO Information', 'Value'],
            [
                ['Domain', $status['domain']],
                ['Enabled', $status['enabled'] ? '✅ Yes' : '❌ No'],
                ['Robots.txt', $status['files']['robots_txt'] ? '✅ Generated' : '❌ Missing'],
                ['Sitemap.xml', $status['files']['sitemap_xml'] ? '✅ Generated' : '❌ Missing'],
                ['Robots URL', $status['urls']['robots_txt']],
                ['Sitemap URL', $status['urls']['sitemap_xml']],
                ['Storage Path', $status['storage_path']],
            ]
        );
    }
}
