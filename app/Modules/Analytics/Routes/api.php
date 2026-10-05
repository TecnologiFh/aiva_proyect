<?php

use App\Modules\Analytics\Controllers\AnalyticsController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])
            ->middleware(RequirePermission::class . ':report:view_sales');

        Route::get('/analytics/sales', [AnalyticsController::class, 'sales'])
            ->middleware(RequirePermission::class . ':report:view_sales');

        Route::get('/analytics/ai-performance', [AnalyticsController::class, 'aiPerformance'])
            ->middleware(RequirePermission::class . ':report:view_analytics');
    });
});
