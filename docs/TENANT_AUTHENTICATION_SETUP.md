# Tenant Authentication Setup Guide

## Issue: Authentication Routes Not Tenant-Aware

### Problem Description
When accessing `tenancy1.local/login`, users are authenticated against the main database instead of the tenant-specific database. This happens because the `auth.php` routes are not wrapped with tenant middleware.

### Solution: Smart Tenant Middleware Integration

## Step 1: Update Your Application's Route Files

### Option A: Apply Smart Tenant Middleware to Auth Routes (Recommended)

Update your `routes/web.php` to apply tenant middleware to auth routes:

```php
<?php
// routes/web.php

use Illuminate\Support\Facades\Route;

// Apply smart tenant middleware to auth routes
Route::middleware(['smart.tenant'])->group(function () {
    require base_path('routes/auth.php');
});

// Your existing routes with tenant middleware
Route::middleware(['smart.tenant', 'auth'])->group(function () {
    // Your tenant-specific authenticated routes here
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Add other authenticated routes
});

// Central/admin routes (without tenant middleware)
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Admin routes that should use central database
});
```

### Option B: Modify Auth Routes Directly

Update your `routes/auth.php` to include tenant middleware:

```php
<?php
// routes/auth.php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Auth\ConfirmPassword;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Route;

// Guest routes with tenant context
Route::middleware(['smart.tenant', 'guest'])->group(function () {
    Route::get('login', Login::class)->name('login');   
    Route::get('register', Register::class)->name('register');
    Route::get('forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Authenticated routes with tenant context
Route::middleware(['smart.tenant', 'auth'])->group(function () {
    Route::get('verify-email', VerifyEmail::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('confirm-password', ConfirmPassword::class)
        ->name('password.confirm');
});
```

## Step 2: Update Your Tenant Routes

Ensure your `routes/tenant.php` is properly configured:

```php
<?php
// routes/tenant.php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are automatically wrapped with tenant middleware
| No need to manually apply tenant middleware here
|
*/

Route::middleware(['web'])->group(function () {
    // Tenant-specific homepage
    Route::get('/', function () {
        return view('tenant.dashboard');
    })->name('tenant.home');
    
    // Include auth routes for this tenant
    require base_path('routes/auth.php');
    
    // Other tenant-specific routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', function () {
            $tenant = tenant();
            return view('dashboard', compact('tenant'));
        })->name('dashboard');
        
        // Add your tenant-specific authenticated routes here
    });
});
```

## Step 3: Configure User Model for Tenant Context

Ensure your User model works correctly in tenant context:

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id', // Add if you want to track which tenant the user belongs to
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    /**
     * Get the tenant this user belongs to (optional)
     */
    public function tenant()
    {
        return $this->belongsTo(\ArtflowStudio\Tenancy\Models\Tenant::class);
    }
}
```

## Step 4: Update Authentication Configuration

Update your `config/auth.php` if needed:

```php
<?php
// config/auth.php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        // Add tenant-specific guard if needed
        'tenant' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    // ... rest of auth config
];
```

## Step 5: Test the Authentication Flow

### Test Commands

```bash
# 1. Validate the system
php artisan tenancy:validate

# 2. Test tenant connections
php artisan tenancy:test-connections

# 3. Test tenant isolation (important for auth)
php artisan tenancy:test-isolation --tenants=2 --operations=5
```

### Manual Testing Steps

1. **Access tenant domain**: Go to `http://tenancy1.local/login`
2. **Check database context**: The login form should connect to tenant database
3. **Register a user**: Create a user account - it should be stored in tenant database
4. **Login test**: Login should authenticate against tenant database
5. **Session test**: User session should be tenant-specific

### Verification Queries

```sql
-- Check which database the user was created in
-- Run this in your tenant database (tenant_tenancy1)
SELECT * FROM users WHERE email = 'test@example.com';

-- This should return the user if created in tenant context
-- Should be empty in central database if working correctly
```

## Step 6: Troubleshooting Common Issues

### Issue: Assets Not Loading
```php
// Ensure assets are excluded from tenant middleware
// The SmartTenancyInitializer should handle this automatically
// Assets like CSS, JS, images should load without tenant context
```

### Issue: Livewire Components Not Working
```php
// Add Livewire routes to exclusion list if needed
// This is handled automatically by SmartTenancyInitializer
```

### Issue: API Routes Affected
```php
// API routes are automatically excluded
// If you need tenant-aware API, use 'tenancy.api' middleware group
Route::middleware(['tenancy.api'])->prefix('api/tenant')->group(function () {
    // Tenant-specific API routes
});
```

## Expected Results After Implementation

✅ **Tenant Login**: `tenancy1.local/login` authenticates against tenant database  
✅ **Asset Loading**: CSS, JS, images load correctly without middleware interference  
✅ **Session Isolation**: Each tenant has isolated user sessions  
✅ **Performance**: No impact on asset loading or static file serving  

## Verification Commands

```bash
# Comprehensive system test
php artisan tenancy:validate

# Test authentication flow specifically
php artisan tenancy:test-isolation --detailed --tenants=2

# Performance test with auth routes
php artisan tenancy:test-performance-enhanced --concurrent-users=3 --skip-deep-tests
```

Your authentication should now work properly with tenant context while maintaining optimal performance for assets and static files.
