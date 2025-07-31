<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Tenancy Package Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the TenancyServiceProvider and will be 
| assigned to the "web" middleware group automatically.
|
*/

// Admin Routes (Central domain only)
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    // Tenant Dashboard
    Route::get('/dashboard', [TenantController::class, 'dashboard'])->name('dashboard');
    
    // API endpoints for live stats
    Route::get('/stats', [TenantController::class, 'stats']);
    Route::get('/live-stats', [TenantController::class, 'liveStats']);
    Route::get('/health', [TenantController::class, 'health']);
    Route::get('/performance', [TenantController::class, 'performance']);
    Route::get('/connections', [TenantController::class, 'connectionStats']);
    Route::get('/active-users', [TenantController::class, 'activeUsers']);
    
    // Cache management
    Route::post('/cache/reset', [TenantController::class, 'resetCache']);
    
    // Tenant Management (RESTful Resource)
    Route::resource('tenants', TenantController::class)->parameters(['tenants' => 'uuid']);
    
    // Additional Tenant Actions
    Route::post('/tenants/{uuid}/activate', [TenantController::class, 'activate']);
    Route::post('/tenants/{uuid}/suspend', [TenantController::class, 'suspend']);
    Route::post('/tenants/{uuid}/migrate', [TenantController::class, 'migrateTenant']);
    Route::post('/tenants/{uuid}/seed', [TenantController::class, 'seedTenant']);
    Route::post('/tenants/{uuid}/status', [TenantController::class, 'updateStatus']);
    Route::post('/tenants/{uuid}/reset', [TenantController::class, 'resetTenant']);
    Route::post('/tenants/{uuid}/domains', [TenantController::class, 'addDomain']);
    Route::delete('/domains/{domain}', [TenantController::class, 'removeDomain']);
    
    // Enhanced Admin Actions
    Route::post('/migrate-all-tenants', [TenantController::class, 'migrateAllTenants'])->name('migrate.all');
    Route::post('/clear-all-caches', [TenantController::class, 'clearAllCaches'])->name('cache.clear.all');
});
