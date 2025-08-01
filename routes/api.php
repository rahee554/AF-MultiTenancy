<?php

use Illuminate\Support\Facades\Route;
use ArtflowStudio\Tenancy\Http\Controllers\TenantApiController;

/*
|--------------------------------------------------------------------------
| Artflow Studio Tenancy API Routes
|--------------------------------------------------------------------------
|
| API routes for tenant management with simple X-API-Key authentication.
| No bearer tokens - just simple API key validation.
|
*/

Route::prefix('api/tenancy')->group(function () {
    
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
    
});
