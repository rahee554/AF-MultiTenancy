<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Smart Domain Routes Example
|--------------------------------------------------------------------------
|
| This example shows how to use the new 'central.tenant.web' middleware
| to create routes that work intelligently on BOTH central and tenant domains.
|
| Central domains: localhost, admin.yoursite.com
| Tenant domains: tenant1.yoursite.com, tenant2.yoursite.com, etc.
|
*/

// ========================================
// SMART ROUTES - Work on BOTH central and tenant domains
// ========================================

Route::middleware(['central.tenant.web'])->group(function () {
    
    // ✨ Authentication routes that work everywhere
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
    
    // Password reset routes
    Route::get('password/reset', [AuthController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('password/email', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('password/reset/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [AuthController::class, 'reset'])->name('password.update');
    
    // ✨ Protected routes that work on both domain types
    Route::middleware(['auth'])->group(function () {
        
        // Main dashboard - shows different content based on domain type
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        
        // User profile management
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        
        // Settings that adapt based on context
        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        
        // Notification preferences
        Route::get('notifications', [SettingsController::class, 'notifications'])->name('notifications');
        Route::put('notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');
        
    });
    
    // ✨ API routes that work on both domain types
    Route::prefix('api')->group(function () {
        
        // Public API endpoints
        Route::get('health', function () {
            return response()->json([
                'status' => 'ok',
                'domain_type' => request()->attributes->get('domain_type'),
                'timestamp' => now(),
            ]);
        });
        
        // Authenticated API endpoints
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('user', function () {
                return response()->json(auth()->user());
            });
            
            Route::get('stats', [DashboardController::class, 'stats']);
        });
    });
});

// ========================================
// DOMAIN-SPECIFIC ROUTES (Keep these separate)
// ========================================

// Central domain ONLY routes (admin/management features)
Route::middleware(['central.web'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('system-settings', [SettingsController::class, 'systemSettings'])->name('system-settings');
        Route::get('tenant-management', [AdminController::class, 'tenants'])->name('tenants');
        Route::get('system-logs', [AdminController::class, 'logs'])->name('logs');
    });
});

// Tenant domain ONLY routes (tenant-specific features)
Route::middleware(['tenant.web'])->group(function () {
    Route::prefix('tenant')->name('tenant.')->group(function () {
        Route::get('billing', [TenantController::class, 'billing'])->name('billing');
        Route::get('team', [TenantController::class, 'team'])->name('team');
        Route::get('integration', [TenantController::class, 'integrations'])->name('integrations');
    });
});

/*
|--------------------------------------------------------------------------
| How This Works:
|--------------------------------------------------------------------------
|
| 1. SMART ROUTES (central.tenant.web):
|    - Same URL works on both domain types: /login, /dashboard, /profile
|    - Automatic context detection in controllers and views
|    - Proper session scoping (standard on central, tenant-scoped on tenant)
|
| 2. CENTRAL ONLY ROUTES (central.web):
|    - Admin features that should never be on tenant domains
|    - System management, tenant oversight, global settings
|
| 3. TENANT ONLY ROUTES (tenant.web):
|    - Features specific to tenant applications
|    - Team management, billing, integrations
|
| Example Behavior:
| - http://localhost/login → Central login (admin users)
| - http://tenant1.yoursite.com/login → Tenant login (tenant1 users)
| - http://localhost/admin/tenants → ✅ Works (central only)
| - http://tenant1.yoursite.com/admin/tenants → ❌ Blocked (central only)
| - http://tenant1.yoursite.com/tenant/billing → ✅ Works (tenant only)
| - http://localhost/tenant/billing → ❌ Blocked (tenant only)
|
*/
