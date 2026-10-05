<?php

use App\Modules\Automation\Controllers\AutomationController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/automations', [AutomationController::class, 'index'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');

        Route::post('/automations', [AutomationController::class, 'store'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');

        Route::patch('/automations/{uuid}/toggle', [AutomationController::class, 'toggle'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');
    });
});
