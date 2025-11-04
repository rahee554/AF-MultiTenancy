# Provider Refactoring Guide

## Overview

This document outlines the refactoring that moved all tenancy-related service provider logic from the application into the `artflow-studio/tenancy` package. This centralizes tenancy configuration and makes the package more self-contained.

## What Was Moved

### 1. Tenant Lifecycle Events Management

**From:** `App\Providers\AppServiceProvider`

**Moved to:** `ArtflowStudio\Tenancy\TenancyServiceProvider::registerTenantEvents()`

**Includes:**
- Tenant creation event handler that sets up directories
- Tenant deletion event handler that cleans up directories
- Directory creation for public assets, PWA files, media, etc.
- Directory creation for private backups, logs, cache, uploads, etc.

**Methods moved:**
- `registerTenantEvents()` - Listens for Eloquent model events
- `createTenantDirectories()` - Creates directory structure for new tenants
- `deleteTenantDirectories()` - Cleans up directories when tenant is deleted

### 2. Stancl/Tenancy Event Listeners

**From:** `App\Providers\TenancyServiceProvider`

**Moved to:** `ArtflowStudio\Tenancy\TenancyServiceProvider::bootStanclTenancyEvents()`

**Includes:**
- Tenant creation job pipeline (CreateDatabase, MigrateDatabase)
- Tenant deletion job pipeline (DeleteDatabase)
- Domain events listeners
- Database events listeners
- Tenancy initialization and ending listeners
- Resource syncing listeners

**Methods moved:**
- `bootStanclTenancyEvents()` - Registers all stancl/tenancy events
- `getStanclTenancyEvents()` - Returns complete event mapping

### 3. Middleware Priority Configuration

**From:** `App\Providers\TenancyServiceProvider::makeTenancyMiddlewareHighestPriority()`

**Moved to:** `ArtflowStudio\Tenancy\TenancyServiceProvider::makeTenancyMiddlewareHighestPriority()`

**Ensures:**
- Tenancy middleware runs before other middleware
- Proper request initialization order
- Consistent tenancy context throughout the request lifecycle

**Middleware prioritized:**
- `PreventAccessFromCentralDomains`
- `InitializeTenancyByDomain`
- `InitializeTenancyBySubdomain`
- `InitializeTenancyByDomainOrSubdomain`
- `InitializeTenancyByPath`
- `InitializeTenancyByRequestData`

## Files Modified

### ✅ Deleted

- `app/Providers/TenancyServiceProvider.php` - No longer needed, all content moved to package

### ✅ Modified

#### `app/Providers/AppServiceProvider.php`

**Before:** 150+ lines with tenant management logic

**After:** ~30 lines with only application settings sharing

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }

    public function boot(): void
    {
        // Only app-specific configuration
        if (Schema::hasTable('settings')) {
            view()->share('appSettings', \App\Models\Setting::get());
        }
        Blaze::enable();
    }
}
```

#### `vendor/artflow-studio/tenancy/src/TenancyServiceProvider.php`

**Added:**
- Tenant event registration in `boot()` method
- Stancl/tenancy event bootstrapping in `boot()` method
- Middleware priority setup in `register()` method
- `registerTenantEvents()` method
- `createTenantDirectories()` method
- `deleteTenantDirectories()` method
- `bootStanclTenancyEvents()` method
- `getStanclTenancyEvents()` method
- `makeTenancyMiddlewareHighestPriority()` method

#### `bootstrap/providers.php`

**Before:**
```php
<?php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class, // ❌ Removed
];
```

**After:**
```php
<?php
return [
    App\Providers\AppServiceProvider::class,
];
```

The `artflow-studio/tenancy` package is automatically registered by Laravel as a package service provider.

## How It Works

### Initialization Flow

```
Application Bootstrap
├─ bootstrap/providers.php loads
│  └─ App\Providers\AppServiceProvider (only one provider)
│
├─ Laravel auto-discovers package service providers
│  └─ ArtflowStudio\Tenancy\TenancyServiceProvider (auto-loaded)
│     ├─ register() phase:
│     │  ├─ Registers stancl/tenancy provider
│     │  ├─ Registers core services
│     │  ├─ Makes tenancy middleware highest priority
│     │  └─ Registers database managers
│     │
│     └─ boot() phase:
│        ├─ Registers tenant lifecycle events
│        ├─ Boots stancl/tenancy event listeners
│        ├─ Registers middleware groups
│        ├─ Configures Livewire
│        └─ Loads routes, views, migrations
│
└─ Application ready
   ├─ Tenant events listen to Eloquent events
   ├─ Stancl/tenancy events listen to tenancy events
   └─ Middleware stack properly prioritized
```

## Tenant Lifecycle

### Creating a Tenant

```
User creates tenant
     ↓
Tenant model saved
     ↓
Eloquent fired 'created' event
     ↓
TenancyServiceProvider::registerTenantEvents() listener triggered
     ↓
TenancyServiceProvider::createTenantDirectories() runs
     ↓
Directories created for:
├─ storage/app/public/tenants/{domain}
│  ├─ assets/
│  ├─ pwa/
│  ├─ pwa/icons/
│  ├─ seo/
│  ├─ documents/
│  └─ media/
└─ storage/app/private/tenants/{domain}
   ├─ backups/
   ├─ logs/
   ├─ cache/
   ├─ temp/
   ├─ documents/
   ├─ uploads/
   └─ config/
```

### Deleting a Tenant

```
User deletes tenant
     ↓
Tenant model deleted
     ↓
Eloquent fired 'deleted' event
     ↓
TenancyServiceProvider::registerTenantEvents() listener triggered
     ↓
TenancyServiceProvider::deleteTenantDirectories() runs
     ↓
Directories deleted for:
├─ storage/app/public/tenants/{domain}/
└─ storage/app/private/tenants/{domain}/
```

## Benefits

✅ **Centralized Configuration** - All tenancy logic in one package

✅ **Cleaner Application** - Removed 150+ lines from app/Providers

✅ **Self-Contained Package** - Package now manages all its initialization

✅ **Easier Upgrades** - Update package without modifying application providers

✅ **Better Maintainability** - Tenant logic isolated in package

✅ **Consistent Behavior** - Same initialization across all applications using the package

## Migration Path

For existing applications:

1. **No action needed** - The changes are backward compatible
2. **Optional:** Delete `app/Providers/TenancyServiceProvider.php` from your app (it's no longer used)
3. **Optional:** Update `bootstrap/providers.php` to remove the TenancyServiceProvider reference
4. Clear application cache: `php artisan optimize:clear`

## Verification

After moving providers, verify:

```bash
# Check that providers are registered correctly
php artisan config:list | grep tenancy

# Test tenant creation
php artisan tenant:create --name="test-tenant"

# Test authentication on tenant domain
# Navigate to http://test-tenant.local/login and verify login works

# Run tests
php artisan test
```

## Troubleshooting

### Issue: "Class App\Providers\TenancyServiceProvider not found"

**Solution:** Delete the file or remove it from `bootstrap/providers.php`. The package provider is auto-discovered.

### Issue: Tenant directories not created

**Solution:** Ensure the events are being fired:
```bash
php artisan tinker
>>> event(new Eloquent\TenantCreated($tenant));
```

### Issue: Livewire not working on tenant

**Solution:** Clear cache:
```bash
php artisan optimize:clear
```

## Documentation Files

Related documentation:
- `QUICK_START_GUIDE.md` - Installation and setup
- `SESSION_AND_CACHE_ISOLATION.md` - How sessions/cache are isolated
- `LIVEWIRE_MULTITENANT_LOGIN_FIX.md` - Livewire integration details

---

**Status:** ✅ Refactoring Complete

**Version:** v1.0

**Last Updated:** November 2025
