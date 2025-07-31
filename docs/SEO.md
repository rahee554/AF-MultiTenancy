# 🌐 SEO Management

Complete SEO management system for multi-tenant Laravel applications with per-tenant robots.txt and sitemap.xml support.

---

## 📖 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Folder Structure](#folder-structure)
- [Commands](#commands)
- [Configuration](#configuration)
- [Robots.txt Management](#robotstxt-management)
- [Sitemap Management](#sitemap-management)
- [Helper Functions](#helper-functions)
- [Service API](#service-api)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

---

## Overview

The SEO system provides complete isolation of SEO files for each tenant, allowing independent robots.txt and sitemap.xml management.

### Features

- ✅ Per-tenant robots.txt files
- ✅ Dynamic sitemap.xml generation
- ✅ Customizable allow/disallow rules
- ✅ Automatic sitemap URL management
- ✅ CLI commands for all operations
- ✅ Public route serving (`/robots.txt`, `/sitemap.xml`)
- ✅ On-the-fly generation for tenants without SEO enabled

---

## Quick Start

### Enable SEO for a Tenant

```bash
# Enable for specific tenant
php artisan tenant:seo:enable --tenant="tenant-uuid"

# Enable for all tenants
php artisan tenant:seo:enable --all

# Enable with custom disallow paths
php artisan tenant:seo:enable --tenant="uuid" --disallow=/admin,/api
```

### Check SEO Status

```bash
php artisan tenant:seo:status --all
```

### Add URLs to Sitemap

```bash
php artisan tenant:seo:generate-sitemap \
    --tenant="uuid" \
    --add-url="/products" \
    --priority=0.9 \
    --changefreq=daily
```

---

## Folder Structure

Each tenant's SEO files are stored in:

```
storage/app/public/tenants/{domain}/seo/
├── robots.txt       # Tenant-specific robots.txt
└── sitemap.xml      # Tenant-specific sitemap
```

**Example for `tenant1.local`:**
```
storage/app/public/tenants/tenant1.local/seo/
├── robots.txt
└── sitemap.xml
```

**Important:** Folder names use the **exact domain** (e.g., `tenant1.local`, not `tenant1_local`).

---

## Commands

### Enable SEO

Enable SEO for one or more tenants.

```bash
# Single tenant
php artisan tenant:seo:enable --tenant="uuid"

# All tenants
php artisan tenant:seo:enable --all

# Interactive mode (select from list)
php artisan tenant:seo:enable --interactive

# With custom disallow paths
php artisan tenant:seo:enable --tenant="uuid" --disallow=/private,/admin,/api
```

**Options:**
- `--tenant=UUID` - Tenant ID
- `--all` - Enable for all tenants
- `--interactive` - Interactive tenant selection
- `--disallow=PATHS` - Comma-separated paths to disallow

### Disable SEO

Disable SEO and optionally remove files.

```bash
# Disable (keep files)
php artisan tenant:seo:disable --tenant="uuid"

# Disable and remove files
php artisan tenant:seo:disable --tenant="uuid" --remove-files

# Disable all
php artisan tenant:seo:disable --all --remove-files
```

**Options:**
- `--tenant=UUID` - Tenant ID
- `--all` - Disable for all tenants
- `--interactive` - Interactive selection (only SEO-enabled tenants)
- `--remove-files` - Delete robots.txt and sitemap.xml

### Check SEO Status

View SEO status for tenants.

```bash
# All tenants
php artisan tenant:seo:status --all

# Specific tenant
php artisan tenant:seo:status --tenant="uuid"
```

**Output Example:**
```
📊 SEO Status for All Tenants

+--------------------------------------+-----------+---------------+-----+--------+---------+
| ID                                   | Name      | Domain        | SEO | Robots | Sitemap |
+--------------------------------------+-----------+---------------+-----+--------+---------+
| c20dd9f2-8996-49d3-a8dc-d5f00376285e | Acme Corp | tenant1.local | ✅  | ✅     | ✅      |
| f621a19a-9004-4811-9ba7-6d54d32d6051 | Test Inc  | tenant2.local | ❌  | ❌     | ❌      |
+--------------------------------------+-----------+---------------+-----+--------+---------+

📈 Summary:
   Total Tenants: 2
   SEO Enabled: 1
   SEO Disabled: 1
```

### Generate Sitemap

Generate or update sitemap for tenants.

```bash
# Regenerate for specific tenant
php artisan tenant:seo:generate-sitemap --tenant="uuid"

# Regenerate for all SEO-enabled tenants
php artisan tenant:seo:generate-sitemap --all

# Add single URL
php artisan tenant:seo:generate-sitemap \
    --tenant="uuid" \
    --add-url="/products" \
    --priority=0.9 \
    --changefreq=daily

# Add multiple URLs (run command multiple times or use service)
php artisan tenant:seo:generate-sitemap --tenant="uuid" --add-url="/about"
php artisan tenant:seo:generate-sitemap --tenant="uuid" --add-url="/contact"
```

**Options:**
- `--tenant=UUID` - Tenant ID
- `--all` - Generate for all SEO-enabled tenants
- `--add-url=PATH` - Add URL to sitemap
- `--priority=FLOAT` - Priority for added URL (0.0-1.0, default: 0.8)
- `--changefreq=FREQ` - Change frequency (daily, weekly, monthly, etc.)

---

## Configuration

### Default Configuration

When SEO is enabled, default configuration is created:

```php
[
    'allow_all' => true,
    'disallow_paths' => [
        '/admin',
        '/api',
        '/login',
        '/register',
    ],
    'sitemap_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
        ['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly'],
        ['url' => '/contact', 'priority' => 0.8, 'changefreq' => 'monthly'],
    ],
]
```

### Tenant Model Methods

```php
use ArtflowStudio\Tenancy\Models\Tenant;

$tenant = Tenant::find($id);

// Check if SEO is enabled
if ($tenant->hasSEO()) {
    // SEO is enabled
}

// Enable SEO programmatically
$tenant->enableSEO([
    'allow_all' => true,
    'disallow_paths' => ['/admin', '/private'],
]);

// Disable SEO
$tenant->disableSEO();

// Get SEO config
$config = $tenant->seo_config;
```

---

## Robots.txt Management

### Default robots.txt

```
# Robots.txt for Acme Corp
# Generated: 2025-10-23 14:12:38

User-agent: *
Allow: /
Disallow: /admin
Disallow: /api
Disallow: /login
Disallow: /register

# Sitemap
Sitemap: https://tenant1.local/sitemap.xml
```

### Custom robots.txt

Use the `TenantSEOService` to customize:

```php
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$service = app(TenantSEOService::class);

// Update disallow paths
$service->updateDisallowPaths($tenant, [
    '/admin',
    '/private',
    '/internal',
]);

// Generate custom robots.txt
$service->generateRobotsTxt($tenant, [
    'allow_all' => false, // Disallow all by default
    'disallow_paths' => ['*'], // Disallow everything
]);
```

### Public Route

Robots.txt is automatically served at:
```
https://tenant1.local/robots.txt
```

If SEO is not enabled, a default robots.txt is generated on-the-fly.

---

## Sitemap Management

### Default Sitemap Structure

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://tenant1.local/</loc>
        <priority>1.0</priority>
        <changefreq>daily</changefreq>
        <lastmod>2025-10-23</lastmod>
    </url>
    <url>
        <loc>https://tenant1.local/about</loc>
        <priority>0.8</priority>
        <changefreq>monthly</changefreq>
        <lastmod>2025-10-23</lastmod>
    </url>
</urlset>
```

### Add URLs to Sitemap

**Via Command:**
```bash
php artisan tenant:seo:generate-sitemap \
    --tenant="uuid" \
    --add-url="/products" \
    --priority=0.9 \
    --changefreq=weekly
```

**Via Service:**
```php
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$service = app(TenantSEOService::class);

// Add single URL
$service->addSitemapUrl($tenant, '/products', [
    'priority' => 0.9,
    'changefreq' => 'daily',
]);

// Add multiple URLs
$urls = [
    ['/products', ['priority' => 0.9, 'changefreq' => 'daily']],
    ['/blog', ['priority' => 0.7, 'changefreq' => 'weekly']],
    ['/categories', ['priority' => 0.6, 'changefreq' => 'weekly']],
];

foreach ($urls as [$url, $options]) {
    $service->addSitemapUrl($tenant, $url, $options);
}
```

### Remove URLs from Sitemap

```php
$service->removeSitemapUrl($tenant, '/old-page');
```

### Public Route

Sitemap is automatically served at:
```
https://tenant1.local/sitemap.xml
```

---

## Helper Functions

### `tenant_seo_asset($path, $tenant = null)`

Generate URL for SEO asset.

```php
// In Blade
<link rel="canonical" href="{{ tenant_seo_asset('sitemap.xml') }}">

// In PHP
$robotsUrl = tenant_seo_asset('robots.txt');
// Output: http://localhost/storage/tenants/tenant1.local/seo/robots.txt
```

### `tenant_path($subfolder, $tenant = null)`

Get absolute path to SEO folder.

```php
$seoPath = tenant_path('seo');
// Output: /var/www/storage/app/public/tenants/tenant1.local/seo

// Check if files exist
$robotsFile = tenant_path('seo') . '/robots.txt';
if (file_exists($robotsFile)) {
    $content = file_get_contents($robotsFile);
}
```

---

## Service API

### TenantSEOService Methods

```php
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$service = app(TenantSEOService::class);
```

#### `enableSEO(Tenant $tenant, array $config = []): array`

Enable SEO for tenant with custom configuration.

```php
$result = $service->enableSEO($tenant, [
    'allow_all' => true,
    'disallow_paths' => ['/admin', '/api'],
    'sitemap_urls' => [
        ['url' => '/', 'priority' => 1.0],
        ['url' => '/about', 'priority' => 0.8],
    ],
]);

// Returns:
// [
//     'success' => true,
//     'robots_generated' => true,
//     'sitemap_generated' => true,
//     'storage_path' => '...'
// ]
```

#### `disableSEO(Tenant $tenant, bool $removeFiles = false): bool`

Disable SEO and optionally remove files.

```php
$service->disableSEO($tenant, true); // Remove files
```

#### `getSEOStatus(Tenant $tenant): array`

Get detailed SEO status.

```php
$status = $service->getSEOStatus($tenant);

// Returns:
// [
//     'enabled' => true,
//     'robots_exists' => true,
//     'sitemap_exists' => true,
//     'robots_path' => '...',
//     'sitemap_path' => '...',
//     'config' => [...],
// ]
```

#### `generateRobotsTxt(Tenant $tenant, array $config): string`

Generate robots.txt file.

```php
$path = $service->generateRobotsTxt($tenant, [
    'allow_all' => true,
    'disallow_paths' => ['/admin'],
]);
```

#### `generateSitemap(Tenant $tenant, array $config): string`

Generate sitemap.xml file.

```php
$path = $service->generateSitemap($tenant, [
    'sitemap_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
]);
```

#### `addSitemapUrl(Tenant $tenant, string $url, array $options = []): bool`

Add URL to existing sitemap.

```php
$service->addSitemapUrl($tenant, '/new-page', [
    'priority' => 0.7,
    'changefreq' => 'weekly',
]);
```

#### `removeSitemapUrl(Tenant $tenant, string $url): bool`

Remove URL from sitemap.

```php
$service->removeSitemapUrl($tenant, '/old-page');
```

#### `updateDisallowPaths(Tenant $tenant, array $paths): bool`

Update disallow paths in robots.txt.

```php
$service->updateDisallowPaths($tenant, [
    '/admin',
    '/private',
    '/internal',
]);
```

---

## Best Practices

### 1. Enable SEO Early

Enable SEO during tenant creation to ensure robots.txt and sitemap.xml are available from day one.

```php
$tenant = Tenant::create([...]);
$tenant->enableSEO();
```

### 2. Keep Sitemaps Updated

Update sitemaps when content changes:

```php
// When creating a new page
Route::post('/pages', function (Request $request) {
    $page = Page::create($request->all());
    
    // Add to sitemap
    app(TenantSEOService::class)->addSitemapUrl(
        current_tenant(),
        $page->slug,
        ['priority' => 0.8, 'changefreq' => 'weekly']
    );
});

// When deleting a page
Route::delete('/pages/{page}', function (Page $page) {
    app(TenantSEOService::class)->removeSitemapUrl(
        current_tenant(),
        $page->slug
    );
    
    $page->delete();
});
```

### 3. Customize Disallow Paths

Protect sensitive areas:

```php
$service->updateDisallowPaths($tenant, [
    '/admin',
    '/api',
    '/dashboard',
    '/internal',
    '/private',
]);
```

### 4. Set Appropriate Priorities

- Homepage: 1.0
- Main sections: 0.8-0.9
- Regular pages: 0.6-0.7
- Less important: 0.4-0.5

### 5. Monitor SEO Status

Regularly check SEO status:

```bash
php artisan tenant:seo:status --all
```

---

## Troubleshooting

### Robots.txt Returns 404

**Problem:** `/robots.txt` returns 404

**Solutions:**
1. Check if SEO is enabled: `php artisan tenant:seo:status --tenant=uuid`
2. Verify routes are loaded: Check `routes/tenant-seo.php` is included
3. Clear cache: `php artisan route:clear`

### Sitemap Shows Wrong URLs

**Problem:** Sitemap contains incorrect URLs

**Solutions:**
1. Regenerate sitemap: `php artisan tenant:seo:generate-sitemap --tenant=uuid`
2. Verify tenant domain: Check `$tenant->domains()->first()->domain`
3. Update URLs manually via service

### Files Not Created

**Problem:** robots.txt and sitemap.xml not created

**Solutions:**
1. Check folder permissions: `storage/app/public/tenants/` must be writable
2. Verify tenant folder exists: `ls storage/app/public/tenants/tenant1.local`
3. Enable SEO: `php artisan tenant:seo:enable --tenant=uuid`

### Wrong Folder Name

**Problem:** Folder created with underscores instead of dots

**Solutions:**
1. This has been fixed - folders now use exact domain names
2. Delete old folder: `rm -rf storage/app/public/tenants/tenant1_local`
3. Re-enable SEO: `php artisan tenant:seo:enable --tenant=uuid`

---

## Examples

### Complete SEO Setup Workflow

```bash
# 1. Create tenant
php artisan tenant:create --name="Acme Corp" --domain="acme.local"

# 2. Enable SEO
php artisan tenant:seo:enable --tenant="uuid"

# 3. Add custom URLs to sitemap
php artisan tenant:seo:generate-sitemap --tenant="uuid" --add-url="/products" --priority=0.9
php artisan tenant:seo:generate-sitemap --tenant="uuid" --add-url="/services" --priority=0.8
php artisan tenant:seo:generate-sitemap --tenant="uuid" --add-url="/blog" --priority=0.7

# 4. Verify
php artisan tenant:seo:status --tenant="uuid"
```

### Programmatic SEO Management

```php
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantSEOService;

$tenant = Tenant::find($id);
$seo = app(TenantSEOService::class);

// Enable with custom config
$seo->enableSEO($tenant, [
    'allow_all' => true,
    'disallow_paths' => ['/admin', '/api', '/dashboard'],
    'sitemap_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
        ['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly'],
        ['url' => '/contact', 'priority' => 0.8, 'changefreq' => 'monthly'],
        ['url' => '/products', 'priority' => 0.9, 'changefreq' => 'weekly'],
    ],
]);

// Get status
$status = $seo->getSEOStatus($tenant);
if ($status['enabled']) {
    echo "SEO is enabled for {$tenant->name}";
}

// Add dynamic URLs
$products = Product::all();
foreach ($products as $product) {
    $seo->addSitemapUrl($tenant, "/product/{$product->slug}", [
        'priority' => 0.7,
        'changefreq' => 'weekly',
    ]);
}
```

---

**For more information, see the [main README](../README.md) or [Asset Management](./ASSET-MANAGEMENT.md) documentation.**
