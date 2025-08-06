<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use ArtflowStudio\Tenancy\Http\Controllers\TenantViewController;
use ArtflowStudio\Tenancy\Http\Controllers\TenantApiController;
use ArtflowStudio\Tenancy\Http\Controllers\RealTimeMonitoringController;

/*
|--------------------------------------------------------------------------
| Artflow Studio Tenancy Routes
|--------------------------------------------------------------------------
|
| IMPORTANT: This package works ON TOP OF stancl/tenancy.
| 
| For proper routing setup:
| 1. Central domains use standard Laravel routes in routes/web.php and routes/api.php  
| 2. Tenant domains use routes in routes/tenant.php (stancl/tenancy convention)
| 3. These routes provide management and monitoring functionality for both contexts
|
| Middleware groups available:
| - 'central.web'     → For central domain web routes (no tenancy)
| - 'tenant.web'      → For tenant domain web routes (with tenancy + session scoping)
| - 'tenant.api'      → For tenant domain API routes
| - 'central'         → Ensures route is on central domain only
| - 'af-tenant'       → Our tenant enhancements (status checks, logging)
|
*/

// ========================================
// CENTRAL DOMAIN ROUTES ONLY
// ========================================

// These routes are ONLY for central domains (localhost, your-main-domain.com)
// They will be BLOCKED on tenant domains automatically by stancl/tenancy

Route::middleware(['central.web'])
    ->group(function () {
        
        // Central domain admin interface for managing tenants
        Route::prefix('tenancy')
            ->name('tenancy.')
            ->group(function () {
                
                // Dashboard and overview
                Route::get('/', [TenantViewController::class, 'dashboard'])->name('dashboard');
                Route::get('/dashboard', [TenantViewController::class, 'dashboard'])->name('dashboard.view');
                
                // Tenant management web interface
                Route::get('/tenants', [TenantViewController::class, 'index'])->name('tenants.index');
                Route::get('/tenants/create', [TenantViewController::class, 'create'])->name('tenants.create');
                Route::post('/tenants', [TenantViewController::class, 'store'])->name('tenants.store');
                Route::get('/tenants/{tenant}', [TenantViewController::class, 'show'])->name('tenants.show');
                Route::get('/tenants/{tenant}/edit', [TenantViewController::class, 'edit'])->name('tenants.edit');
                Route::put('/tenants/{tenant}', [TenantViewController::class, 'update'])->name('tenants.update');
                Route::delete('/tenants/{tenant}', [TenantViewController::class, 'destroy'])->name('tenants.destroy');
                
                // Performance monitoring web interface
                Route::get('/monitor', [TenantViewController::class, 'monitor'])->name('monitor');
                Route::get('/monitor/performance', [TenantViewController::class, 'performanceMonitor'])->name('monitor.performance');
                Route::get('/monitor/health', [TenantViewController::class, 'healthMonitor'])->name('monitor.health');
                
                // Batch operations web interface
                Route::get('/batch', [TenantViewController::class, 'batchOperations'])->name('batch');
                Route::post('/batch/create-test-tenants', [TenantViewController::class, 'createTestTenants'])->name('batch.create-test');
                Route::post('/batch/cleanup', [TenantViewController::class, 'cleanup'])->name('batch.cleanup');
                
                // Settings and configuration
                Route::get('/settings', [TenantViewController::class, 'settings'])->name('settings');
                Route::post('/settings', [TenantViewController::class, 'updateSettings'])->name('settings.update');
            });
        
        // Central domain API endpoints for tenant management
        Route::prefix('api/tenancy')
            ->name('api.tenancy.')
            ->group(function () {
                
                // Health and system status
                Route::get('health', [TenantApiController::class, 'apiHealth'])->name('health');
                Route::get('stats', [TenantApiController::class, 'apiStats'])->name('stats');
                
                // Tenant management API
                Route::get('tenants', [TenantApiController::class, 'index'])->name('tenants.index');
                Route::post('tenants', [TenantApiController::class, 'store'])->name('tenants.store');
                Route::get('tenants/{id}', [TenantApiController::class, 'show'])->name('tenants.show');
                Route::put('tenants/{id}', [TenantApiController::class, 'update'])->name('tenants.update');
                Route::delete('tenants/{id}', [TenantApiController::class, 'destroy'])->name('tenants.destroy');
                
                // Tenant operations
                Route::post('tenants/{id}/activate', [TenantApiController::class, 'activate'])->name('tenants.activate');
                Route::post('tenants/{id}/deactivate', [TenantApiController::class, 'deactivate'])->name('tenants.deactivate');
                Route::post('tenants/{id}/migrate', [TenantApiController::class, 'migrate'])->name('tenants.migrate');
                
                // Real-time monitoring API
                Route::get('monitor/performance', [RealTimeMonitoringController::class, 'performance'])->name('monitor.performance');
                Route::get('monitor/connections', [RealTimeMonitoringController::class, 'connections'])->name('monitor.connections');
                Route::get('monitor/memory', [RealTimeMonitoringController::class, 'memory'])->name('monitor.memory');
                Route::get('monitor/overview', [RealTimeMonitoringController::class, 'overview'])->name('monitor.overview');
                
                // Batch operations API
                Route::post('tenants/batch/create', [TenantApiController::class, 'batchCreate'])->name('tenants.batch.create');
                Route::post('tenants/batch/delete', [TenantApiController::class, 'batchDelete'])->name('tenants.batch.delete');
                Route::post('tenants/batch/migrate', [TenantApiController::class, 'batchMigrate'])->name('tenants.batch.migrate');
                
                // Performance testing API
                Route::post('test/performance', [TenantApiController::class, 'testPerformance'])->name('test.performance');
                Route::post('test/isolation', [TenantApiController::class, 'testIsolation'])->name('test.isolation');
                Route::get('test/status', [TenantApiController::class, 'getTestStatus'])->name('test.status');
            });
    });

// ========================================
// TENANT DOMAIN ROUTES ONLY  
// ========================================

// These routes are ONLY for tenant domains (tenant1.yourdomain.com, tenant2.yourdomain.com, etc.)
// They will be BLOCKED on central domains automatically by stancl/tenancy

Route::middleware(['tenant.web'])
    ->group(function () {
        
        // Tenant-specific admin/management interface
        Route::prefix('admin')
            ->name('tenant.admin.')
            ->group(function () {
                Route::get('/', [TenantViewController::class, 'tenantDashboard'])->name('dashboard');
                Route::get('/info', [TenantViewController::class, 'tenantInfo'])->name('info');
            });
        
        // Tenant-specific API endpoints
        Route::prefix('api/tenant')
            ->name('tenant.api.')
            ->group(function () {
                Route::get('info', [TenantApiController::class, 'tenantInfo'])->name('info');
                Route::get('stats', [TenantApiController::class, 'tenantStats'])->name('stats');
                Route::post('test-isolation', [TenantApiController::class, 'testTenantIsolation'])->name('test-isolation');
            });
    });

// ========================================
// DEVELOPMENT & TESTING ROUTES
// ========================================

if (app()->environment(['local', 'testing'])) {
    
    // Development utilities - available on CENTRAL domain only
    Route::middleware(['central.web'])
        ->prefix('tenancy/dev')
        ->name('tenancy.dev.')
        ->group(function () {
            
            Route::get('phpinfo', function () {
                return phpinfo();
            })->name('phpinfo');
            
            Route::get('clear-cache', function () {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                return 'Cache cleared';
            })->name('clear-cache');
            
            // Database testing utilities
            Route::get('test-connections', [TenantApiController::class, 'testConnections'])->name('test-connections');
            Route::get('stress-test', [TenantApiController::class, 'stressTest'])->name('stress-test');
        });
}
// ========================================

Route::prefix('tenancy')
    ->middleware($middleware['admin'])
    ->name('tenancy.')
    ->group(function () {
        
        // Dashboard routes
        Route::get('/', [TenantViewController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [TenantViewController::class, 'dashboard'])->name('dashboard.view');
        
        // Tenant management web interface
        Route::get('/tenants', [TenantViewController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantViewController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantViewController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantViewController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/edit', [TenantViewController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [TenantViewController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [TenantViewController::class, 'destroy'])->name('tenants.destroy');
        
        // Performance monitoring web interface
        Route::get('/monitor', [TenantViewController::class, 'monitor'])->name('monitor');
        Route::get('/monitor/performance', [TenantViewController::class, 'performanceMonitor'])->name('monitor.performance');
        Route::get('/monitor/health', [TenantViewController::class, 'healthMonitor'])->name('monitor.health');
        
        // Batch operations web interface
        Route::get('/batch', [TenantViewController::class, 'batchOperations'])->name('batch');
        Route::post('/batch/create-test-tenants', [TenantViewController::class, 'createTestTenants'])->name('batch.create-test');
        Route::post('/batch/cleanup', [TenantViewController::class, 'cleanup'])->name('batch.cleanup');
        
        // Settings and configuration
        Route::get('/settings', [TenantViewController::class, 'settings'])->name('settings');
        Route::post('/settings', [TenantViewController::class, 'updateSettings'])->name('settings.update');
    });

// ========================================
// TENANT CONTEXT ROUTES
// ========================================

Route::middleware(['tenant'])
    ->group(function () {
        
        // Get middleware config inside closure
        $middleware = config('artflow-tenancy.middleware', [
            'ui' => ['web'],
            'api' => ['tenancy.api'],
            'admin' => ['web'],
        ]);
        
        // Tenant-specific API routes
        Route::prefix('api/tenant')
            ->middleware($middleware['api'])
            ->group(function () {
                Route::get('info', [TenantApiController::class, 'tenantInfo']);
                Route::get('stats', [TenantApiController::class, 'tenantStats']);
                Route::post('test-isolation', [TenantApiController::class, 'testTenantIsolation']);
            });
        
        // Tenant-specific web routes
        Route::prefix('tenant')
            ->middleware($middleware['ui'])
            ->name('tenant.')
            ->group(function () {
                Route::get('dashboard', [TenantViewController::class, 'tenantDashboard'])->name('dashboard');
                Route::get('info', [TenantViewController::class, 'tenantInfo'])->name('info');
            });
    });

// ========================================
// DEVELOPMENT & TESTING ROUTES
// ========================================

if (app()->environment(['local', 'testing'])) {
    Route::prefix('tenancy/dev')
        ->middleware($middleware['admin'])
        ->name('tenancy.dev.')
        ->group(function () {
            
            // Development utilities
            Route::get('phpinfo', function () {
                return phpinfo();
            })->name('phpinfo');
            
            Route::get('clear-cache', function () {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                return 'Cache cleared';
            })->name('clear-cache');
            
            // Database testing utilities
            Route::get('test-connections', [TenantApiController::class, 'testConnections'])->name('test-connections');
            Route::get('stress-test', [TenantApiController::class, 'stressTest'])->name('stress-test');
        });
}
