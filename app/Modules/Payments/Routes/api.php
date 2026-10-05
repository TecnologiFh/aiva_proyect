<?php

use App\Modules\Payments\Controllers\PaymentController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

// Endpoint público para recepción de Webhooks de Pasarelas de Pago
Route::post('/payments/webhook/{gateway}', [PaymentController::class, 'handleWebhook']);

// Rutas protegidas por Sanctum Token y RBAC
Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/payments', [PaymentController::class, 'index'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');

        Route::post('/payments', [PaymentController::class, 'store'])
            ->middleware(RequirePermission::class . ':order:create');
    });
});
