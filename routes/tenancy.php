<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Http\Controllers\TenantViewController;
use ArtflowStudio\Tenancy\Http\Controllers\TenantApiController;
use ArtflowStudio\Tenancy\Http\Controllers\RealTimeMonitoringController;

/*
|--------------------------------------------------------------------------
| Artflow Studio Tenancy Routes
|--------------------------------------------------------------------------
|
| This file contains all routes for the Artflow Studio Tenancy package.
| It includes both admin web interface routes and API routes.
|
*/

// ========================================
// CENTRAL DOMAIN ROUTES (Admin Interface)
// ========================================

Route::middleware(['web'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [TenantViewController::class, 'dashboard'])->name('dashboard');
    
    // Tenant CRUD
    Route::resource('tenants', TenantViewController::class);
    
    // Tenant Operations
    Route::post('/tenants/{id}/activate', [TenantViewController::class, 'activate']);
    Route::post('/tenants/{id}/block', [TenantViewController::class, 'block']);
    Route::post('/tenants/{id}/migrate', [TenantViewController::class, 'migrateTenant']);
    Route::post('/tenants/{id}/seed', [TenantViewController::class, 'seedTenant']);
    Route::post('/tenants/{id}/status', [TenantViewController::class, 'updateStatus']);
    Route::post('/tenants/{id}/reset', [TenantViewController::class, 'resetTenant']);
    Route::post('/tenants/{id}/domains', [TenantViewController::class, 'addDomain']);
    Route::delete('/domains/{domain}', [TenantViewController::class, 'removeDomain']);
    
    // System Operations
    Route::post('/migrate-all-tenants', [TenantViewController::class, 'migrateAllTenants'])->name('migrate.all');
    Route::post('/clear-all-caches', [TenantViewController::class, 'clearAllCaches'])->name('cache.clear.all');
    
    // Real-time Monitoring
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/dashboard', [RealTimeMonitoringController::class, 'getDashboardData'])->name('dashboard');
        Route::get('/system-stats', [RealTimeMonitoringController::class, 'getSystemStats'])->name('system');
        Route::get('/tenant-stats/{tenantId?}', [RealTimeMonitoringController::class, 'getTenantStats'])->name('tenants');
        Route::get('/connections', [RealTimeMonitoringController::class, 'getConnectionStats'])->name('connections');
        Route::delete('/clear-caches', [RealTimeMonitoringController::class, 'clearCaches'])->name('clear.caches');
        
        // Legacy monitoring routes
        Route::get('/tenant-activity', [TenantViewController::class, 'tenantActivity'])->name('tenant.activity');
        Route::get('/resource-usage', [TenantViewController::class, 'resourceUsage'])->name('resource.usage');
        Route::get('/database-analytics', [TenantViewController::class, 'databaseAnalytics'])->name('database.analytics');
        Route::get('/security', [TenantViewController::class, 'securityMonitoring'])->name('security');
        Route::get('/alerts', [TenantViewController::class, 'systemAlerts'])->name('alerts');
    });
    
    // Cache & Reset Operations
    Route::post('/cache/reset', [TenantViewController::class, 'resetCache']);
});

// ========================================
// TENANT DOMAIN ROUTES (Business Interface)
// ========================================

Route::middleware(['web', 'tenant', 'auth'])->group(function () {
    // Tenant Dashboard
    Route::get('/dashboard', function () {
        return view('business.dashboard');
    })->name('business.dashboard');
    
    // Add your business routes here
    // These routes will automatically work on tenant domains
});

// ========================================
// API ROUTES (External Integration)
// ========================================

// Protected API routes (require TENANT_API_KEY via X-API-Key header)
Route::middleware(['tenancy.api'])->prefix('tenancy')->name('tenancy.')->group(function () {
    // Core CRUD Operations
    Route::get('/tenants', [TenantApiController::class, 'apiIndex'])->name('api.tenants.index');
    Route::post('/tenants/create', [TenantApiController::class, 'apiStore'])->name('api.tenants.store');
    Route::get('/tenants/{uuid}', [TenantApiController::class, 'apiShow'])->name('api.tenants.show');
    Route::put('/tenants/{uuid}', [TenantApiController::class, 'apiUpdate'])->name('api.tenants.update');
    Route::delete('/tenants/{uuid}', [TenantApiController::class, 'apiDestroy'])->name('api.tenants.destroy');

    // System Monitoring
    Route::get('/dashboard', [TenantApiController::class, 'apiDashboard'])->name('api.dashboard');
    Route::get('/stats', [TenantApiController::class, 'apiStats'])->name('api.stats');
    Route::get('/live-stats', [TenantApiController::class, 'apiLiveStats'])->name('api.live-stats');
    Route::get('/health', [TenantApiController::class, 'apiHealth'])->name('api.health');
    Route::get('/performance', [TenantApiController::class, 'apiPerformance'])->name('api.performance');
    Route::get('/connection-stats', [TenantApiController::class, 'apiConnectionStats'])->name('api.connection-stats');
    Route::get('/active-users', [TenantApiController::class, 'apiActiveUsers'])->name('api.active-users');

    // Tenant Management Operations
    Route::post('/tenants/{uuid}/block', [TenantApiController::class, 'apiBlock'])->name('api.tenants.block');
    Route::put('/tenants/{uuid}/status', [TenantApiController::class, 'apiUpdateStatus'])->name('api.tenants.status');
    Route::post('/tenants/{uuid}/reset', [TenantApiController::class, 'apiReset'])->name('api.tenants.reset');

    // Domain Management
    Route::get('/tenants/{uuid}/domains', [TenantApiController::class, 'apiGetDomains'])->name('api.tenants.domains.list');
    Route::post('/tenants/{uuid}/domains/create', [TenantApiController::class, 'apiAddDomain'])->name('api.tenants.domains.create');
    Route::delete('/tenants/{uuid}/domains/{domainId}', [TenantApiController::class, 'apiRemoveDomain'])->name('api.tenants.domains.delete');

    // Database Operations
    Route::post('/tenants/{uuid}/migrate', [TenantApiController::class, 'apiMigrate'])->name('api.tenants.migrate');
    Route::post('/tenants/{uuid}/seed', [TenantApiController::class, 'apiSeed'])->name('api.tenants.seed');

    // Bulk Operations
    Route::post('/seed-all-tenants', [TenantApiController::class, 'apiSeedAllTenants'])->name('api.seed-all-tenants');
    Route::post('/migrate-all-tenants', [TenantApiController::class, 'apiMigrateAllTenants'])->name('api.migrate-all-tenants');
    Route::put('/bulk-status-update', [TenantApiController::class, 'apiBulkStatusUpdate'])->name('api.bulk-status-update');

    // System Operations
    Route::post('/clear-cache', [TenantApiController::class, 'apiClearCache'])->name('api.clear-cache');
    Route::post('/clear-all-caches', [TenantApiController::class, 'apiClearAllCaches'])->name('api.clear-all-caches');
    Route::get('/system-info', [TenantApiController::class, 'apiSystemInfo'])->name('api.system-info');

    // Maintenance Mode
    Route::post('/maintenance/on', [TenantApiController::class, 'apiMaintenanceOn'])->name('api.maintenance.on');
    Route::post('/maintenance/off', [TenantApiController::class, 'apiMaintenanceOff'])->name('api.maintenance.off');

    // Backup & Restore
    Route::post('/tenants/{uuid}/backup', [TenantApiController::class, 'apiBackupTenant'])->name('api.tenants.backup');
    Route::post('/tenants/{uuid}/restore', [TenantApiController::class, 'apiRestoreTenant'])->name('api.tenants.restore');
    Route::post('/tenants/{uuid}/export', [TenantApiController::class, 'apiExportTenant'])->name('api.tenants.export');
    Route::post('/import-tenant', [TenantApiController::class, 'apiImportTenant'])->name('api.tenants.import');

    // Analytics
    Route::get('/analytics/overview', [TenantApiController::class, 'apiAnalyticsOverview'])->name('api.analytics.overview');
    Route::get('/analytics/usage', [TenantApiController::class, 'apiUsageAnalytics'])->name('api.analytics.usage');
    Route::get('/analytics/performance', [TenantApiController::class, 'apiPerformanceAnalytics'])->name('api.analytics.performance');
    Route::get('/analytics/growth', [TenantApiController::class, 'apiGrowthAnalytics'])->name('api.analytics.growth');

    // Reports
    Route::get('/reports/tenants', [TenantApiController::class, 'apiTenantsReport'])->name('api.reports.tenants');
    Route::get('/reports/system', [TenantApiController::class, 'apiSystemReport'])->name('api.reports.system');

    // Webhooks
    Route::post('/webhooks/tenant-created', [TenantApiController::class, 'webhookTenantCreated'])->name('api.webhooks.tenant-created');
    Route::post('/webhooks/tenant-updated', [TenantApiController::class, 'webhookTenantUpdated'])->name('api.webhooks.tenant-updated');
    Route::post('/webhooks/tenant-deleted', [TenantApiController::class, 'webhookTenantDeleted'])->name('api.webhooks.tenant-deleted');
});

// Central API routes (for admin panel)
Route::prefix('api/admin')->group(function () {
    // Tenant Management API
    Route::get('/tenants', [TenantApiController::class, 'apiIndex'])->name('api.admin.tenants.index');
    Route::post('/tenants', [TenantApiController::class, 'apiStore'])->name('api.admin.tenants.store');
    Route::get('/tenants/{uuid}', [TenantApiController::class, 'apiShow'])->name('api.admin.tenants.show');
    Route::put('/tenants/{uuid}', [TenantApiController::class, 'apiUpdate'])->name('api.admin.tenants.update');
    Route::delete('/tenants/{uuid}', [TenantApiController::class, 'apiDestroy'])->name('api.admin.tenants.destroy');
    
    // Tenant Operations API
    Route::post('/tenants/{uuid}/activate', [TenantApiController::class, 'apiActivate'])->name('api.admin.tenants.activate');
    Route::post('/tenants/{uuid}/suspend', [TenantApiController::class, 'apiSuspend'])->name('api.admin.tenants.suspend');
    Route::post('/tenants/{uuid}/migrate', [TenantApiController::class, 'apiMigrate'])->name('api.admin.tenants.migrate');
    Route::post('/tenants/{uuid}/seed', [TenantApiController::class, 'apiSeed'])->name('api.admin.tenants.seed');
    
    // System API
    Route::get('/stats', [TenantApiController::class, 'apiStats'])->name('api.admin.stats');
    Route::get('/health', [TenantApiController::class, 'apiHealth'])->name('api.admin.health');
    Route::post('/cache/reset', [TenantApiController::class, 'apiResetCache'])->name('api.admin.cache.reset');
});
