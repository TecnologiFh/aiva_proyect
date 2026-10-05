<?php

use App\Modules\Business\Controllers\BusinessHoursController;
use App\Modules\Business\Controllers\BusinessSettingsController;
use App\Modules\Business\Controllers\CategoryController;
use App\Modules\Business\Controllers\ProductController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    // Rutas protegidas por Sanctum Token y RBAC
    Route::middleware(['auth:sanctum'])->group(function () {

        // Categorías
        Route::get('/business/categories', [CategoryController::class, 'index'])
            ->middleware(RequirePermission::class . ':business:view_products');

        Route::post('/business/categories', [CategoryController::class, 'store'])
            ->middleware(RequirePermission::class . ':business:manage_products');

        // Productos y Servicios
        Route::get('/business/products', [ProductController::class, 'index'])
            ->middleware(RequirePermission::class . ':business:view_products');

        Route::post('/business/products', [ProductController::class, 'store'])
            ->middleware(RequirePermission::class . ':business:manage_products');

        Route::patch('/business/products/{uuid}/status', [ProductController::class, 'updateStatus'])
            ->middleware(RequirePermission::class . ':business:manage_products');

        // Configuración Comercial del Negocio
        Route::get('/business/settings', [BusinessSettingsController::class, 'show'])
            ->middleware(RequirePermission::class . ':business:view_products');

        Route::put('/business/settings', [BusinessSettingsController::class, 'update'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');

        // Horarios de Atención por Turnos
        Route::get('/business/hours', [BusinessHoursController::class, 'show'])
            ->middleware(RequirePermission::class . ':business:view_products');

        Route::post('/business/hours', [BusinessHoursController::class, 'update'])
            ->middleware(RequirePermission::class . ':tenant:manage_settings');
    });
});
