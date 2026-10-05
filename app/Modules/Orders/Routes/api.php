<?php

use App\Modules\Orders\Controllers\OrderController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/orders', [OrderController::class, 'index'])
            ->middleware(RequirePermission::class . ':order:create');

        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware(RequirePermission::class . ':order:create');

        Route::post('/orders/{uuid}/confirm', [OrderController::class, 'confirm'])
            ->middleware(RequirePermission::class . ':order:create');

        Route::patch('/orders/{uuid}/status', [OrderController::class, 'updateStatus'])
            ->middleware(RequirePermission::class . ':order:create');
    });
});
