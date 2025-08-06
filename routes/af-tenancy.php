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

// Get middleware config once at the top
$middleware = config('artflow-tenancy.middleware', [
    'ui' => ['web'],
    'api' => ['tenancy.api'],
    'admin' => ['web'],
]);

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
                
                // Tenant status management
                Route::put('/tenants/{tenant}/activate', [TenantViewController::class, 'activate'])->name('tenants.activate');
                Route::put('/tenants/{tenant}/deactivate', [TenantViewController::class, 'deactivate'])->name('tenants.deactivate');
                Route::put('/tenants/{tenant}/suspend', [TenantViewController::class, 'suspend'])->name('tenants.suspend');
                Route::put('/tenants/{tenant}/block', [TenantViewController::class, 'block'])->name('tenants.block');
                
                // Tenant database operations
                Route::post('/tenants/{tenant}/migrate', [TenantViewController::class, 'migrate'])->name('tenants.migrate');
                Route::post('/tenants/{tenant}/migrate/fresh', [TenantViewController::class, 'migrateFresh'])->name('tenants.migrate.fresh');
                Route::post('/tenants/{tenant}/seed', [TenantViewController::class, 'seed'])->name('tenants.seed');
                
                // System health and monitoring
                Route::get('/health', [TenantViewController::class, 'health'])->name('health');
                Route::get('/stats', [TenantViewController::class, 'stats'])->name('stats');
            });
            
        // Central domain API routes for tenant management
        Route::prefix('api/tenancy')
            ->name('api.tenancy.')
            ->group(function () {
                
                // Tenant CRUD API
                Route::get('/tenants', [TenantApiController::class, 'index'])->name('tenants.index');
                Route::post('/tenants', [TenantApiController::class, 'store'])->name('tenants.store');
                Route::get('/tenants/{tenant}', [TenantApiController::class, 'show'])->name('tenants.show');
                Route::put('/tenants/{tenant}', [TenantApiController::class, 'update'])->name('tenants.update');
                Route::delete('/tenants/{tenant}', [TenantApiController::class, 'destroy'])->name('tenants.destroy');
                
                // Tenant operations API
                Route::post('/tenants/{tenant}/migrate', [TenantApiController::class, 'migrate'])->name('tenants.migrate');
                Route::post('/tenants/{tenant}/seed', [TenantApiController::class, 'seed'])->name('tenants.seed');
                Route::post('/tenants/migrate-all', [TenantApiController::class, 'migrateAll'])->name('tenants.migrate-all');
                
                // System API
                Route::get('/health', [TenantApiController::class, 'health'])->name('health');
                Route::get('/stats', [TenantApiController::class, 'stats'])->name('stats');
                Route::get('/system-info', [TenantApiController::class, 'systemInfo'])->name('system-info');
                
                // Real-time monitoring
                Route::get('/monitoring/live-stats', [RealTimeMonitoringController::class, 'liveStats'])->name('monitoring.live-stats');
                Route::get('/monitoring/connections', [RealTimeMonitoringController::class, 'connections'])->name('monitoring.connections');
                Route::get('/monitoring/performance', [RealTimeMonitoringController::class, 'performance'])->name('monitoring.performance');
            });
    });

// ========================================
// TENANT CONTEXT ROUTES
// ========================================

Route::middleware(['tenant.web'])
    ->group(function () use ($middleware) {
        
        // Tenant-specific UI routes
        Route::prefix('tenant')
            ->middleware($middleware['ui'])
            ->name('tenant.')
            ->group(function () {
                
                // Tenant information display
                Route::get('info', [TenantViewController::class, 'tenantInfo'])->name('info');
            });
    });

// ========================================
// TENANT CONTEXT API ROUTES
// ========================================

Route::middleware(['tenant'])
    ->group(function () use ($middleware) {
        
        // Tenant-specific API routes
        Route::prefix('api/tenant')
            ->middleware($middleware['api'])
            ->group(function () {
                Route::get('info', [TenantApiController::class, 'tenantInfo']);
                Route::get('stats', [TenantApiController::class, 'tenantStats']);
                Route::get('health', [TenantApiController::class, 'tenantHealth']);
                
                // Real-time monitoring for tenant
                Route::get('monitoring/stats', [RealTimeMonitoringController::class, 'tenantStats']);
                Route::get('monitoring/activity', [RealTimeMonitoringController::class, 'tenantActivity']);
            });
    });

// ========================================
// ADMIN ROUTES (BOTH CENTRAL AND TENANT CONTEXTS)
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
        
        // Tenant status management
        Route::put('/tenants/{tenant}/activate', [TenantViewController::class, 'activate'])->name('tenants.activate');
        Route::put('/tenants/{tenant}/deactivate', [TenantViewController::class, 'deactivate'])->name('tenants.deactivate');
        Route::put('/tenants/{tenant}/suspend', [TenantViewController::class, 'suspend'])->name('tenants.suspend');
        Route::put('/tenants/{tenant}/block', [TenantViewController::class, 'block'])->name('tenants.block');
        
        // Tenant database operations
        Route::post('/tenants/{tenant}/migrate', [TenantViewController::class, 'migrate'])->name('tenants.migrate');
        Route::post('/tenants/{tenant}/migrate/fresh', [TenantViewController::class, 'migrateFresh'])->name('tenants.migrate.fresh');
        Route::post('/tenants/{tenant}/seed', [TenantViewController::class, 'seed'])->name('tenants.seed');
        
        // System health and monitoring
        Route::get('/health', [TenantViewController::class, 'health'])->name('health');
        Route::get('/stats', [TenantViewController::class, 'stats'])->name('stats');
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
