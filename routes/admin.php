<?php

use Illuminate\Support\Facades\Route;

Route::middleware(config('artflow-tenancy.admin.middleware', ['web', 'can:manage-tenants']))
    ->prefix((string) (config('artflow-tenancy.admin.route_prefix', 'admin') . '/tenants'))
    ->name('tenancy.admin.')
    ->group(function () {
        // Dashboard and Main Pages
        Route::get('/', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/tenants', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantsIndex::class)->name('index');
        Route::get('/create', \ArtflowStudio\Tenancy\Http\Livewire\Admin\CreateTenant::class)->name('create');
        Route::get('/tenant/{tenant}', \ArtflowStudio\Tenancy\Http\Livewire\Admin\ViewTenant::class)->name('view');
        
        // Monitoring and Analytics
        Route::get('/monitoring', \ArtflowStudio\Tenancy\Http\Livewire\Admin\SystemMonitoring::class)->name('monitoring');
        Route::get('/queue', \ArtflowStudio\Tenancy\Http\Livewire\Admin\QueueMonitoring::class)->name('queue');
        Route::get('/analytics', \ArtflowStudio\Tenancy\Http\Livewire\Admin\TenantAnalytics::class)->name('analytics');
        
        // API Management Routes
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/settings', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Api\ApiSettings::class)->name('settings');
            Route::get('/keys', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Api\ApiKeys::class)->name('keys');
            Route::get('/endpoints', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Api\ApiEndpoints::class)->name('endpoints');
            Route::get('/docs', \ArtflowStudio\Tenancy\Http\Livewire\Admin\Api\ApiDocumentation::class)->name('docs');
        });
    });
