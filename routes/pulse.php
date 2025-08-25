<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Http\Controllers\TenantPulseController;

/*
|--------------------------------------------------------------------------
| Tenant Pulse Dashboard Routes
|--------------------------------------------------------------------------
|
| These routes provide access to tenant-specific Pulse metrics and 
| dashboard functionality for administrators.
|
*/

Route::prefix('admin/pulse')->name('admin.pulse.')->group(function () {
    Route::get('/', [TenantPulseController::class, 'dashboard'])->name('dashboard');
    Route::get('/metrics', [TenantPulseController::class, 'metrics'])->name('metrics');
    Route::post('/clean', [TenantPulseController::class, 'cleanMetrics'])->name('clean');
});
