# 🎯 Quick Migration Guide

## Problem: "smart.tenant middleware not found"

If you're getting this error, here's the solution:

## ✅ SOLUTION: Use the new simplified middleware

### 1. Update your routes/web.php:

**Replace this:**
```php
Route::middleware(['smart.tenant'])->group(function () {
    // Your routes
});
```

**With this:**
```php
Route::middleware(['tenant'])->group(function () {
    // Your routes
});
```

### 2. Clear Laravel caches:
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### 3. Test the middleware:
```bash
php artisan af-tenancy:test-middleware
```

You should see: `🎉 SUCCESS: All 7/7 middleware registered correctly!`

## 🔍 What Changed?

- **Old System**: Complex middleware chains with confusing names
- **New System**: Simple `tenant` middleware that does everything
- **Benefit**: One middleware handles all tenant initialization, validation, and status checking

## 🛡️ Tenant Status Protection

The new middleware automatically checks if your tenant is active:

```php
// If you have a 'status' column in your tenants table:
Schema::table('tenants', function (Blueprint $table) {
    $table->string('status')->default('active');
});
```

**Status values:**
- `active` - Normal operation ✅
- `inactive` - Shows professional error page 🚫
- `suspended` - Shows suspension notice ⚠️
- `maintenance` - Shows maintenance message 🔧

## 📱 Complete Example

Here's how your `routes/web.php` should look:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;

// Central domain routes (no middleware needed)
Route::get('/', function () {
    return view('welcome');
});

// Tenant routes (use simple 'tenant' middleware)
Route::middleware(['tenant'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])
         ->name('dashboard');
    
    Route::resource('customers', CustomerController::class);
    
    Route::get('/profile', function () {
        $tenant = tenant(); // Get current tenant
        return view('profile', compact('tenant'));
    });
    
    // All your other tenant routes...
});
```

## 🚀 That's it!

Your middleware is now simplified and includes:
- ✅ Tenant initialization
- ✅ Domain validation  
- ✅ Authentication context
- ✅ Status checking
- ✅ Professional error pages

**Version:** 0.7.0.4+
