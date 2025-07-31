<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Http\Controllers\TenantApiController;
use ArtflowStudio\Tenancy\Http\Controllers\Api\TenantApiController as ApiTenantApiController;
use ArtflowStudio\Tenancy\Http\Controllers\RealTimeMonitoringController;

/*
|--------------------------------------------------------------------------
| Artflow Tenancy Admin API Routes
|--------------------------------------------------------------------------
|
| These routes provide the complete API for tenant management.
| All API endpoints are organized here for centralized management.
|
| Middleware and route prefix are applied from config:
| - config('artflow-tenancy.middleware.api') for middleware
| - config('artflow-tenancy.route.api_prefix') for prefix
|
*/

// Get configuration
$apiPrefix = config('artflow-tenancy.route.api_prefix', 'af-tenancy-api');
$middleware = config('artflow-tenancy.middleware.api', ['api']);

// ========================================
// CENTRAL DOMAIN API ROUTES
// ========================================

Route::middleware($middleware)
    ->prefix($apiPrefix)
    ->name('api.tenancy.')
    ->group(function () {
        
        // ========================================
        // TENANT MANAGEMENT API
        // ========================================
        
        // RESTful tenant resource routes
        Route::apiResource('tenants', ApiTenantApiController::class);
        
        // Tenant quota management API
        Route::get('tenants/{tenant}/quotas', [ApiTenantApiController::class, 'getQuotas'])->name('tenants.quotas');
        Route::put('tenants/{tenant}/quotas', [ApiTenantApiController::class, 'updateQuotas'])->name('tenants.quotas.update');
        Route::post('tenants/{tenant}/quotas/reset', [ApiTenantApiController::class, 'resetQuotas'])->name('tenants.quotas.reset');
        
        // Tenant analytics API
        Route::get('tenants/{tenant}/analytics', [ApiTenantApiController::class, 'getAnalytics'])->name('tenants.analytics');
        
        // Tenant settings API
        Route::get('tenants/{tenant}/settings', [ApiTenantApiController::class, 'getSettings'])->name('tenants.settings');
        Route::put('tenants/{tenant}/settings', [ApiTenantApiController::class, 'updateSettings'])->name('tenants.settings.update');
        
        // Tenant operations API
        Route::post('tenants/{tenant}/migrate', [TenantApiController::class, 'migrate'])->name('tenants.migrate');
        Route::post('tenants/{tenant}/seed', [TenantApiController::class, 'seed'])->name('tenants.seed');
        Route::post('tenants/migrate-all', [TenantApiController::class, 'migrateAll'])->name('tenants.migrate-all');
        
        // ========================================
        // SYSTEM MANAGEMENT API
        // ========================================
        
        // System overview and health
        Route::get('system/overview', [ApiTenantApiController::class, 'getSystemOverview'])->name('system.overview');
        Route::get('system/health', [TenantApiController::class, 'health'])->name('system.health');
        Route::get('system/stats', [TenantApiController::class, 'stats'])->name('system.stats');
        Route::get('system/info', [TenantApiController::class, 'systemInfo'])->name('system.info');
        
        // ========================================
        // REAL-TIME MONITORING API
        // ========================================
        
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('live-stats', [RealTimeMonitoringController::class, 'liveStats'])->name('live-stats');
            Route::get('connections', [RealTimeMonitoringController::class, 'connections'])->name('connections');
            Route::get('performance', [RealTimeMonitoringController::class, 'performance'])->name('performance');
        });
        
        // ========================================
        // LEGACY API ROUTES (for backward compatibility)
        // ========================================
        
        // Legacy tenant CRUD operations
        Route::get('legacy/tenants', [TenantApiController::class, 'index'])->name('legacy.tenants.index');
        Route::post('legacy/tenants', [TenantApiController::class, 'store'])->name('legacy.tenants.store');
        Route::get('legacy/tenants/{tenant}', [TenantApiController::class, 'show'])->name('legacy.tenants.show');
        Route::put('legacy/tenants/{tenant}', [TenantApiController::class, 'update'])->name('legacy.tenants.update');
        Route::delete('legacy/tenants/{tenant}', [TenantApiController::class, 'destroy'])->name('legacy.tenants.destroy');
    });

// ========================================
// TENANT CONTEXT API ROUTES
// ========================================

Route::middleware(['tenant'])
    ->prefix($apiPrefix . '/tenant')
    ->name('api.tenant.')
    ->group(function () {
        
        // Tenant-specific API routes
        Route::get('info', [TenantApiController::class, 'tenantInfo'])->name('info');
        Route::get('stats', [TenantApiController::class, 'tenantStats'])->name('stats');
        Route::get('health', [TenantApiController::class, 'tenantHealth'])->name('health');
        
        // Real-time monitoring for tenant
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('stats', [RealTimeMonitoringController::class, 'tenantStats'])->name('stats');
            Route::get('activity', [RealTimeMonitoringController::class, 'tenantActivity'])->name('activity');
        });
    });

// ========================================
// DEVELOPMENT & TESTING API ROUTES  
// ========================================

if (app()->environment(['local', 'testing'])) {
    
    Route::middleware($middleware)
        ->prefix($apiPrefix . '/dev')
        ->name('api.tenancy.dev.')
        ->group(function () {
            
            // Development and testing utilities
            Route::get('test-connections', [TenantApiController::class, 'testConnections'])->name('test-connections');
            Route::get('stress-test', [TenantApiController::class, 'stressTest'])->name('stress-test');
            
            // Debug endpoints
            Route::get('config', function () {
                return response()->json([
                    'middleware' => config('artflow-tenancy.middleware'),
                    'route_config' => config('artflow-tenancy.route'),
                    'central_domains' => config('artflow-tenancy.central_domains'),
                ]);
            })->name('config');
            
            Route::get('routes', function () {
                $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
                    ->filter(function ($route) {
                        return str_contains($route->getName() ?? '', 'tenancy');
                    })
                    ->map(function ($route) {
                        return [
                            'name' => $route->getName(),
                            'uri' => $route->uri(),
                            'methods' => $route->methods(),
                            'middleware' => $route->middleware(),
                        ];
                    })
                    ->values();
                    
                return response()->json($routes);
            })->name('routes');
        });
}
