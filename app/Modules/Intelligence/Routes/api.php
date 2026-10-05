<?php

use App\Modules\Intelligence\Controllers\IntelligenceController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/intelligence/sales', [IntelligenceController::class, 'sales'])
            ->middleware(RequirePermission::class . ':report:view_sales');

        Route::get('/intelligence/orders', [IntelligenceController::class, 'orders'])
            ->middleware(RequirePermission::class . ':report:view_orders');

        Route::get('/intelligence/orders/status', [IntelligenceController::class, 'ordersStatus'])
            ->middleware(RequirePermission::class . ':report:view_orders');

        Route::get('/intelligence/operations', [IntelligenceController::class, 'operations'])
            ->middleware(RequirePermission::class . ':report:view_operations');

        Route::get('/intelligence/customers', [IntelligenceController::class, 'customers'])
            ->middleware(RequirePermission::class . ':report:view_customers');

        Route::get('/intelligence/customers/360/{customerId}', [IntelligenceController::class, 'customer360'])
            ->middleware(RequirePermission::class . ':report:view_customers');

        Route::get('/intelligence/products', [IntelligenceController::class, 'products'])
            ->middleware(RequirePermission::class . ':report:view_products');

        // Intelligence Foundation Metric Engine Routes
        Route::get('/intelligence/metrics', [IntelligenceController::class, 'metrics'])
            ->middleware(RequirePermission::class . ':report:view_sales');

        Route::get('/intelligence/metric/{metricKey}', [IntelligenceController::class, 'queryMetric'])
            ->middleware(RequirePermission::class . ':report:view_sales');

        Route::post('/intelligence/query', [IntelligenceController::class, 'batchQuery'])
            ->middleware(RequirePermission::class . ':report:view_sales');
    });
});
