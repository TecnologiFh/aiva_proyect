<?php

use App\Modules\AI\Controllers\AIController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        // Procesamiento completo de mensaje comercial
        Route::post('/ai/process', [AIController::class, 'process'])
            ->middleware(RequirePermission::class . ':conversation:reply');

        // Análisis liviano de intención
        Route::post('/ai/analyze-intent', [AIController::class, 'analyzeIntent'])
            ->middleware(RequirePermission::class . ':conversation:reply');

        // Estadísticas de uso de tokens y costo
        Route::get('/ai/usage', [AIController::class, 'usageStats'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');
    });
});
