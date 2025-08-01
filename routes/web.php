<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Artflow Studio Tenancy Web Routes  
|--------------------------------------------------------------------------
|
| Web routes for tenant management dashboard.
| These are currently disabled due to service provider issues.
| Use CLI commands or API endpoints instead.
|
*/

// Web routes disabled until service provider binding issues are resolved
// Route::prefix('tenancy')->middleware(['web'])->group(function () {
//     Route::get('/', [TenantController::class, 'index'])->name('tenancy.dashboard');
//     Route::get('/tenants', [TenantController::class, 'tenants'])->name('tenancy.tenants');
// });
