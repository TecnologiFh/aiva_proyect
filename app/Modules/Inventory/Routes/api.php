<?php

use App\Modules\Inventory\Controllers\InventoryController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/inventory', [InventoryController::class, 'index'])
            ->middleware(RequirePermission::class . ':inventory:view');

        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])
            ->middleware(RequirePermission::class . ':inventory:adjust');

        Route::get('/inventory/movements', [InventoryController::class, 'movements'])
            ->middleware(RequirePermission::class . ':inventory:view');
    });
});
