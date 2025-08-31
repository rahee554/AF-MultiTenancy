<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use ArtflowStudio\Tenancy\Http\Controllers\TenantViewController;
use ArtflowStudio\Tenancy\Http\Controllers\Admin\TenantController;
use ArtflowStudio\Tenancy\Http\Controllers\Admin\SystemController;
use ArtflowStudio\Tenancy\Http\Controllers\RealTimeMonitoringController;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring;

/*
|--------------------------------------------------------------------------
| Artflow Tenancy Admin Routes
|--------------------------------------------------------------------------
|
| These routes provide the complete admin interface for tenant management.
| Includes both traditional controllers and Livewire components.
|
| Middleware and route prefix are applied from config:
| - config('artflow-tenancy.middleware.admin') for middleware
| - config('artflow-tenancy.route.prefix') for prefix
|
*/

// Get configuration
$routePrefix = config('artflow-tenancy.route.prefix', 'af-tenancy');
$middleware = config('artflow-tenancy.middleware.admin', ['central.web', 'auth']);

// Apply middleware and prefix from config
Route::middleware($middleware)
    ->prefix($routePrefix)
    ->name('tenancy.')
    ->group(function () {
        
        // ========================================
        // LIVEWIRE ADMIN INTERFACE
        // ========================================
        
        Route::prefix('admin')->name('admin.')->group(function () {
            
            // Admin Dashboard
            Route::get('/', Dashboard::class)->name('dashboard');
            Route::get('/dashboard', Dashboard::class)->name('dashboard.view');
            
            // Admin Index alias for sidebar compatibility
            Route::redirect('/index', '/')->name('index');
            
            // Tenant Management
            Route::get('/tenants', TenantsIndex::class)->name('tenants.index');
            Route::get('/tenants/create', CreateTenant::class)->name('create');
            Route::get('/tenants/{tenant}', ViewTenant::class)->name('tenants.show');
            Route::get('/tenants/{tenant}/edit', ViewTenant::class)->name('tenants.edit');
            
            // Analytics
            Route::get('/analytics', TenantAnalytics::class)->name('analytics');
            
            // Monitoring
            Route::get('/queue', QueueMonitoring::class)->name('queue');
            Route::get('/monitoring', SystemMonitoring::class)->name('monitoring');
            
            // API Settings (placeholder views for future components)
            Route::get('/api/settings', function() {
                return view('artflow-tenancy::admin.api.settings');
            })->name('api.settings');
            
            Route::get('/api/keys', function() {
                return view('artflow-tenancy::admin.api.keys');
            })->name('api.keys');
            
            Route::get('/api/endpoints', function() {
                return view('artflow-tenancy::admin.api.endpoints');
            })->name('api.endpoints');
            
            Route::get('/api/docs', function() {
                return view('artflow-tenancy::admin.api.docs');
            })->name('api.docs');
        });
        
        // ========================================
        // SYSTEM HEALTH AND STATS ROUTES
        // ========================================
        
        // System health and monitoring (keep minimal controller routes for basic system info)
        Route::get('/health', [TenantViewController::class, 'health'])->name('health');
        Route::get('/stats', [TenantViewController::class, 'stats'])->name('stats');
        
        // ========================================
        // ENHANCED ADMIN ROUTES
        // ========================================
        
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('dashboard', [SystemController::class, 'dashboard'])->name('dashboard');
            Route::get('configuration', [SystemController::class, 'configuration'])->name('configuration');
            Route::post('cache-configuration', [SystemController::class, 'updateCacheConfiguration'])->name('cache-configuration');
            Route::post('enable-enhanced', [SystemController::class, 'enableEnhancedTenancy'])->name('enable-enhanced');
            Route::post('maintenance', [SystemController::class, 'maintenance'])->name('maintenance');
            Route::get('stats', [SystemController::class, 'stats'])->name('stats');
        });
        
        // Enhanced tenant management routes have been moved to pure Livewire components above
        
        // Real-time monitoring
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('/live-stats', [RealTimeMonitoringController::class, 'liveStats'])->name('live-stats');
            Route::get('/connections', [RealTimeMonitoringController::class, 'connections'])->name('connections');
            Route::get('/performance', [RealTimeMonitoringController::class, 'performance'])->name('performance');
        });
    });

// ========================================
// TENANT CONTEXT ROUTES
// ========================================

Route::middleware(['tenant.web'])
    ->prefix($routePrefix . '/tenant')
    ->name('tenant.')
    ->group(function () {
        // Tenant information display
        Route::get('info', [TenantViewController::class, 'tenantInfo'])->name('info');
    });

// ========================================
// DEVELOPMENT & TESTING ROUTES  
// ========================================

if (app()->environment(['local', 'testing'])) {
    
    // Development utilities - available on CENTRAL domain only
    Route::middleware(['central.web'])
        ->prefix($routePrefix . '/dev')
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
            Route::get('test-connections', [\ArtflowStudio\Tenancy\Http\Controllers\TenantApiController::class, 'testConnections'])->name('test-connections');
            Route::get('stress-test', [\ArtflowStudio\Tenancy\Http\Controllers\TenantApiController::class, 'stressTest'])->name('stress-test');
        });
}
