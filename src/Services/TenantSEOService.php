<?php

namespace ArtflowStudio\Tenancy\Services;

use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Exception;

class TenantSEOService
{
    /**
     * Enable SEO for a tenant
     */
    public function enableSEO(Tenant $tenant, array $config = []): bool
    {
        try {
            // Merge with default config
            $seoConfig = array_merge($this->getDefaultSEOConfig($tenant), $config);
            
            // Create SEO directory structure
            $this->createSEOStructure($tenant);
            
            // Generate robots.txt
            $this->generateRobotsTxt($tenant, $seoConfig);
            
            // Generate sitemap.xml
            $this->generateSitemap($tenant, $seoConfig);
            
            // Update tenant record
            $tenant->update([
                'seo_enabled' => true,
                'seo_config' => $seoConfig
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to enable SEO: {$e->getMessage()}");
        }
    }
    
    /**
     * Disable SEO for a tenant
     */
    public function disableSEO(Tenant $tenant, bool $removeFiles = false): bool
    {
        try {
            // Update tenant record
            $tenant->update([
                'seo_enabled' => false
            ]);
            
            // Optionally remove SEO files
            if ($removeFiles) {
                $this->removeSEOFiles($tenant);
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to disable SEO: {$e->getMessage()}");
        }
    }
    
    /**
     * Get SEO status for a tenant
     */
    public function getSEOStatus(Tenant $tenant): array
    {
        $seoPath = tenant_path('seo', $tenant);
        $domain = $tenant->domains()->first()->domain ?? 'unknown';
        
        return [
            'enabled' => $tenant->seo_enabled ?? false,
            'config' => $tenant->seo_config ?? [],
            'files' => [
                'robots_txt' => file_exists("{$seoPath}/robots.txt"),
                'sitemap_xml' => file_exists("{$seoPath}/sitemap.xml"),
            ],
            'urls' => [
                'robots_txt' => "https://{$domain}/robots.txt",
                'sitemap_xml' => "https://{$domain}/sitemap.xml",
            ],
            'storage_path' => $seoPath,
            'public_url' => tenant_url('seo', $tenant),
            'domain' => $domain
        ];
    }
    
    /**
     * Create SEO directory structure
     */
    protected function createSEOStructure(Tenant $tenant): void
    {
        $seoPath = tenant_path('seo', $tenant);
        
        if (!File::exists($seoPath)) {
            File::makeDirectory($seoPath, 0755, true);
        }
    }
    
    /**
     * Generate robots.txt for tenant
     */
    public function generateRobotsTxt(Tenant $tenant, array $config = []): bool
    {
        try {
            $seoPath = tenant_path('seo', $tenant);
            $domain = $tenant->domains()->first()->domain ?? 'localhost';
            
            $config = $config ?: $tenant->seo_config ?? [];
            
            $allowAll = $config['allow_all'] ?? true;
            $disallowPaths = $config['disallow_paths'] ?? ['/admin', '/api'];
            $sitemapUrl = $config['sitemap_url'] ?? "https://{$domain}/sitemap.xml";
            
            $content = "# Robots.txt for {$tenant->name}\n";
            $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $content .= "User-agent: *\n";
            
            if ($allowAll) {
                $content .= "Allow: /\n";
                
                // Add disallow paths
                foreach ($disallowPaths as $path) {
                    $content .= "Disallow: {$path}\n";
                }
            } else {
                $content .= "Disallow: /\n";
            }
            
            $content .= "\n# Sitemap\n";
            $content .= "Sitemap: {$sitemapUrl}\n";
            
            File::put("{$seoPath}/robots.txt", $content);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to generate robots.txt: {$e->getMessage()}");
        }
    }
    
    /**
     * Generate sitemap.xml for tenant
     */
    public function generateSitemap(Tenant $tenant, array $config = []): bool
    {
        try {
            $seoPath = tenant_path('seo', $tenant);
            $domain = $tenant->domains()->first()->domain ?? 'localhost';
            
            $config = $config ?: $tenant->seo_config ?? [];
            $urls = $config['sitemap_urls'] ?? $this->getDefaultSitemapUrls($tenant);
            
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            foreach ($urls as $urlData) {
                $url = $urlData['url'] ?? $urlData;
                $priority = $urlData['priority'] ?? '0.8';
                $changefreq = $urlData['changefreq'] ?? 'weekly';
                $lastmod = $urlData['lastmod'] ?? date('Y-m-d');
                
                // Ensure URL is absolute
                if (!str_starts_with($url, 'http')) {
                    $url = "https://{$domain}{$url}";
                }
                
                $xml .= "  <url>\n";
                $xml .= "    <loc>{$url}</loc>\n";
                $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
                $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
                $xml .= "    <priority>{$priority}</priority>\n";
                $xml .= "  </url>\n";
            }
            
            $xml .= '</urlset>';
            
            File::put("{$seoPath}/sitemap.xml", $xml);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to generate sitemap: {$e->getMessage()}");
        }
    }
    
    /**
     * Remove SEO files
     */
    protected function removeSEOFiles(Tenant $tenant): void
    {
        $seoPath = tenant_path('seo', $tenant);
        
        if (File::exists($seoPath)) {
            File::deleteDirectory($seoPath);
        }
    }
    
    /**
     * Get default SEO configuration
     */
    protected function getDefaultSEOConfig(Tenant $tenant): array
    {
        $domain = $tenant->domains()->first()->domain ?? 'localhost';
        
        return [
            'allow_all' => true,
            'disallow_paths' => [
                '/admin',
                '/api',
                '/login',
                '/register',
            ],
            'sitemap_url' => "https://{$domain}/sitemap.xml",
            'sitemap_urls' => $this->getDefaultSitemapUrls($tenant),
        ];
    }
    
    /**
     * Get default sitemap URLs
     */
    protected function getDefaultSitemapUrls(Tenant $tenant): array
    {
        return [
            [
                'url' => '/',
                'priority' => '1.0',
                'changefreq' => 'daily',
                'lastmod' => date('Y-m-d')
            ],
            [
                'url' => '/about',
                'priority' => '0.8',
                'changefreq' => 'monthly',
                'lastmod' => date('Y-m-d')
            ],
            [
                'url' => '/contact',
                'priority' => '0.8',
                'changefreq' => 'monthly',
                'lastmod' => date('Y-m-d')
            ],
        ];
    }
    
    /**
     * Add URL to sitemap
     */
    public function addSitemapUrl(Tenant $tenant, string $url, array $options = []): bool
    {
        try {
            $seoConfig = $tenant->seo_config ?? [];
            $sitemapUrls = $seoConfig['sitemap_urls'] ?? [];
            
            $urlData = array_merge([
                'url' => $url,
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => date('Y-m-d')
            ], $options);
            
            // Check if URL already exists
            $exists = false;
            foreach ($sitemapUrls as $index => $existing) {
                $existingUrl = is_array($existing) ? ($existing['url'] ?? $existing) : $existing;
                if ($existingUrl === $url) {
                    $sitemapUrls[$index] = $urlData;
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $sitemapUrls[] = $urlData;
            }
            
            $seoConfig['sitemap_urls'] = $sitemapUrls;
            $tenant->update(['seo_config' => $seoConfig]);
            
            // Regenerate sitemap
            $this->generateSitemap($tenant, $seoConfig);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to add sitemap URL: {$e->getMessage()}");
        }
    }
    
    /**
     * Remove URL from sitemap
     */
    public function removeSitemapUrl(Tenant $tenant, string $url): bool
    {
        try {
            $seoConfig = $tenant->seo_config ?? [];
            $sitemapUrls = $seoConfig['sitemap_urls'] ?? [];
            
            $sitemapUrls = array_filter($sitemapUrls, function($urlData) use ($url) {
                $existingUrl = is_array($urlData) ? ($urlData['url'] ?? $urlData) : $urlData;
                return $existingUrl !== $url;
            });
            
            $seoConfig['sitemap_urls'] = array_values($sitemapUrls);
            $tenant->update(['seo_config' => $seoConfig]);
            
            // Regenerate sitemap
            $this->generateSitemap($tenant, $seoConfig);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to remove sitemap URL: {$e->getMessage()}");
        }
    }
    
    /**
     * Update robots.txt disallow paths
     */
    public function updateDisallowPaths(Tenant $tenant, array $paths): bool
    {
        try {
            $seoConfig = $tenant->seo_config ?? [];
            $seoConfig['disallow_paths'] = $paths;
            
            $tenant->update(['seo_config' => $seoConfig]);
            
            // Regenerate robots.txt
            $this->generateRobotsTxt($tenant, $seoConfig);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to update disallow paths: {$e->getMessage()}");
        }
    }
}
