# 📦 AF-MultiTenancy Installation Guide

**Built on top of stancl/tenancy for Laravel**

## 🎯 Prerequisites

- Laravel 9.0+ (recommended: Laravel 10+)
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.4+
- Composer 2.0+

## 🚀 Quick Installation

### Step 1: Install the Package

```bash
composer require artflow-studio/tenancy
```

**What this installs:**
- AF-MultiTenancy package with enhancements
- stancl/tenancy 3.7+ (automatically installed as dependency)
- All necessary dependencies

### Step 2: Run Installation Command

```bash
php artisan af-tenancy:install
```

**What this command does:**
1. Publishes stancl/tenancy configuration (`config/tenancy.php`)
2. Publishes AF-MultiTenancy enhancements (`config/artflow-tenancy.php`)
3. Runs database migrations for tenants and domains tables
4. Updates `.env` file with necessary variables
5. Configures Livewire for multi-tenancy (if installed)

### Step 3: Test the Installation

```bash
php artisan af-tenancy:test-all
```

**This comprehensive test will check:**
- ✅ stancl/tenancy core functionality
- ✅ AF-MultiTenancy enhancements
- ✅ Database connections and tables
- ✅ Middleware registration
- ✅ Service bindings
- ✅ Livewire integration (if installed)
- ✅ Tenant creation and isolation

## 🏗️ Architecture Overview

```
Your Laravel App
       ↓
AF-MultiTenancy (enhancements)
       ↓  
stancl/tenancy 3.7+ (core functionality)
       ↓
Laravel Framework
```

## 📋 Configuration Files

After installation, you'll have:

### `config/tenancy.php` (stancl/tenancy core)
```php
<?php
return [
    'tenant_model' => \ArtflowStudio\Tenancy\Models\Tenant::class,
    'domain_model' => \ArtflowStudio\Tenancy\Models\Domain::class,
    
    // Uses stancl's proven database managers
    'database' => [
        'managers' => [
            'mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
        ],
    ],
    
    // All stancl/tenancy bootstrappers
    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        // ... and more
    ],
];
```

### `config/artflow-tenancy.php` (our enhancements)
```php
<?php
return [
    // Status management
    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive', 
        'suspended' => 'Suspended',
        'blocked' => 'Blocked',
    ],
    
    // Homepage functionality
    'homepage' => [
        'enabled' => true,
        'fallback_redirect' => '/login',
    ],
    
    // API configuration
    'api' => [
        'enabled' => true,
        'auth_required' => true,
    ],
];
```

## 🔗 Routes Configuration

Create or update your `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// Central domain routes (your main app)
Route::middleware(['central.web'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
    
    // Your central app routes here
});

// Tenant routes (multi-tenant functionality)
Route::middleware(['tenant.web'])->group(function () {
    Route::get('/', function () {
        $tenant = request()->tenant;
        return "Welcome to {$tenant->name}!";
    });
    
    // Your tenant-specific routes here
    require base_path('routes/tenant.php');
});
```

### Create `routes/tenant.php`:
```php
<?php

use Illuminate\Support\Facades\Route;

// These routes run in tenant context
Route::get('/dashboard', function () {
    $tenant = tenant(); // stancl/tenancy helper
    return view('tenant.dashboard', compact('tenant'));
});

Route::get('/users', function () {
    // This will query the tenant's database
    $users = \App\Models\User::all(); 
    return view('tenant.users', compact('users'));
});
```

## 🧪 Testing Your Setup

### 1. Create Your First Tenant

```bash
php artisan tenant:manage create
```

Follow the prompts to create a tenant with:
- Name: "My First Tenant"
- Domain: "tenant1.localhost" 
- Status: "active"

### 2. Test Tenant Access

Add to your `/etc/hosts` (Linux/Mac) or `C:\Windows\System32\drivers\etc\hosts` (Windows):
```
127.0.0.1 tenant1.localhost
```

Then visit: `http://tenant1.localhost:8000`

### 3. Run Comprehensive Tests

```bash
# Quick test
php artisan af-tenancy:test-all --quick

# Full test with auto-fix
php artisan af-tenancy:test-all --fix --verbose
```

## 🔧 Advanced Configuration

### Environment Variables

Add to your `.env` file:

```env
# Tenant Database Configuration
TENANT_DB_PREFIX=tenant_
TENANT_DB_CONNECTION=mysql

# Central Domains (add your main domains)
APP_DOMAIN=yourdomain.com

# Homepage Management
TENANT_HOMEPAGE_ENABLED=true

# API Security (optional)
TENANT_API_KEY=your-secure-api-key

# Livewire (if using)
LIVEWIRE_ASSET_URL=/livewire
```

### Livewire Integration (Optional)

If you're using Livewire:

```bash
composer require livewire/livewire
```

The package automatically configures Livewire for tenancy. Your Livewire components will work seamlessly across tenants with proper session isolation.

```php
// Livewire component example
class TenantDashboard extends Component
{
    public function render()
    {
        // Automatically uses tenant's database
        $stats = [
            'users' => \App\Models\User::count(),
            'orders' => \App\Models\Order::count(),
        ];
        
        return view('livewire.tenant-dashboard', compact('stats'));
    }
}
```

## 🔍 Troubleshooting

### Common Issues

**Issue: "Class 'Stancl\Tenancy\TenancyServiceProvider' not found"**
```bash
# Solution: Install stancl/tenancy manually first
composer require stancl/tenancy
php artisan af-tenancy:install
```

**Issue: "Tenant not found" errors**
```bash
# Run the test to diagnose
php artisan af-tenancy:test-all --verbose

# Check domain configuration
cat config/tenancy.php | grep -A5 central_domains
```

**Issue: Database connection errors**
```bash
# Test database connections
php artisan tenancy:health

# Check database configuration
cat .env | grep DB_
```

**Issue: Livewire session mismatches**
```bash
# Our package fixes this automatically, but you can verify:
php artisan af-tenancy:test-all | grep -i livewire
```

### Getting Help

1. **Run diagnostics**: `php artisan af-tenancy:test-all --verbose`
2. **Check logs**: `tail -f storage/logs/laravel.log`
3. **View system status**: `php artisan tenancy:health`

## 📚 Next Steps

After installation:

1. **Create tenants**: `php artisan tenant:manage create`
2. **Set up your tenant routes** in `routes/tenant.php`
3. **Configure your models** for tenant isolation
4. **Test thoroughly** with `php artisan af-tenancy:test-all`

## 🎯 What You Get

✅ **Complete tenant isolation** - Each tenant gets its own database
✅ **Status management** - Active, inactive, suspended, blocked tenants  
✅ **Homepage functionality** - Per-tenant homepage management
✅ **Livewire compatibility** - Session fixes for multi-tenant Livewire
✅ **Admin interface** - Web and API management tools
✅ **CLI tools** - Comprehensive command-line management
✅ **Battle-tested foundation** - Built on proven stancl/tenancy

Your multi-tenant Laravel application is now ready! 🚀
