# 🏢 ArtFlow Studio Tenancy Package

[![Latest Version](https://img.shields.io/packagist/v/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)
[![License](https://img.shields.io/packagist/l/artflow-studio/tenancy.svg?style=flat-square)](https://packagist.org/packages/artflow-studio/tenancy)

**Enterprise-grade Laravel multi-tenancy with PWA, SEO, and Asset Management**

🚀 **Complete Multi-Tenancy Solution** - Built on `stancl/tenancy` with advanced features including isolated assets, SEO management, PWA support, performance monitoring, and universal routing capabilities.

---

## ⚡ Quick Start

### Installation

```bash
# Install the package (includes stancl/tenancy automatically)
composer require artflow-studio/tenancy

# Run the installation (sets up everything including asset directories)
php artisan af-tenancy:install

# Create your first tenant
php artisan tenant:create
```

### Basic Usage

```php
// routes/web.php

// Central domain routes (your main app/admin)
Route::middleware(['central.web'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('admin', [AdminController::class, 'index']);
});

// Tenant routes (with full asset and SEO support)
Route::middleware(['tenant.web'])->group(function () {
    Route::get('/', function () {
        return view('tenant.home');
    });
    
    // Tenant assets are automatically scoped
    // Use helper functions in your views:
    // <img src="{{ tenant_asset('images/logo.png') }}" />
    // <link href="{{ tenant_pwa_asset('manifest.json') }}" />
});
```

---

## 🎯 Core Features

### 🏢 Multi-Tenancy Foundation
- **Complete Database Isolation** - Each tenant gets its own database using stancl/tenancy
- **Domain-Based Routing** - Automatic tenant detection and switching
- **Universal Middleware** - Routes that work on both central and tenant domains
- **Custom Database Names** - User-defined database names with validation
- **FastPanel Integration** - Seamless integration with FastPanel control panel

### 📁 Asset Management (NEW!)
- **Isolated Asset Storage** - Each tenant has separate folders for assets, documents, and media
- **Exact Domain Naming** - Folders use exact domain names (e.g., `tenant1.local`, not `tenant1_local`)
- **Automatic Directory Creation** - Asset structure created automatically during tenant setup
- **Helper Functions** - Easy-to-use functions for generating asset URLs
- **5 Asset Categories**: assets, pwa, seo, documents, media

**Folder Structure:**
```
storage/app/public/tenants/
├── tenant1.local/
│   ├── assets/      # General assets (images, fonts, CSS, JS)
│   ├── pwa/         # PWA files (manifest, icons, service worker)
│   ├── seo/         # SEO files (robots.txt, sitemap.xml)
│   ├── documents/   # Documents and downloads
│   └── media/       # Media files (videos, audio)
```

### 🌐 SEO Management (NEW!)
- **Per-Tenant robots.txt** - Customizable robots.txt for each tenant
- **Dynamic Sitemaps** - Auto-generated and manageable sitemaps
- **SEO Commands** - Enable, disable, and manage SEO via CLI
- **Public Routes** - Automatic `/robots.txt` and `/sitemap.xml` serving
- **Disallow Paths** - Customizable paths to disallow in robots.txt

**Quick SEO Setup:**
```bash
# Enable SEO for a tenant
php artisan tenant:seo:enable --tenant=uuid

# Check SEO status
php artisan tenant:seo:status --all

# Add URL to sitemap
php artisan tenant:seo:generate-sitemap --add-url=/products --priority=0.9
```

### 📱 PWA Support (NEW!)
- **Per-Tenant PWA** - Each tenant can have its own Progressive Web App
- **Manifest Generation** - Auto-generate PWA manifests
- **Service Worker Support** - Custom service workers per tenant
- **Icon Management** - Separate PWA icons for each tenant
- **Offline Support** - Full PWA capabilities per tenant

### ⚡ Performance & Monitoring
- **Real-time Monitoring** - Built-in performance metrics and health checks
- **High Performance** - Optimized for 1000+ concurrent tenants (18ms avg response)
- **Comprehensive Testing** - 15+ specialized testing commands
- **Stress Testing** - Production-ready load testing
- **System Validation** - Automated health checks and repair tools

### 🔧 Developer Experience
- **Zero Configuration** - Works out of the box with sensible defaults
- **Rich CLI Commands** - 40+ artisan commands for tenant management
- **Helper Functions** - 7 global helper functions for assets and paths
- **One-Command Setup** - Install everything with `php artisan af-tenancy:install`
- **Comprehensive Docs** - Detailed documentation for all features

---

## 📦 Installation & Setup

### Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x or 12.x
- MySQL 5.7+ or MariaDB 10.3+
- Composer 2.x

### Step-by-Step Installation

1. **Install via Composer**
```bash
composer require artflow-studio/tenancy
```

2. **Run Installation Command**
```bash
php artisan af-tenancy:install
```

This will:
- ✅ Publish configuration files
- ✅ Install stancl/tenancy
- ✅ Create directory structure (including asset directories)
- ✅ Add tenant_template to database config
- ✅ Run migrations
- ✅ Create README files for tenant directories

3. **Configure Database Root Credentials** (Optional but Recommended)

Add to your `.env` file:
```bash
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=your_mysql_root_password
```

**Why Root Credentials?**
- Automatically creates tenant databases
- Grants proper MySQL privileges
- Enables FastPanel integration
- Required for production deployments

4. **Create Storage Symlink** (If not done already)
```bash
php artisan storage:link
```

5. **Configure Domains**

Edit `config/artflow-tenancy.php`:
```php
'central_domains' => [
    'localhost',
    'yourdomain.com',
],
```

---

## 🚀 Usage Guide

### Creating Tenants

**Interactive Mode:**
```bash
php artisan tenant:create
```

**Command Line:**
```bash
php artisan tenant:create \
    --name="Acme Corp" \
    --domain="acme.local" \
    --database="custom_db_name" \
    --homepage
```

### Asset Management

**Helper Functions in Blade:**
```blade
{{-- General assets --}}
<img src="{{ tenant_asset('images/logo.png') }}" alt="Logo">
<link href="{{ tenant_asset('css/custom.css') }}" rel="stylesheet">

{{-- PWA assets --}}
<link rel="manifest" href="{{ tenant_pwa_asset('manifest.json') }}">
<link rel="icon" href="{{ tenant_pwa_asset('icons/icon-192.png') }}">

{{-- SEO assets --}}
<link rel="sitemap" href="{{ tenant_seo_asset('sitemap.xml') }}">

{{-- Get paths in PHP --}}
@php
    $assetsPath = tenant_path('assets');
    $pwaPath = tenant_path('pwa');
@endphp
```

**Upload Assets:**
```php
use ArtflowStudio\Tenancy\Services\TenantAssetService;

$service = app(TenantAssetService::class);

// Upload a file
$service->uploadAsset($tenant, $uploadedFile, 'assets');

// Copy a file
$service->copyAsset($tenant, '/path/to/file.png', 'assets/images/file.png');

// Get folder size
$sizes = $service->getTenantFolderSize($tenant);
// Returns: ['total' => 1024000, 'assets' => 500000, 'pwa' => 200000, ...]
```

### SEO Management

**Enable SEO:**
```bash
# Single tenant
php artisan tenant:seo:enable --tenant=uuid

# All tenants
php artisan tenant:seo:enable --all

# With custom disallow paths
php artisan tenant:seo:enable --tenant=uuid --disallow=/private,/admin
```

**Manage Sitemap:**
```bash
# Add URLs to sitemap
php artisan tenant:seo:generate-sitemap \
    --tenant=uuid \
    --add-url=/products \
    --priority=0.9 \
    --changefreq=daily

# Regenerate sitemap
php artisan tenant:seo:generate-sitemap --all
```

**Check SEO Status:**
```bash
php artisan tenant:seo:status --all
```

**Disable SEO:**
```bash
php artisan tenant:seo:disable --tenant=uuid --remove-files
```

### PWA Management

```bash
# Enable PWA for tenant
php artisan tenant:pwa:enable --tenant=uuid

# Disable PWA
php artisan tenant:pwa:disable --tenant=uuid

# Check PWA status
php artisan tenant:pwa:status --all
```

### Tenant Management Commands

```bash
# List all tenants
php artisan tenant:list

# Update tenant
php artisan tenant:update {tenant_id}

# Delete tenant
php artisan tenant:delete {tenant_id}

# Run migrations for all tenants
php artisan tenants:migrate

# Run seeders for specific tenant
php artisan tenants:seed --tenant=uuid
```

---

## 🔍 Helper Functions Reference

### Asset Helpers

#### `tenant_asset($path, $tenant = null)`
Generate URL for tenant-specific asset.

```php
tenant_asset('images/logo.png')
// Output: http://yourdomain.com/storage/tenants/tenant1.local/assets/images/logo.png
```

#### `tenant_pwa_asset($path, $tenant = null)`
Generate URL for PWA asset.

```php
tenant_pwa_asset('manifest.json')
// Output: http://yourdomain.com/storage/tenants/tenant1.local/pwa/manifest.json
```

#### `tenant_seo_asset($path, $tenant = null)`
Generate URL for SEO asset.

```php
tenant_seo_asset('robots.txt')
// Output: http://yourdomain.com/storage/tenants/tenant1.local/seo/robots.txt
```

### Path Helpers

#### `tenant_path($subfolder = '', $tenant = null)`
Get absolute storage path for tenant.

```php
tenant_path('assets')
// Output: /var/www/storage/app/public/tenants/tenant1.local/assets
```

#### `tenant_url($subfolder = '', $tenant = null)`
Get public URL path for tenant.

```php
tenant_url('pwa')
// Output: /storage/tenants/tenant1.local/pwa
```

### Tenant Helpers

#### `current_tenant()`
Get current tenant instance.

```php
$tenant = current_tenant();
if ($tenant) {
    echo $tenant->name;
}
```

#### `tenant_config($key = null, $default = null)`
Get tenant configuration.

```php
$seoConfig = tenant_config('seo_config');
```

#### `tenant_domain_folder($domain)`
Get folder name for domain (exact domain, lowercased).

```php
tenant_domain_folder('Tenant1.Local')
// Output: tenant1.local
```

---

## 📚 Detailed Documentation

- **[SEO Management](./docs/SEO.md)** - Complete SEO guide with robots.txt and sitemap management
- **[PWA Support](./docs/PWA.md)** - Progressive Web App implementation per tenant
- **[Asset Management](./docs/ASSET-MANAGEMENT.md)** - File uploads, storage, and organization
- **[Commands Reference](./docs/COMMANDS.md)** - All available artisan commands
- **[API Reference](./docs/API.md)** - REST API for tenant management
- **[Testing Guide](./docs/TESTING.md)** - Performance and load testing
- **[Troubleshooting](./docs/TROUBLESHOOTING.md)** - Common issues and solutions

---

## 🎯 Advanced Features

### Custom Middleware

```php
// Apply tenant-specific middleware
Route::middleware(['tenant.web', 'tenant.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### Database Operations

```php
// Execute queries in tenant context
Tenant::find($tenantId)->run(function () {
    User::create(['name' => 'John Doe']);
});

// Switch database connection
tenancy()->initialize($tenant);
```

### Events and Listeners

```php
// Listen to tenant events
Event::listen(TenantCreated::class, function ($event) {
    // Send welcome email
    // Setup default data
    // Initialize services
});
```

---

## 🧪 Testing & Validation

### Run Tests

```bash
# Run all tests
php artisan af-tenancy:test-all

# Specific tests
php artisan tenant:test:performance
php artisan tenant:test:stress
php artisan tenant:test:database
```

### Health Checks

```bash
# System health check
php artisan tenant:health-check

# Performance monitoring
php artisan tenant:performance:monitor
```

---

## 🔒 Security

- Database credentials are encrypted
- Tenant isolation is enforced at database level
- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- Rate limiting on API endpoints

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

---

## 📄 License

The ArtFlow Studio Tenancy Package is open-sourced software licensed under the [MIT license](LICENSE.md).

---

## 🙏 Credits

Built on top of the excellent [stancl/tenancy](https://github.com/stancl/tenancy) package.

---

## 📞 Support

- **Documentation**: [Full Docs](./docs/)
- **Issues**: [GitHub Issues](https://github.com/artflow-studio/tenancy/issues)
- **Email**: support@artflowstudio.com

---

## 🗺️ Roadmap

- [ ] Multi-database support (PostgreSQL, SQLite)
- [ ] Tenant billing and subscription management
- [ ] Advanced analytics and reporting
- [ ] Tenant backup and restore
- [ ] Tenant cloning and templating
- [ ] Advanced caching strategies
- [ ] GraphQL API support

---

**Made with ❤️ by ArtFlow Studio**
