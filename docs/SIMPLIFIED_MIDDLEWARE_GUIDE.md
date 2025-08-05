# Simplified Tenant Middleware Usage Guide

## Overview
The package now provides a simplified `tenant` middleware that handles all tenant initialization, domain validation, and status checking in one place.

## Basic Usage

### In your routes/web.php:
```php
<?php

use Illuminate\Support\Facades\Route;

// Apply tenant middleware to all tenant routes
Route::middleware(['tenant'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    });
    
    // All your tenant routes here...
});
```

### Alternative syntax:
```php
// Apply to specific routes
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('tenant');

// Apply to route groups
Route::prefix('admin')->middleware('tenant')->group(function () {
    // Admin routes
});
```

## What the Tenant Middleware Does

The `tenant` middleware automatically:
1. **Initializes Tenancy** - Sets up the tenant context based on domain
2. **Validates Domain Access** - Prevents access from central domains
3. **Checks Tenant Status** - Verifies if tenant is active
4. **Handles Inactive Tenants** - Shows custom error page for inactive tenants

## Tenant Status Checking

If your `tenants` table has a `status` column, the middleware will automatically check it:

### Supported Status Values:
- `active` - Tenant is fully operational (default)
- `inactive` - Tenant is temporarily disabled
- `suspended` - Tenant account is suspended
- `maintenance` - Tenant is under maintenance

### Adding Status Column (Optional):
```php
// In a migration file
Schema::table('tenants', function (Blueprint $table) {
    $table->string('status')->default('active');
});
```

## Error Pages

When a tenant is inactive, users will see a professional error page with:
- Clear explanation of the issue
- Current tenant status
- Contact information guidance
- Responsive design

## Legacy Compatibility

The following middleware aliases are still available for backward compatibility:
- `smart.tenant` - Points to the new simplified middleware
- `tenant.auth` - Points to the old authentication middleware

## Migration from Old Middleware

### Before (Complex):
```php
Route::middleware(['tenant.init', 'tenant.prevent', 'tenant.auth'])->group(function () {
    // Routes
});
```

### After (Simple):
```php
Route::middleware(['tenant'])->group(function () {
    // Routes
});
```

## Troubleshooting

### Middleware Not Found Error:
If you get "middleware not found" error:
1. Clear route cache: `php artisan route:clear`
2. Clear config cache: `php artisan config:clear`
3. Clear application cache: `php artisan cache:clear`

### Custom Error Pages:
The error page is built into the middleware. If you need custom error pages, you can:
1. Override the `SimpleTenantMiddleware` class
2. Implement your own `renderInactiveTenantPage()` method

## Performance Notes

The simplified middleware:
- ✅ Combines multiple middleware into one (better performance)
- ✅ Only checks tenant status when needed
- ✅ Uses efficient HTML generation for error pages
- ✅ No additional database queries beyond standard tenancy

## Version Information

This simplified middleware system was introduced in version 0.7.0.3+ of the artflow-studio/tenancy package.
