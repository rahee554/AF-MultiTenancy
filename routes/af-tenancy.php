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
| This file contains all routes for the Artflow Studio Tenancy package.
| Routes are organized with middleware from artflow-tenancy.php config.
|
*/

// Get middleware configuration
$middleware = config('artflow-tenancy.middleware', [
    'ui' => ['web'],
    'api' => ['tenancy.api'],
    'admin' => ['web'],
]);

// ========================================
// CENTRAL DOMAIN ROUTES (127.0.0.1, localhost, etc.)
// ========================================

Route::middleware(['central'])
    ->group(function () {
        
        // Central domain homepage
        Route::get('/', function () {
            return view('welcome'); // Default Laravel welcome page
        })->name('central.home');
        
        // Central domain dashboard and admin routes
        Route::view('dashboard', 'dashboard')
            ->middleware(['auth', 'verified'])
            ->name('central.dashboard');
            
        
        // Central domain API health endpoint
        Route::get('api/health', function () {
            return response()->json([
                'status' => 'OK',
                'timestamp' => now(),
                'domain' => request()->getHost(),
                'type' => 'central',
                'tenancy_system' => 'operational'
            ]);
        })->name('central.api.health');
    });

// ========================================
// API ROUTES - Tenant Management
// ========================================

Route::prefix('api/tenancy')
    ->middleware($middleware['api'])
    ->group(function () {
        
        // Health and status endpoints
        Route::get('health', [TenantApiController::class, 'apiHealth']);
        Route::get('stats', [TenantApiController::class, 'apiStats']);
        
        // Tenant management endpoints
        Route::get('tenants', [TenantApiController::class, 'index']);
        Route::post('tenants', [TenantApiController::class, 'store']);
        Route::get('tenants/{id}', [TenantApiController::class, 'show']);
        Route::put('tenants/{id}', [TenantApiController::class, 'update']);
        Route::delete('tenants/{id}', [TenantApiController::class, 'destroy']);
        
        // Tenant operations
        Route::post('tenants/{id}/activate', [TenantApiController::class, 'activate']);
        Route::post('tenants/{id}/deactivate', [TenantApiController::class, 'deactivate']);
        Route::post('tenants/{id}/migrate', [TenantApiController::class, 'migrate']);
        
        // Real-time monitoring endpoints
        Route::get('monitor/performance', [RealTimeMonitoringController::class, 'performance']);
        Route::get('monitor/connections', [RealTimeMonitoringController::class, 'connections']);
        Route::get('monitor/memory', [RealTimeMonitoringController::class, 'memory']);
        Route::get('monitor/overview', [RealTimeMonitoringController::class, 'overview']);
        
        // Batch operations
        Route::post('tenants/batch/create', [TenantApiController::class, 'batchCreate']);
        Route::post('tenants/batch/delete', [TenantApiController::class, 'batchDelete']);
        Route::post('tenants/batch/migrate', [TenantApiController::class, 'batchMigrate']);
        
        // Performance testing endpoints
        Route::post('test/performance', [TenantApiController::class, 'testPerformance']);
        Route::post('test/isolation', [TenantApiController::class, 'testIsolation']);
        Route::get('test/status', [TenantApiController::class, 'getTestStatus']);
    });

// ========================================
// ADMIN WEB ROUTES - Management Interface
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
