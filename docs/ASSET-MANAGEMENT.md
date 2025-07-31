# 📁 Asset Management

Complete asset management system for multi-tenant Laravel applications with isolated storage, helper functions, and automatic directory creation.

---

## 📖 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Folder Structure](#folder-structure)
- [Helper Functions](#helper-functions)
- [Service API](#service-api)
- [File Operations](#file-operations)
- [Storage Management](#storage-management)
- [Best Practices](#best-practices)
- [Examples](#examples)
- [Troubleshooting](#troubleshooting)

---

## Overview

The Asset Management system provides complete isolation of files and assets for each tenant with automatic directory creation and easy-to-use helper functions.

### Features

- ✅ **Isolated Storage** - Each tenant has separate folders
- ✅ **Exact Domain Naming** - Folders use exact domain names (e.g., `tenant1.local`)
- ✅ **5 Asset Categories** - assets, pwa, seo, documents, media
- ✅ **Automatic Creation** - Directories created on-demand
- ✅ **Helper Functions** - 7 global helpers for easy access
- ✅ **File Operations** - Upload, copy, delete, list files
- ✅ **Storage Analytics** - Folder size calculation and breakdowns

---

## Quick Start

### Access Tenant Assets in Blade

```blade
{{-- Images --}}
<img src="{{ tenant_asset('images/logo.png') }}" alt="Logo">
<img src="{{ tenant_asset('images/banner.jpg') }}" alt="Banner">

{{-- CSS --}}
<link href="{{ tenant_asset('css/custom.css') }}" rel="stylesheet">

{{-- JavaScript --}}
<script src="{{ tenant_asset('js/app.js') }}"></script>

{{-- Documents --}}
<a href="{{ tenant_asset('documents/brochure.pdf') }}">Download Brochure</a>

{{-- PWA Assets --}}
<link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">

{{-- SEO Assets --}}
<link rel="sitemap" href="{{ tenant_seo_asset('sitemap.xml') }}">
```

### Upload Files Programmatically

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);
$tenant = current_tenant();

// Upload from request
$file = $request->file('logo');
$path = $service->uploadAsset($tenant, $file, 'assets/images');

// Copy existing file
$service->copyAsset($tenant, '/path/to/source.png', 'assets/images/logo.png');
```

---

## Folder Structure

### Complete Directory Layout

Each tenant has its own isolated folder structure:

```
storage/app/public/tenants/{domain}/
├── assets/          # General assets (images, fonts, CSS, JS, etc.)
│   ├── images/
│   ├── css/
│   ├── js/
│   └── fonts/
├── pwa/            # PWA files (manifest, service worker, icons)
│   ├── manifest.json
│   ├── sw.js
│   └── icons/
├── seo/            # SEO files (robots.txt, sitemap.xml)
│   ├── robots.txt
│   └── sitemap.xml
├── documents/      # Documents and downloads
│   ├── pdfs/
│   ├── reports/
│   └── brochures/
└── media/          # Media files (videos, audio)
    ├── videos/
    ├── audio/
    └── images/
```

### Example for `tenant1.local`

```
storage/app/public/tenants/tenant1.local/
├── assets/
│   ├── images/
│   │   ├── logo.png
│   │   └── banner.jpg
│   ├── css/
│   │   └── custom.css
│   └── js/
│       └── app.js
├── pwa/
│   ├── manifest.json
│   └── icons/
│       ├── icon-192.png
│       └── icon-512.png
├── seo/
│   ├── robots.txt
│   └── sitemap.xml
├── documents/
│   └── brochure.pdf
└── media/
    └── videos/
        └── intro.mp4
```

### Important Notes

- **Exact Domain Names**: Folders use exact domain (e.g., `tenant1.local`, NOT `tenant1_local`)
- **Automatic Creation**: Folders created automatically when needed
- **Public Access**: Accessible via `/storage/tenants/{domain}/{subfolder}/`
- **Storage Link**: Requires `php artisan storage:link` to be run

---

## Helper Functions

### `tenant_asset($path, $tenant = null)`

Generate URL for tenant-specific asset.

**Parameters:**
- `$path` (string): Relative path to asset
- `$tenant` (Tenant|null): Tenant instance (auto-detected if null)

**Returns:** Full public URL to asset

**Examples:**
```php
// In Blade
<img src="{{ tenant_asset('images/logo.png') }}">
// Output: http://localhost/storage/tenants/tenant1.local/assets/images/logo.png

// With custom tenant
$url = tenant_asset('images/banner.jpg', $specificTenant);

// Subdirectories
<link href="{{ tenant_asset('css/theme.css') }}">
<script src="{{ tenant_asset('js/custom.js') }}"></script>
```

### `tenant_pwa_asset($path, $tenant = null)`

Generate URL for PWA asset.

**Parameters:**
- `$path` (string): Relative path to PWA asset
- `$tenant` (Tenant|null): Tenant instance

**Returns:** Full public URL to PWA asset

**Examples:**
```php
// Manifest
<link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">

// Icons
<link rel="icon" href="{{ tenant_pwa_asset('icons/icon-192.png') }}">

// Service Worker
<script>
    navigator.serviceWorker.register("{{ tenant_pwa_asset('sw.js') }}");
</script>
```

### `tenant_seo_asset($path, $tenant = null)`

Generate URL for SEO asset.

**Parameters:**
- `$path` (string): Relative path to SEO asset
- `$tenant` (Tenant|null): Tenant instance

**Returns:** Full public URL to SEO asset

**Examples:**
```php
// Robots.txt
<link rel="robots" href="{{ tenant_seo_asset('robots.txt') }}">

// Sitemap
<link rel="sitemap" href="{{ tenant_seo_asset('sitemap.xml') }}">
```

### `tenant_path($subfolder = '', $tenant = null)`

Get absolute storage path for tenant.

**Parameters:**
- `$subfolder` (string): Subfolder name (assets, pwa, seo, documents, media)
- `$tenant` (Tenant|null): Tenant instance

**Returns:** Absolute file system path

**Examples:**
```php
// Base path
$basePath = tenant_path();
// Output: /var/www/storage/app/public/tenants/tenant1.local

// Assets folder
$assetsPath = tenant_path('assets');
// Output: /var/www/storage/app/public/tenants/tenant1.local/assets

// Check if file exists
$logoPath = tenant_path('assets') . '/images/logo.png';
if (file_exists($logoPath)) {
    // File exists
}

// Read file
$content = file_get_contents(tenant_path('documents') . '/terms.txt');
```

### `tenant_url($subfolder = '', $tenant = null)`

Get public URL path for tenant (without domain).

**Parameters:**
- `$subfolder` (string): Subfolder name
- `$tenant` (Tenant|null): Tenant instance

**Returns:** Relative URL path

**Examples:**
```php
$assetsUrl = tenant_url('assets');
// Output: /storage/tenants/tenant1.local/assets

$pwaUrl = tenant_url('pwa');
// Output: /storage/tenants/tenant1.local/pwa
```

### `current_tenant()`

Get current tenant instance.

**Returns:** Tenant instance or null

**Examples:**
```php
$tenant = current_tenant();

if ($tenant) {
    echo "Current tenant: " . $tenant->name;
    
    // Use with other helpers
    $logo = tenant_asset('images/logo.png', $tenant);
}
```

### `tenant_config($key = null, $default = null)`

Get tenant configuration value.

**Parameters:**
- `$key` (string|null): Configuration key (dot notation)
- `$default` (mixed): Default value if key not found

**Returns:** Configuration value

**Examples:**
```php
// Get SEO config
$seoConfig = tenant_config('seo_config');

// Get specific value with default
$themeColor = tenant_config('pwa_config.theme_color', '#667eea');

// Check if feature is enabled
if (tenant_config('seo_enabled')) {
    // SEO is enabled
}
```

### `tenant_domain_folder($domain)`

Convert domain to folder name (lowercase, exact domain).

**Parameters:**
- `$domain` (string): Domain name

**Returns:** Folder-safe domain name

**Examples:**
```php
$folder = tenant_domain_folder('Tenant1.Local');
// Output: tenant1.local

$folder = tenant_domain_folder('SUBDOMAIN.EXAMPLE.COM');
// Output: subdomain.example.com
```

---

## Service API

### TenantAssetService

Complete API for managing tenant assets.

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);
$tenant = current_tenant();
```

#### `createTenantStructure(Tenant $tenant): bool`

Create complete folder structure for tenant.

**Creates:**
- assets/
- pwa/
- seo/
- documents/
- media/

**Example:**
```php
$result = $service->createTenantStructure($tenant);

if ($result) {
    echo "Folder structure created successfully!";
}
```

#### `deleteTenantStructure(Tenant $tenant): bool`

Delete entire tenant folder and all contents.

**Warning:** This permanently deletes all files!

**Example:**
```php
if (confirm("Are you sure?")) {
    $service->deleteTenantStructure($tenant);
}
```

#### `uploadAsset(Tenant $tenant, UploadedFile $file, string $subfolder, string $filename = null): string`

Upload file to tenant folder.

**Parameters:**
- `$tenant`: Tenant instance
- `$file`: Uploaded file object
- `$subfolder`: Target subfolder (e.g., 'assets/images')
- `$filename`: Optional custom filename

**Returns:** Relative path to uploaded file

**Example:**
```php
// Upload from form
$file = $request->file('logo');
$path = $service->uploadAsset($tenant, $file, 'assets/images');

// With custom filename
$path = $service->uploadAsset($tenant, $file, 'assets/images', 'logo.png');

// Upload to documents
$doc = $request->file('document');
$path = $service->uploadAsset($tenant, $doc, 'documents/pdfs');
```

#### `copyAsset(Tenant $tenant, string $sourcePath, string $destinationPath): bool`

Copy file from local filesystem to tenant folder.

**Parameters:**
- `$tenant`: Tenant instance
- `$sourcePath`: Source file path
- `$destinationPath`: Destination path (relative to tenant folder)

**Returns:** Success boolean

**Example:**
```php
// Copy logo
$service->copyAsset(
    $tenant,
    '/path/to/logo.png',
    'assets/images/logo.png'
);

// Copy multiple files
$files = [
    '/path/to/logo.png' => 'assets/images/logo.png',
    '/path/to/banner.jpg' => 'assets/images/banner.jpg',
    '/path/to/icon.png' => 'pwa/icons/icon-192.png',
];

foreach ($files as $source => $dest) {
    $service->copyAsset($tenant, $source, $dest);
}
```

#### `deleteAsset(Tenant $tenant, string $relativePath): bool`

Delete specific file from tenant storage.

**Example:**
```php
// Delete single file
$service->deleteAsset($tenant, 'assets/images/old-logo.png');

// Delete document
$service->deleteAsset($tenant, 'documents/old-brochure.pdf');
```

#### `listFiles(Tenant $tenant, string $subfolder = ''): array`

List all files in tenant folder or subfolder.

**Returns:** Array of file information

**Example:**
```php
// List all files
$allFiles = $service->listFiles($tenant);

// List files in specific folder
$images = $service->listFiles($tenant, 'assets/images');

// Output structure:
// [
//     [
//         'name' => 'logo.png',
//         'path' => '/absolute/path/to/logo.png',
//         'size' => 12345,
//         'modified' => '2025-10-23 14:30:00',
//         'url' => 'http://localhost/storage/tenants/tenant1.local/assets/images/logo.png',
//     ],
//     ...
// ]

// Display files
foreach ($images as $file) {
    echo "{$file['name']} ({$file['size']} bytes)\n";
}
```

#### `getTenantFolderSize(Tenant $tenant): array`

Calculate total size of tenant storage with breakdown by subfolder.

**Returns:** Array with size information in bytes

**Example:**
```php
$sizes = $service->getTenantFolderSize($tenant);

// Output structure:
// [
//     'total' => 1024000,
//     'assets' => 500000,
//     'pwa' => 200000,
//     'seo' => 10000,
//     'documents' => 300000,
//     'media' => 14000,
// ]

// Display formatted
function formatBytes($bytes) {
    return round($bytes / 1024 / 1024, 2) . ' MB';
}

echo "Total: " . formatBytes($sizes['total']) . "\n";
echo "Assets: " . formatBytes($sizes['assets']) . "\n";
echo "PWA: " . formatBytes($sizes['pwa']) . "\n";
```

#### `assetExists(Tenant $tenant, string $relativePath): bool`

Check if asset exists.

**Example:**
```php
if ($service->assetExists($tenant, 'assets/images/logo.png')) {
    echo "Logo exists!";
} else {
    echo "Logo not found";
}
```

#### `getAssetUrl(Tenant $tenant, string $relativePath): string`

Get public URL for asset.

**Example:**
```php
$url = $service->getAssetUrl($tenant, 'assets/images/logo.png');
// Output: http://localhost/storage/tenants/tenant1.local/assets/images/logo.png
```

---

## File Operations

### Upload Files from Form

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

class AssetController extends Controller
{
    public function upload(Request $request, TenantAssetService $service)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:image,document,media',
        ]);
        
        $tenant = current_tenant();
        $file = $request->file('file');
        
        // Determine subfolder based on type
        $subfolder = match($request->type) {
            'image' => 'assets/images',
            'document' => 'documents',
            'media' => 'media/videos',
        };
        
        // Upload
        $path = $service->uploadAsset($tenant, $file, $subfolder);
        
        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $service->getAssetUrl($tenant, $path),
        ]);
    }
}
```

### Copy Assets During Tenant Creation

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

class TenantSetupService
{
    public function setupDefaultAssets(Tenant $tenant)
    {
        $assetService = app(TenantAssetService::class);
        
        // Create structure
        $assetService->createTenantStructure($tenant);
        
        // Copy default assets
        $defaults = [
            'logo' => 'default/logo.png',
            'favicon' => 'default/favicon.ico',
            'banner' => 'default/banner.jpg',
        ];
        
        foreach ($defaults as $name => $source) {
            $assetService->copyAsset(
                $tenant,
                resource_path("defaults/{$source}"),
                "assets/images/{$name}" . pathinfo($source, PATHINFO_EXTENSION)
            );
        }
    }
}
```

### Bulk File Management

```php
// Upload multiple files
public function uploadBulk(Request $request, TenantAssetService $service)
{
    $files = $request->file('files'); // Multiple files
    $tenant = current_tenant();
    $uploaded = [];
    
    foreach ($files as $file) {
        $path = $service->uploadAsset($tenant, $file, 'assets/images');
        $uploaded[] = [
            'name' => $file->getClientOriginalName(),
            'url' => $service->getAssetUrl($tenant, $path),
        ];
    }
    
    return response()->json(['files' => $uploaded]);
}

// Delete multiple files
public function deleteBulk(Request $request, TenantAssetService $service)
{
    $paths = $request->input('paths'); // Array of paths
    $tenant = current_tenant();
    
    foreach ($paths as $path) {
        $service->deleteAsset($tenant, $path);
    }
    
    return response()->json(['success' => true]);
}
```

---

## Storage Management

### Monitor Storage Usage

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

class StorageController extends Controller
{
    public function stats(TenantAssetService $service)
    {
        $tenant = current_tenant();
        $sizes = $service->getTenantFolderSize($tenant);
        
        return view('storage.stats', [
            'sizes' => $sizes,
            'formatted' => array_map(function($bytes) {
                return number_format($bytes / 1024 / 1024, 2) . ' MB';
            }, $sizes),
        ]);
    }
}
```

### Storage Quota Enforcement

```php
class TenantStorageMiddleware
{
    public function handle($request, Closure $next)
    {
        $tenant = current_tenant();
        $quota = $tenant->storage_quota ?? 1024 * 1024 * 1024; // 1GB default
        
        $service = app(TenantAssetService::class);
        $sizes = $service->getTenantFolderSize($tenant);
        
        if ($sizes['total'] > $quota) {
            return response()->json([
                'error' => 'Storage quota exceeded',
                'used' => $sizes['total'],
                'quota' => $quota,
            ], 413);
        }
        
        return $next($request);
    }
}
```

### Cleanup Old Files

```php
public function cleanup(TenantAssetService $service)
{
    $tenant = current_tenant();
    $files = $service->listFiles($tenant, 'documents');
    
    $thirtyDaysAgo = now()->subDays(30);
    
    foreach ($files as $file) {
        $modified = \Carbon\Carbon::parse($file['modified']);
        
        if ($modified->lt($thirtyDaysAgo)) {
            $service->deleteAsset($tenant, 'documents/' . $file['name']);
        }
    }
}
```

---

## Best Practices

### 1. Validate Uploads

Always validate file uploads:

```php
$request->validate([
    'file' => [
        'required',
        'file',
        'max:10240', // 10MB
        'mimes:jpeg,png,pdf,docx',
    ],
]);
```

### 2. Use Subfolder Organization

Organize files logically:

```
assets/
├── images/
│   ├── products/
│   ├── blog/
│   └── ui/
├── css/
└── js/
```

### 3. Implement Storage Quotas

Set and enforce storage limits:

```php
$tenant->update(['storage_quota' => 1024 * 1024 * 1024]); // 1GB
```

### 4. Regular Cleanup

Schedule regular cleanup of old files:

```php
// In app/Console/Kernel.php
$schedule->call(function () {
    // Cleanup logic
})->daily();
```

### 5. Secure File Access

Protect sensitive documents:

```php
// Check permissions before serving files
if (!auth()->user()->can('view', $document)) {
    abort(403);
}
```

---

## Troubleshooting

### Files Not Accessible

**Problem:** Assets return 404

**Solutions:**
1. Run `php artisan storage:link`
2. Check file exists: `tenant_path('assets') . '/file.png'`
3. Verify folder permissions (775 or 755)

### Upload Failures

**Problem:** File uploads fail

**Solutions:**
1. Check upload_max_filesize in php.ini
2. Verify folder is writable
3. Check disk space
4. Validate file type

### Wrong URL Generated

**Problem:** Helper functions return incorrect URLs

**Solutions:**
1. Check APP_URL in .env
2. Verify storage link exists
3. Clear config cache: `php artisan config:clear`

---

**For more information, see the [main README](../README.md), [SEO Documentation](./SEO.md), or [PWA Documentation](./PWA.md).**
