<?php

namespace ArtflowStudio\Tenancy\Commands\SEO;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantSEOService;
use Illuminate\Console\Command;

class SEOStatusCommand extends Command
{
    protected $signature = 'tenant:seo:status 
                            {--tenant= : Tenant ID to check SEO status}
                            {--all : Show SEO status for all tenants}';

    protected $description = 'Check SEO status for tenant(s)';

    protected TenantSEOService $seoService;

    public function __construct(TenantSEOService $seoService)
    {
        parent::__construct();
        $this->seoService = $seoService;
    }

    public function handle(): int
    {
        $this->info('🔍 SEO Status Check');
        $this->newLine();

        if ($this->option('all')) {
            return $this->showAllTenants();
        } elseif ($this->option('tenant')) {
            return $this->showTenant($this->option('tenant'));
        } else {
            $this->error('❌ Please specify --tenant=ID or --all');
            return 1;
        }
    }

    private function showTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant with ID '{$tenantId}' not found");
            return 1;
        }

        $status = $this->seoService->getSEOStatus($tenant);

        $this->info("📊 SEO Status for: {$tenant->name}");
        $this->newLine();

        $this->table(
            ['SEO Information', 'Value'],
            [
                ['Tenant ID', $tenant->id],
                ['Tenant Name', $tenant->name],
                ['Domain', $status['domain']],
                ['SEO Enabled', $status['enabled'] ? '✅ Yes' : '❌ No'],
                ['Robots.txt', $status['files']['robots_txt'] ? '✅ Exists' : '❌ Missing'],
                ['Sitemap.xml', $status['files']['sitemap_xml'] ? '✅ Exists' : '❌ Missing'],
            ]
        );

        $this->newLine();
        $this->info('🌐 Public URLs:');
        $this->line("   Robots: {$status['urls']['robots_txt']}");
        $this->line("   Sitemap: {$status['urls']['sitemap_xml']}");

        $this->newLine();
        $this->info('📁 Storage:');
        $this->line("   Path: {$status['storage_path']}");
        $this->line("   URL: {$status['public_url']}");

        if ($status['enabled'] && !empty($status['config'])) {
            $this->newLine();
            $this->info('⚙️  Configuration:');
            
            $config = $status['config'];
            
            if (isset($config['allow_all'])) {
                $allowAll = $config['allow_all'] ? 'Yes (Allow all)' : 'No (Disallow all)';
                $this->line("   Allow All: {$allowAll}");
            }
            
            if (isset($config['disallow_paths']) && !empty($config['disallow_paths'])) {
                $this->line("   Disallowed Paths:");
                foreach ($config['disallow_paths'] as $path) {
                    $this->line("     - {$path}");
                }
            }
            
            if (isset($config['sitemap_urls']) && !empty($config['sitemap_urls'])) {
                $count = count($config['sitemap_urls']);
                $this->line("   Sitemap URLs: {$count} pages");
            }
        }

        return 0;
    }

    private function showAllTenants(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found');
            return 1;
        }

        $this->info("📊 SEO Status for All Tenants");
        $this->newLine();

        $tableData = [];
        
        foreach ($tenants as $tenant) {
            $status = $this->seoService->getSEOStatus($tenant);
            $domain = $tenant->domains()->first()->domain ?? 'no-domain';
            
            $tableData[] = [
                $tenant->id,
                $tenant->name,
                $domain,
                $status['enabled'] ? '✅' : '❌',
                $status['files']['robots_txt'] ? '✅' : '❌',
                $status['files']['sitemap_xml'] ? '✅' : '❌',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Domain', 'SEO', 'Robots', 'Sitemap'],
            $tableData
        );

        $enabledCount = $tenants->where('seo_enabled', true)->count();
        $disabledCount = $tenants->count() - $enabledCount;

        $this->newLine();
        $this->info("📈 Summary:");
        $this->line("   Total Tenants: {$tenants->count()}");
        $this->line("   SEO Enabled: {$enabledCount}");
        $this->line("   SEO Disabled: {$disabledCount}");

        return 0;
    }
}
