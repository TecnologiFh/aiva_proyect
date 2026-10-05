<?php

use App\Modules\CRM\Controllers\CustomerController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware(RequirePermission::class . ':crm:view');

        Route::get('/customers/{uuid}', [CustomerController::class, 'show'])
            ->middleware(RequirePermission::class . ':crm:view');

        Route::post('/customers/{uuid}/addresses', [CustomerController::class, 'addAddress'])
            ->middleware(RequirePermission::class . ':crm:edit');

        Route::post('/customers/{uuid}/tags', [CustomerController::class, 'updateTags'])
            ->middleware(RequirePermission::class . ':crm:edit');
    });
});
