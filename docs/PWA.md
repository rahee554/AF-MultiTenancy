# 📱 Progressive Web App (PWA) Support

Complete PWA implementation for multi-tenant Laravel applications with per-tenant manifests, service workers, and offline support.

---

## 📖 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Folder Structure](#folder-structure)
- [Commands](#commands)
- [Configuration](#configuration)
- [Manifest Generation](#manifest-generation)
- [Service Workers](#service-workers)
- [Icons Management](#icons-management)
- [Helper Functions](#helper-functions)
- [Service API](#service-api)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

---

## Overview

The PWA system provides complete isolation of PWA files for each tenant, allowing independent Progressive Web App configurations.

### Features

- ✅ Per-tenant PWA manifests
- ✅ Custom service workers per tenant
- ✅ Icon management and storage
- ✅ Offline support configuration
- ✅ CLI commands for PWA management
- ✅ Automatic manifest generation
- ✅ Theme color customization

---

## Quick Start

### Enable PWA for a Tenant

```bash
# Enable PWA for specific tenant
php artisan tenant:pwa:enable --tenant="tenant-uuid"

# Enable for all tenants
php artisan tenant:pwa:enable --all
```

### Check PWA Status

```bash
php artisan tenant:pwa:status --all
```

### Include PWA in Your Layout

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    
    {{-- PWA Manifest --}}
    <link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">
    
    {{-- PWA Icons --}}
    <link rel="icon" type="image/png" sizes="192x192" href="{{ tenant_pwa_asset('icons/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ tenant_pwa_asset('icons/icon-512.png') }}">
    
    {{-- Theme Color --}}
    <meta name="theme-color" content="#667eea">
    
    {{-- Apple Touch Icon --}}
    <link rel="apple-touch-icon" href="{{ tenant_pwa_asset('icons/icon-192.png') }}">
</head>
<body>
    <!-- Your content -->
    
    {{-- PWA Service Worker Registration --}}
    @if(tenant_config('pwa_enabled'))
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register("{{ tenant_pwa_asset('sw.js') }}")
                .then(reg => console.log('Service Worker registered', reg))
                .catch(err => console.error('Service Worker registration failed', err));
        }
    </script>
    @endif
</body>
</html>
```

---

## Folder Structure

Each tenant's PWA files are stored in:

```
storage/app/public/tenants/{domain}/pwa/
├── manifest.json    # PWA manifest
├── sw.js           # Service worker
└── icons/          # PWA icons
    ├── icon-72.png
    ├── icon-96.png
    ├── icon-128.png
    ├── icon-144.png
    ├── icon-152.png
    ├── icon-192.png
    ├── icon-384.png
    └── icon-512.png
```

**Example for `tenant1.local`:**
```
storage/app/public/tenants/tenant1.local/pwa/
├── manifest.json
├── sw.js
└── icons/
    └── ...
```

---

## Commands

### Enable PWA

Enable PWA for one or more tenants.

```bash
# Single tenant
php artisan tenant:pwa:enable --tenant="uuid"

# All tenants
php artisan tenant:pwa:enable --all

# Interactive mode
php artisan tenant:pwa:enable --interactive
```

**Options:**
- `--tenant=UUID` - Tenant ID
- `--all` - Enable for all tenants
- `--interactive` - Interactive tenant selection

### Disable PWA

Disable PWA and optionally remove files.

```bash
# Disable (keep files)
php artisan tenant:pwa:disable --tenant="uuid"

# Disable and remove files
php artisan tenant:pwa:disable --tenant="uuid" --remove-files

# Disable all
php artisan tenant:pwa:disable --all --remove-files
```

**Options:**
- `--tenant=UUID` - Tenant ID
- `--all` - Disable for all tenants
- `--interactive` - Interactive selection
- `--remove-files` - Delete PWA files

### Check PWA Status

View PWA status for tenants.

```bash
# All tenants
php artisan tenant:pwa:status --all

# Specific tenant
php artisan tenant:pwa:status --tenant="uuid"
```

**Output Example:**
```
📱 PWA Status for All Tenants

+--------------------------------------+-----------+---------------+-----+----------+------------+
| ID                                   | Name      | Domain        | PWA | Manifest | Icons      |
+--------------------------------------+-----------+---------------+-----+----------+------------+
| c20dd9f2-8996-49d3-a8dc-d5f00376285e | Acme Corp | tenant1.local | ✅  | ✅       | ✅ (8)     |
| f621a19a-9004-4811-9ba7-6d54d32d6051 | Test Inc  | tenant2.local | ❌  | ❌       | ❌         |
+--------------------------------------+-----------+---------------+-----+----------+------------+

📈 Summary:
   Total Tenants: 2
   PWA Enabled: 1
   PWA Disabled: 1
```

---

## Configuration

### Default Manifest Configuration

When PWA is enabled, a default manifest.json is created:

```json
{
  "name": "Acme Corp",
  "short_name": "Acme",
  "description": "Progressive Web App for Acme Corp",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#667eea",
  "orientation": "portrait-primary",
  "icons": [
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-72.png",
      "sizes": "72x72",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-96.png",
      "sizes": "96x96",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-128.png",
      "sizes": "128x128",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-144.png",
      "sizes": "144x144",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-152.png",
      "sizes": "152x152",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-384.png",
      "sizes": "384x384",
      "type": "image/png"
    },
    {
      "src": "/storage/tenants/tenant1.local/pwa/icons/icon-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ]
}
```

### Customize Manifest

```php
use ArtflowStudio\Tenancy\Services\TenantPWAService;

$service = app(TenantPWAService::class);

// Update manifest
$service->updateManifest($tenant, [
    'name' => 'Custom App Name',
    'short_name' => 'CustomApp',
    'theme_color' => '#ff5733',
    'background_color' => '#000000',
    'display' => 'fullscreen',
]);
```

### Tenant Model Methods

```php
use ArtflowStudio\Tenancy\Models\Tenant;

$tenant = Tenant::find($id);

// Check if PWA is enabled
if ($tenant->hasPWA()) {
    // PWA is enabled
}

// Enable PWA programmatically
$tenant->enablePWA();

// Disable PWA
$tenant->disablePWA();

// Get PWA config
$config = $tenant->pwa_config;
```

---

## Manifest Generation

### Automatic Generation

When PWA is enabled, manifest.json is automatically generated with default values.

### Custom Manifest

Create custom manifest with specific configuration:

```php
$service = app(TenantPWAService::class);

$manifest = [
    'name' => $tenant->name,
    'short_name' => substr($tenant->name, 0, 12),
    'description' => "Progressive Web App for {$tenant->name}",
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#667eea',
    'orientation' => 'portrait-primary',
    'scope' => '/',
    'categories' => ['business', 'productivity'],
    'lang' => 'en-US',
];

$service->generateManifest($tenant, $manifest);
```

### Manifest Fields Reference

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `name` | string | Full app name | "Acme Corporation" |
| `short_name` | string | Short app name (max 12 chars) | "Acme" |
| `description` | string | App description | "Corporate portal" |
| `start_url` | string | Initial URL when app opens | "/" |
| `display` | string | Display mode | "standalone", "fullscreen", "minimal-ui" |
| `background_color` | string | Background color (hex) | "#ffffff" |
| `theme_color` | string | Theme color (hex) | "#667eea" |
| `orientation` | string | Screen orientation | "portrait-primary", "landscape" |
| `scope` | string | Navigation scope | "/" |
| `icons` | array | App icons | See manifest structure |

---

## Service Workers

### Default Service Worker

A basic service worker is created with caching strategy:

```javascript
// sw.js
const CACHE_NAME = 'tenant1-local-v1';
const urlsToCache = [
  '/',
  '/css/app.css',
  '/js/app.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => response || fetch(event.request))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
```

### Custom Service Worker

Upload custom service worker:

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);

// Upload custom sw.js
$service->copyAsset(
    $tenant,
    '/path/to/custom-sw.js',
    'pwa/sw.js'
);
```

### Advanced Caching Strategies

```javascript
// Advanced service worker with offline support
const CACHE_NAME = 'tenant1-local-v2';
const RUNTIME_CACHE = 'tenant1-runtime';

// Install event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll([
        '/',
        '/css/app.css',
        '/js/app.js',
        '/offline.html',
      ]);
    })
  );
  self.skipWaiting();
});

// Fetch event with offline fallback
self.addEventListener('fetch', (event) => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match('/offline.html');
      })
    );
  } else {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return caches.open(RUNTIME_CACHE).then((cache) => {
          return fetch(event.request).then((response) => {
            return cache.put(event.request, response.clone()).then(() => {
              return response;
            });
          });
        });
      })
    );
  }
});
```

---

## Icons Management

### Default Icons

PWA requires icons in multiple sizes:

- 72x72
- 96x96
- 128x128
- 144x144
- 152x152
- 192x192 (required, maskable)
- 384x384
- 512x512 (required, maskable)

### Upload Custom Icons

```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);

// Upload icons
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

foreach ($sizes as $size) {
    $service->copyAsset(
        $tenant,
        "/path/to/icon-{$size}.png",
        "pwa/icons/icon-{$size}.png"
    );
}
```

### Generate Icons from Source

Use image processing to generate all sizes from one source image:

```php
use Intervention\Image\Facades\Image;
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);
$sourceImage = '/path/to/logo.png';
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

foreach ($sizes as $size) {
    $icon = Image::make($sourceImage)
        ->resize($size, $size)
        ->encode('png');
    
    $tempPath = storage_path("app/temp/icon-{$size}.png");
    $icon->save($tempPath);
    
    $service->copyAsset(
        $tenant,
        $tempPath,
        "pwa/icons/icon-{$size}.png"
    );
    
    unlink($tempPath);
}
```

---

## Helper Functions

### `tenant_pwa_asset($path, $tenant = null)`

Generate URL for PWA asset.

```blade
{{-- Manifest --}}
<link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">

{{-- Service Worker --}}
<script>
    navigator.serviceWorker.register("{{ tenant_pwa_asset('sw.js') }}");
</script>

{{-- Icons --}}
<link rel="icon" href="{{ tenant_pwa_asset('icons/icon-192.png') }}">
```

### `tenant_path($subfolder, $tenant = null)`

Get absolute path to PWA folder.

```php
$pwaPath = tenant_path('pwa');
// Output: /var/www/storage/app/public/tenants/tenant1.local/pwa

$manifestPath = tenant_path('pwa') . '/manifest.json';
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
}
```

---

## Service API

### TenantPWAService Methods

```php
use ArtflowStudio\Tenancy\Services\TenantPWAService;

$service = app(TenantPWAService::class);
```

#### `enablePWA(Tenant $tenant, array $config = []): bool`

Enable PWA for tenant.

```php
$service->enablePWA($tenant, [
    'name' => 'Custom App',
    'theme_color' => '#ff5733',
]);
```

#### `disablePWA(Tenant $tenant, bool $removeFiles = false): bool`

Disable PWA.

```php
$service->disablePWA($tenant, true); // Remove files
```

#### `generateManifest(Tenant $tenant, array $config): string`

Generate manifest.json.

```php
$path = $service->generateManifest($tenant, [
    'name' => $tenant->name,
    'theme_color' => '#667eea',
]);
```

#### `updateManifest(Tenant $tenant, array $updates): bool`

Update existing manifest.

```php
$service->updateManifest($tenant, [
    'theme_color' => '#new-color',
    'background_color' => '#another-color',
]);
```

#### `getPWAStatus(Tenant $tenant): array`

Get PWA status.

```php
$status = $service->getPWAStatus($tenant);

// Returns:
// [
//     'enabled' => true,
//     'manifest_exists' => true,
//     'sw_exists' => true,
//     'icons_count' => 8,
//     'manifest_path' => '...',
// ]
```

---

## Best Practices

### 1. Optimize Icons

- Use PNG format for best compatibility
- Ensure icons are square (1:1 aspect ratio)
- Use transparency for irregular shapes
- Optimize file sizes (use tools like TinyPNG)

### 2. Service Worker Versioning

Update cache name when deploying changes:

```javascript
const CACHE_NAME = 'tenant1-local-v2'; // Increment version
```

### 3. Test on Multiple Devices

- Test installation on iOS (Safari)
- Test on Android (Chrome)
- Test on desktop (Chrome, Edge)
- Verify offline functionality

### 4. HTTPS Required

PWAs require HTTPS in production:
- Development: localhost works without HTTPS
- Production: Must use HTTPS

### 5. Manifest Validation

Validate manifest using Chrome DevTools:
1. Open DevTools
2. Go to Application tab
3. Check Manifest section
4. Look for errors/warnings

---

## Troubleshooting

### PWA Not Installing

**Problem:** App doesn't show "Add to Home Screen" prompt

**Solutions:**
1. Ensure HTTPS is enabled (production)
2. Verify manifest.json is valid
3. Check service worker is registered
4. Verify icon sizes (192px and 512px required)
5. Check browser console for errors

### Service Worker Not Updating

**Problem:** Changes not reflected in PWA

**Solutions:**
1. Unregister old service worker
2. Increment cache version name
3. Clear browser cache
4. Use "Update on reload" in DevTools

### Icons Not Showing

**Problem:** App icons not displaying correctly

**Solutions:**
1. Verify icon paths in manifest.json
2. Check icon files exist in storage
3. Ensure proper icon sizes
4. Validate image format (PNG required)

### Offline Mode Not Working

**Problem:** App doesn't work offline

**Solutions:**
1. Check service worker fetch event
2. Verify URLs are cached correctly
3. Test with DevTools offline mode
4. Check cache storage in DevTools

---

## Examples

### Complete PWA Setup Workflow

```bash
# 1. Create tenant
php artisan tenant:create --name="Acme Corp" --domain="acme.local"

# 2. Enable PWA
php artisan tenant:pwa:enable --tenant="uuid"

# 3. Upload custom icons
# (Use AssetService to upload icons)

# 4. Verify
php artisan tenant:pwa:status --tenant="uuid"
```

### Programmatic PWA Setup

```php
use ArtflowStudio\Tenancy\Models\Tenant;
use ArtflowStudio\Tenancy\Services\TenantPWAService;
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$tenant = Tenant::find($id);
$pwa = app(TenantPWAService::class);
$assets = app(TenantAssetService::class);

// Enable PWA
$pwa->enablePWA($tenant, [
    'name' => $tenant->name,
    'short_name' => substr($tenant->name, 0, 12),
    'theme_color' => '#667eea',
    'background_color' => '#ffffff',
]);

// Upload custom icons
$sourceLogo = '/path/to/tenant-logo.png';
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

foreach ($sizes as $size) {
    $icon = Image::make($sourceLogo)->resize($size, $size)->encode('png');
    $tempPath = storage_path("app/temp/icon-{$size}.png");
    $icon->save($tempPath);
    
    $assets->copyAsset($tenant, $tempPath, "pwa/icons/icon-{$size}.png");
    unlink($tempPath);
}

// Upload custom service worker
$assets->copyAsset($tenant, '/path/to/custom-sw.js', 'pwa/sw.js');

// Verify
$status = $pwa->getPWAStatus($tenant);
if ($status['enabled']) {
    echo "PWA enabled successfully!";
}
```

---

**For more information, see the [main README](../README.md) or [SEO Documentation](./SEO.md).**
