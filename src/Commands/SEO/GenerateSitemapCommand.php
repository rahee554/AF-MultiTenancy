<?php

namespace ArtflowStudio\Tenancy\Commands\SEO;

use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantSEOService;
use Illuminate\Console\Command;
use Exception;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'tenant:seo:generate-sitemap 
                            {--tenant= : Tenant ID to generate sitemap for}
                            {--all : Generate sitemap for all SEO-enabled tenants}
                            {--add-url= : Add URL to sitemap (format: /path)}
                            {--priority= : Priority for the URL (0.0-1.0)}
                            {--changefreq= : Change frequency (always|hourly|daily|weekly|monthly|yearly|never)}';

    protected $description = 'Generate or update sitemap.xml for tenant(s)';

    protected TenantSEOService $seoService;

    public function __construct(TenantSEOService $seoService)
    {
        parent::__construct();
        $this->seoService = $seoService;
    }

    public function handle(): int
    {
        $this->info('🗺️  Sitemap Generator');
        $this->newLine();

        try {
            if ($this->option('all')) {
                return $this->generateForAllTenants();
            } elseif ($this->option('tenant')) {
                return $this->generateForTenant($this->option('tenant'));
            } else {
                $this->error('❌ Please specify --tenant=ID or --all');
                return 1;
            }
        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function generateForTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("❌ Tenant with ID '{$tenantId}' not found");
            return 1;
        }

        if (!$tenant->seo_enabled) {
            $this->error('❌ SEO is not enabled for this tenant');
            $this->line('   Run: php artisan tenant:seo:enable --tenant=' . $tenant->id);
            return 1;
        }

        $this->info("🗺️  Generating sitemap for: {$tenant->name}");
        $domain = $tenant->domains()->first()->domain ?? 'no-domain';
        $this->line("   Domain: {$domain}");
        $this->newLine();

        // Add URL if specified
        if ($url = $this->option('add-url')) {
            $options = [];
            
            if ($priority = $this->option('priority')) {
                $options['priority'] = $priority;
            }
            
            if ($changefreq = $this->option('changefreq')) {
                $options['changefreq'] = $changefreq;
            }
            
            $this->seoService->addSitemapUrl($tenant, $url, $options);
            $this->info("   ✅ Added URL: {$url}");
        } else {
            // Just regenerate sitemap
            $config = $tenant->seo_config ?? [];
            $this->seoService->generateSitemap($tenant, $config);
        }

        $status = $this->seoService->getSEOStatus($tenant);
        
        $this->info('✅ Sitemap generated successfully!');
        $this->newLine();
        
        $this->line("   URL: {$status['urls']['sitemap_xml']}");
        $this->line("   Path: {$status['storage_path']}/sitemap.xml");

        // Show sitemap URLs count
        $config = $tenant->seo_config ?? [];
        if (isset($config['sitemap_urls'])) {
            $count = count($config['sitemap_urls']);
            $this->line("   Pages: {$count}");
        }

        return 0;
    }

    private function generateForAllTenants(): int
    {
        $tenants = Tenant::where('seo_enabled', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants with SEO enabled found');
            return 1;
        }

        $this->info("🗺️  Generating sitemaps for {$tenants->count()} tenant(s)...");
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            try {
                $config = $tenant->seo_config ?? [];
                $this->seoService->generateSitemap($tenant, $config);
                
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
