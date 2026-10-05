<?php

namespace App\Modules\Intelligence\Providers;

use App\Modules\Analytics\Services\FinancialAllocationTruthEngineService;
use App\Modules\Intelligence\Queries\GetSalesAnalyticsQuery;
use Illuminate\Support\ServiceProvider;

class IntelligenceServiceProvider extends ServiceProvider
{
    /**
     * Register any intelligence query services.
     */
    public function register(): void
    {
        $this->app->singleton(GetSalesAnalyticsQuery::class, function ($app) {
            return new GetSalesAnalyticsQuery(
                $app->make(FinancialAllocationTruthEngineService::class)
            );
        });

        $this->app->singleton(\App\Modules\Intelligence\Queries\GetOrdersSummaryQuery::class, function () {
            return new \App\Modules\Intelligence\Queries\GetOrdersSummaryQuery();
        });

        $this->app->singleton(\App\Modules\Intelligence\Queries\GetOrdersStatusQuery::class, function () {
            return new \App\Modules\Intelligence\Queries\GetOrdersStatusQuery();
        });

        $this->app->singleton(\App\Modules\Intelligence\Queries\GetOrdersOperationsQuery::class, function ($app) {
            return new \App\Modules\Intelligence\Queries\GetOrdersOperationsQuery(
                $app->make(\App\Modules\Intelligence\Queries\GetOrdersSummaryQuery::class),
                $app->make(\App\Modules\Intelligence\Queries\GetOrdersStatusQuery::class)
            );
        });

        $this->app->singleton(\App\Modules\Intelligence\Queries\GetCustomersAnalyticsQuery::class, function () {
            return new \App\Modules\Intelligence\Queries\GetCustomersAnalyticsQuery();
        });

        $this->app->singleton(\App\Modules\Intelligence\Queries\GetProductsAnalyticsQuery::class, function () {
            return new \App\Modules\Intelligence\Queries\GetProductsAnalyticsQuery();
        });

        // Intelligence Foundation: Metric Registry, Cache, and Engine
        $this->app->singleton(\App\Modules\Intelligence\Domain\Registry\MetricRegistry::class, function () {
            return new \App\Modules\Intelligence\Domain\Registry\MetricRegistry();
        });

        $this->app->singleton(\App\Modules\Intelligence\Domain\Engine\Cache\IntelligenceCacheService::class, function () {
            return new \App\Modules\Intelligence\Domain\Engine\Cache\IntelligenceCacheService();
        });

        $this->app->singleton(\App\Modules\Intelligence\Domain\Engine\MetricEngine::class, function ($app) {
            return new \App\Modules\Intelligence\Domain\Engine\MetricEngine(
                $app->make(\App\Modules\Intelligence\Domain\Registry\MetricRegistry::class),
                $app->make(\App\Modules\Intelligence\Domain\Engine\Cache\IntelligenceCacheService::class),
                $app
            );
        });
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        // El enrutamiento de API para el módulo es cargado automáticamente
        // por ModuleDiscoveryServiceProvider si existe app/Modules/Intelligence/Routes/api.php
    }
}
