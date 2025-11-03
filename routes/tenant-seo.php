<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Tenant SEO Routes
|--------------------------------------------------------------------------
|
| These routes serve tenant-specific SEO files like robots.txt and sitemap.xml
| They should be included in tenant routes (routes with tenant middleware)
|
*/

// Robots.txt route
Route::get('/robots.txt', function () {
    $tenant = tenancy()->tenant;
    
    if (!$tenant || !$tenant->seo_enabled) {
        // Return default robots.txt
        return response("User-agent: *\nDisallow: /", 200)
            ->header('Content-Type', 'text/plain');
    }
    
    $robotsPath = tenant_path('seo', $tenant) . '/robots.txt';
    
    if (!File::exists($robotsPath)) {
        // Generate on-the-fly if missing
        app(\ArtflowStudio\Tenancy\Services\TenantSEOService::class)
            ->generateRobotsTxt($tenant);
    }
    
    $content = File::get($robotsPath);
    
    return response($content, 200)
        ->header('Content-Type', 'text/plain');
})->name('tenant.robots');

// Sitemap.xml route
Route::get('/sitemap.xml', function () {
    $tenant = tenancy()->tenant;
    
    if (!$tenant || !$tenant->seo_enabled) {
        // Return minimal sitemap
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . url('/') . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        $xml .= '</urlset>';
        
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
    
    $sitemapPath = tenant_path('seo', $tenant) . '/sitemap.xml';
    
    if (!File::exists($sitemapPath)) {
        // Generate on-the-fly if missing
        app(\ArtflowStudio\Tenancy\Services\TenantSEOService::class)
            ->generateSitemap($tenant);
    }
    
    $content = File::get($sitemapPath);
    
    return response($content, 200)
        ->header('Content-Type', 'application/xml');
})->name('tenant.sitemap');
