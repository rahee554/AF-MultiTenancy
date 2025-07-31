# Tenant Asset Management & SEO System

Complete tenant-isolated asset management with SEO support (robots.txt, sitemap.xml) for multi-tenant Laravel applications.

## 📁 Folder Structure

Each tenant has its own isolated folder structure using **exact domain names**:

```
storage/app/public/tenants/{domain}/
├── assets/          # General assets (images, fonts, CSS, JS, etc.)
├── pwa/            # PWA files (manifest.json, service-worker.js, icons)
├── seo/            # SEO files (robots.txt, sitemap.xml)
├── documents/      # Documents and downloads
└── media/          # Media files (videos, audio)
```

Example for `tenant1.local`:
```
storage/app/public/tenants/tenant1.local/
├── assets/
│   ├── images/
│   ├── fonts/
│   └── css/
├── pwa/
│   ├── manifest.json
│   ├── sw.js
│   └── icons/
├── seo/
│   ├── robots.txt
│   └── sitemap.xml
├── documents/
└── media/
```

**Important:** Folder names use the **exact domain** (e.g., `tenant1.local`, not `tenant1_local`). Dots are preserved in folder names.

## 🔧 Helper Functions

### `tenant_asset($path, $tenant = null)`
Generate URL for tenant-specific asset.

```php
// In Blade templates
<img src="{{ tenant_asset('images/logo.png') }}" alt="Logo">
<link rel="stylesheet" href="{{ tenant_asset('css/custom.css') }}">

// In PHP code
$logoUrl = tenant_asset('images/logo.png');
```

### `tenant_pwa_asset($path, $tenant = null)`
Generate URL for tenant-specific PWA asset.

```php
<link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">
```

### `tenant_seo_asset($path, $tenant = null)`
Generate URL for tenant-specific SEO asset.

```php
// Access sitemap directly
$sitemapUrl = tenant_seo_asset('sitemap.xml');
```

### `tenant_path($subfolder = '', $tenant = null)`
Get absolute storage path for tenant's folder.

```php
// Get tenant's assets folder path
$assetsPath = tenant_path('assets');
// Returns: /path/to/storage/app/public/tenants/tenant1_local/assets

// Get tenant's root folder
$rootPath = tenant_path();
// Returns: /path/to/storage/app/public/tenants/tenant1_local
```

### `tenant_url($subfolder = '', $tenant = null)`
Get public URL path for tenant's folder.

```php
$assetsUrl = tenant_url('assets');
// Returns: /storage/tenants/tenant1_local/assets
```

### `current_tenant()`
Get current tenant instance.

```php
$tenant = current_tenant();
if ($tenant) {
    echo $tenant->name;
}
```

### `tenant_config($key, $default = null, $tenant = null)`
Get tenant-specific configuration value.

```php
$brandColor = tenant_config('brand_color', '#667eea');
```

## 🔍 SEO Management

### Enable SEO for Tenant

```bash
# Enable for specific tenant
php artisan tenant:seo:enable --tenant=1

# Enable for specific tenant with custom settings
php artisan tenant:seo:enable --tenant=1 --disallow=/private,/admin

# Enable for all tenants
php artisan tenant:seo:enable --all

# Interactive mode
php artisan tenant:seo:enable --interactive
```

### Disable SEO for Tenant

```bash
# Disable but keep files
php artisan tenant:seo:disable --tenant=1

# Disable and remove files
php artisan tenant:seo:disable --tenant=1 --remove-files

# Disable for all tenants
php artisan tenant:seo:disable --all
```

### Check SEO Status

```bash
# Check specific tenant
php artisan tenant:seo:status --tenant=1

# Check all tenants
php artisan tenant:seo:status --all
```

### Generate/Update Sitemap

```bash
# Regenerate sitemap
php artisan tenant:seo:generate-sitemap --tenant=1

# Add URL to sitemap
php artisan tenant:seo:generate-sitemap --tenant=1 --add-url=/products --priority=0.9 --changefreq=daily

# Regenerate for all tenants
php artisan tenant:seo:generate-sitemap --all
```

## 📝 Robots.txt

### Default robots.txt Content

```
# Robots.txt for Tenant Name
# Generated: 2025-10-23 10:00:00

User-agent: *
Allow: /
Disallow: /admin
Disallow: /api
Disallow: /login
Disallow: /register

# Sitemap
Sitemap: https://tenant1.local/sitemap.xml
```

### Customize Disallow Paths

```php
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$seoService = app(TenantSEOService::class);
$seoService->updateDisallowPaths($tenant, [
    '/admin',
    '/api',
    '/private',
    '/internal'
]);
```

## 🗺️ Sitemap.xml

### Default Sitemap URLs

The system generates a basic sitemap with these default URLs:
- `/` (Homepage, priority: 1.0, daily)
- `/about` (About page, priority: 0.8, monthly)
- `/contact` (Contact page, priority: 0.8, monthly)

### Add URLs to Sitemap

```php
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$seoService = app(TenantSEOService::class);

// Add single URL
$seoService->addSitemapUrl($tenant, '/products', [
    'priority' => '0.9',
    'changefreq' => 'daily',
    'lastmod' => date('Y-m-d')
]);

// Add multiple URLs
$urls = [
    ['url' => '/products', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['url' => '/services', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/blog', 'priority' => '0.7', 'changefreq' => 'daily'],
];

foreach ($urls as $urlData) {
    $seoService->addSitemapUrl($tenant, $urlData['url'], $urlData);
}
```

### Remove URLs from Sitemap

```php
$seoService->removeSitemapUrl($tenant, '/old-page');
```

## 🌐 Public URLs

SEO files are automatically accessible via standard URLs:

```
https://tenant1.local/robots.txt
https://tenant1.local/sitemap.xml
```

These routes are automatically handled and serve the tenant-specific files.

## 🔄 Integration with Tenant Creation

### Automatically Enable SEO

When creating a tenant, you can enable SEO:

```php
use ArtflowStudio\Tenancy\Services\TenantService;
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$tenantService = app(TenantService::class);
$seoService = app(TenantSEOService::class);

// Create tenant
$tenant = $tenantService->createTenant($data);

// Enable SEO
$seoService->enableSEO($tenant, [
    'allow_all' => true,
    'disallow_paths' => ['/admin', '/api'],
    'sitemap_urls' => [
        ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => '/products', 'priority' => '0.9', 'changefreq' => 'daily'],
    ]
]);
```

## 📦 Asset Upload

### Upload Assets Programmatically

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$assetService = app(TenantAssetService::class);

// Upload file to assets folder
$url = $assetService->uploadAsset($tenant, $request->file('logo'), 'images');
// Returns: URL to the uploaded file

// Copy existing file
$assetService->copyAsset($tenant, '/path/to/source.png', 'images/logo.png');
```

### List Tenant Assets

```php
$files = $assetService->listFiles($tenant, 'assets');

foreach ($files as $file) {
    echo $file['url'];      // Public URL
    echo $file['path'];     // Relative path
    echo $file['size_human']; // Human-readable size
}
```

### Get Folder Size

```php
$sizeInfo = $assetService->getTenantFolderSize($tenant);

echo $sizeInfo['total_human']; // e.g., "15.3 MB"
echo $sizeInfo['breakdown']['assets']['human']; // e.g., "10.5 MB"
```

## 🏗️ Folder Structure Creation

Tenant folder structure is automatically created when:
1. Tenant is created
2. PWA is enabled
3. SEO is enabled
4. Assets are uploaded

You can manually create it:

```php
$assetService->createTenantStructure($tenant);
```

## 🔒 Security

- All tenant assets are isolated in separate folders
- Folder names are sanitized (dots replaced with underscores)
- `.gitignore` is automatically created in tenant folders
- Public access is controlled via Laravel's storage symlink

## 📊 Monitoring

### Check Tenant Folder Usage

```bash
# Via asset service
$sizeInfo = $assetService->getTenantFolderSize($tenant);
```

### Check SEO Status

```bash
php artisan tenant:seo:status --all
```

## 🎨 Blade Examples

### Using Assets in Views

```blade
<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }}</title>
    
    {{-- Tenant-specific CSS --}}
    <link rel="stylesheet" href="{{ tenant_asset('css/theme.css') }}">
    
    {{-- PWA manifest --}}
    <link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">
    
    {{-- Favicon --}}
    <link rel="icon" href="{{ tenant_asset('images/favicon.ico') }}">
</head>
<body>
    {{-- Logo --}}
    <img src="{{ tenant_asset('images/logo.png') }}" alt="Logo">
    
    {{-- Tenant-specific JavaScript --}}
    <script src="{{ tenant_asset('js/custom.js') }}"></script>
</body>
</html>
```

## 🚀 Migration Guide

### From Old PWA Structure

If you have existing PWA files in `storage/app/public/pwa/{domain}/`, they will now be in:
```
storage/app/public/tenants/{domain}/pwa/
```

The system automatically handles the new path. No manual migration needed for new installations.

## 📚 API Reference

### TenantSEOService

```php
// Enable SEO
$seoService->enableSEO($tenant, $config);

// Disable SEO
$seoService->disableSEO($tenant, $removeFiles = false);

// Get SEO status
$status = $seoService->getSEOStatus($tenant);

// Generate robots.txt
$seoService->generateRobotsTxt($tenant, $config);

// Generate sitemap.xml
$seoService->generateSitemap($tenant, $config);

// Add sitemap URL
$seoService->addSitemapUrl($tenant, $url, $options);

// Remove sitemap URL
$seoService->removeSitemapUrl($tenant, $url);

// Update disallow paths
$seoService->updateDisallowPaths($tenant, $paths);
```

### TenantAssetService

```php
// Create tenant structure
$assetService->createTenantStructure($tenant);

// Delete tenant structure
$assetService->deleteTenantStructure($tenant, $confirm = true);

// Copy asset
$assetService->copyAsset($tenant, $sourcePath, $destinationPath);

// Upload asset
$url = $assetService->uploadAsset($tenant, $file, $subfolder);

// Get folder size
$size = $assetService->getTenantFolderSize($tenant);

// List files
$files = $assetService->listFiles($tenant, $subfolder);
```

## 🎯 Best Practices

1. **Always use helper functions** for asset URLs in templates
2. **Regenerate sitemaps** when adding new pages
3. **Update robots.txt** when adding protected areas
4. **Monitor folder sizes** for large tenants
5. **Enable SEO early** in tenant lifecycle
6. **Use tenant_asset()** instead of asset() for tenant-specific files

## 💡 Tips

- SEO files are automatically generated on first access if missing
- Sitemap supports up to 50,000 URLs per file
- Robots.txt changes take effect immediately
- Assets are served via Laravel's storage symlink
- All paths are automatically sanitized for security

## 🐛 Troubleshooting

### Robots.txt not accessible

1. Ensure SEO is enabled: `php artisan tenant:seo:status --tenant=1`
2. Check if file exists: `ls storage/app/public/tenants/{domain}/seo/`
3. Regenerate: `php artisan tenant:seo:enable --tenant=1`

### Assets not loading

1. Check storage link: `php artisan storage:link`
2. Verify tenant folder exists: `tenant_path('assets', $tenant)`
3. Check file permissions: `chmod -R 755 storage/app/public/tenants/`

### Sitemap empty

1. Add URLs: `php artisan tenant:seo:generate-sitemap --tenant=1 --add-url=/page`
2. Or via code: `$seoService->addSitemapUrl($tenant, '/page')`

---

**Package:** ArtflowStudio Tenancy  
**Version:** 1.0.0  
**Last Updated:** October 23, 2025
