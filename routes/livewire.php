<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring;
use ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring;

/*
|--------------------------------------------------------------------------
| Artflow Tenancy Admin Livewire Routes
|--------------------------------------------------------------------------
|
| These routes define the Livewire-powered admin interface for tenant management.
| All routes require authentication and admin role access.
|
*/

// Get middleware configuration
$middlewareConfig = config('artflow-tenancy.middleware', [
    'admin' => ['web', 'auth', 'role:admin']
]);

Route::middleware($middlewareConfig['admin'])
    ->prefix('tenancy/admin')
    ->name('tenancy.admin.')
    ->group(function () {
        
        // Dashboard
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('/dashboard', Dashboard::class)->name('dashboard.view');
        
        // Tenant Management
        Route::get('/tenants', TenantsIndex::class)->name('index');
        Route::get('/tenants/create', CreateTenant::class)->name('create');
        Route::get('/tenants/{tenant}', ViewTenant::class)->name('show');
        
        // Analytics
        Route::get('/analytics', TenantAnalytics::class)->name('analytics');
        
        // Monitoring
        Route::get('/queue', QueueMonitoring::class)->name('queue');
        Route::get('/monitoring', SystemMonitoring::class)->name('monitoring');
        
        // API Settings (these will be added later when components are created)
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
