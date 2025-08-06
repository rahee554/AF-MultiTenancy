# Middleware Usage Guide

This package works **ON TOP OF** `stancl/tenancy` and follows its routing conventions. Understanding the middleware groups is crucial for proper authentication and routing.

## The Central vs Tenant Domain Problem

Your issue: "central domain and tenant domain both uses the LOGIN page so when using tenant.web it cannot be used central on the same"

**This is BY DESIGN in stancl/tenancy!** Here's why:

- **Central domains** (like `localhost`, `admin.yoursite.com`) are for admin/management
- **Tenant domains** (like `tenant1.yoursite.com`, `tenant2.yoursite.com`) are for the actual tenant applications
- **They should NOT share the same login page** - they serve different purposes

## Correct Middleware Usage

### For Central Domain Routes (Admin/Management)

```php
// In your main Laravel app's routes/web.php or routes/api.php
Route::middleware(['central.web'])->group(function () {
    // These routes work ONLY on central domains
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    
    Route::get('admin/dashboard', [AdminController::class, 'index'])
        ->middleware('auth')  // Standard Laravel auth
        ->name('admin.dashboard');
});
```

### For Tenant Domain Routes (Tenant Applications)

```php
// In your main Laravel app's routes/tenant.php (stancl/tenancy convention)
Route::middleware(['tenant.web'])->group(function () {
    // These routes work ONLY on tenant domains (tenant1.yoursite.com, etc.)
    Route::get('login', [TenantLoginController::class, 'showLoginForm'])->name('tenant.login');
    Route::post('login', [TenantLoginController::class, 'login']);
    
    Route::get('dashboard', [TenantDashboardController::class, 'index'])
        ->middleware('auth')  // Tenant-scoped auth
        ->name('tenant.dashboard');
});
```

## Available Middleware Groups

| Middleware Group | Purpose | Available On | Session Scoping |
|------------------|---------|--------------|-----------------|
| `central.web` | Central domain web routes | Central domains only | Standard Laravel sessions |
| `tenant.web` | Tenant domain web routes | Tenant domains only | Tenant-scoped sessions |
| `tenant.api` | Tenant domain API routes | Tenant domains only | No sessions (API) |
| `tenant.auth.web` | Tenant routes with auth logging | Tenant domains only | Tenant-scoped + logging |

## Common Routing Patterns

### 1. Separate Login Systems (Recommended)

**Central Domain Login** (for admin/management):
```php
// routes/web.php
Route::middleware(['central.web'])->group(function () {
    Route::get('admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('admin/login', [AdminAuthController::class, 'login']);
    
    Route::middleware('auth:admin')->group(function () {
        Route::get('admin/dashboard', [AdminController::class, 'dashboard']);
        Route::get('admin/tenants', [TenantManagementController::class, 'index']);
    });
});
```

**Tenant Domain Login** (for tenant users):
```php
// routes/tenant.php  
Route::middleware(['tenant.web'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [TenantAuthController::class, 'login']);
    
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [TenantController::class, 'dashboard']);
        Route::get('profile', [TenantProfileController::class, 'show']);
    });
});
```

### 2. Shared Controllers with Different Guards

You can use the same controller for both if you use different authentication guards:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session', 
        'provider' => 'admins',
    ],
    'tenant' => [
        'driver' => 'session',
        'provider' => 'tenant_users',
    ],
],
```

## Troubleshooting Common Issues

### Issue: "Route not found" errors

**Problem**: Using wrong middleware group for the domain type.

**Solution**: 
- Use `central.web` for routes accessed on `localhost`, `admin.yoursite.com`
- Use `tenant.web` for routes accessed on `tenant1.yoursite.com`, `tenant2.yoursite.com`

### Issue: Sessions not working properly with Livewire

**Problem**: Session data shared between tenants.

**Solution**: Always use `tenant.web` (includes session scoping) for tenant routes with Livewire:

```php
Route::middleware(['tenant.web'])->group(function () {
    // Livewire routes here - sessions will be properly scoped
});
```

### Issue: Authentication redirects to wrong domain

**Problem**: Laravel's default auth redirects don't understand tenancy.

**Solution**: Create separate auth controllers or override redirect logic:

```php
// In your TenantAuthController
protected function redirectTo()
{
    return tenant_route('dashboard');  // Custom helper for tenant routes
}
```

## Best Practices

1. **Never mix central and tenant middleware** in the same route group
2. **Use separate controllers** for central vs tenant authentication when possible
3. **Always use `tenant.web`** for Livewire components on tenant domains
4. **Test both central and tenant domains** during development
5. **Use different authentication guards** for central vs tenant users

## Example: Complete Setup

### Central Domain (admin.yoursite.com)
```php
// routes/web.php
Route::domain('admin.yoursite.com')->middleware(['central.web'])->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login']);
    
    Route::middleware('auth:admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.home');
        Route::resource('tenants', TenantManagementController::class);
    });
});
```

### Tenant Domains (*.yoursite.com)
```php
// routes/tenant.php
Route::middleware(['tenant.web'])->group(function () {
    Route::get('/', [TenantHomeController::class, 'index'])->name('tenant.home');
    Route::get('login', [TenantAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [TenantAuthController::class, 'login']);
    
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');
        
        // Livewire components
        Route::get('profile', TenantProfileComponent::class)->name('profile');
    });
});
```

This way you have:
- **Central domain**: Admin panel for managing tenants (`admin.yoursite.com/login`)
- **Tenant domains**: Individual tenant applications (`tenant1.yoursite.com/login`, `tenant2.yoursite.com/login`)
- **Proper session isolation**: Each tenant has its own session scope
- **No conflicts**: Each domain type has its own routes and middleware
