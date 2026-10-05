<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\DTOs\ProductsAnalyticsDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Illuminate\Support\Facades\DB;

class GetProductsAnalyticsQuery
{
    /**
     * Ejecuta la consulta analítica de productos y líneas de pedido históricas.
     * Estrictamente READ-ONLY, tenant-scoped, determinista y auditable.
     */
    public function execute(AnalyticsFilterSet $filterSet): ProductsAnalyticsDTO
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

        // 1. Timezone Guard (Fail-Closed, zero silent fallbacks)
        if (!$filterSet->isTimezoneResolved || $timezone === AnalyticsFilterSet::TIMEZONE_UNRESOLVED) {
            return new ProductsAnalyticsDTO(
                status: MetricDataStatus::INSUFFICIENT_DATA,
                catalogSummary: [
                    'products_count' => 0,
                ],
                orderLinesSummary: [
                    'products_sold' => 0,
                    'units_sold' => 0,
                    'product_revenue' => 0.00,
                ],
                byCategoryCurrent: [],
                topProducts: [],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'products_analytics',
                    sourceOfTruth: 'tenants.timezone',
                    calculationSource: 'GetProductsAnalyticsQuery (TIMEZONE_GUARD)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                    currency: $currency
                ),
                message: 'Zona horaria del tenant no disponible o inválida (TIMEZONE_UNRESOLVED). Se prohíbe fallback silencioso.'
            );
        }

        // 2. P31-01 — products_count (Current active non-deleted catalog products)
        $productsCount = (int) DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        // 3. P31-05 — product_category_current (Current catalog category breakdown)
        $categoriesRaw = DB::table('categories as c')
            ->leftJoin('products as p', function ($join) use ($tenantId) {
                $join->on('c.id', '=', 'p.category_id')
                    ->where('p.tenant_id', '=', $tenantId)
                    ->whereNull('p.deleted_at');
            })
            ->where('c.tenant_id', $tenantId)
            ->selectRaw("c.name as category_name, COUNT(p.id) as products_count")
            ->groupBy('c.id', 'c.name')
            ->get();

        $byCategoryCurrent = [];
        foreach ($categoriesRaw as $row) {
            $catName = trim((string) $row->category_name);
            $key = ($catName === '') ? 'SIN_CATEGORIA' : $catName;
            $byCategoryCurrent[$key] = (int) $row->products_count;
        }

        // 4. Order Lines Query (Historical snapshot in non-cancelled orders)
        $orderLinesQuery = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)
            ->where('oi.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED');

        $startUtc = $filterSet->getStartDateTimeUtcString();
        $endUtc = $filterSet->getEndDateTimeUtcString();

        if ($startUtc) {
            $orderLinesQuery->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $orderLinesQuery->where('o.created_at', '<=', $endUtc);
        }
        if ($filterSet->channel) {
            $orderLinesQuery->where('o.channel', $filterSet->channel);
        }
        if ($filterSet->paymentMethod) {
            $orderLinesQuery->where('o.payment_method', $filterSet->paymentMethod);
        }

        $linesStats = (clone $orderLinesQuery)->selectRaw("
            COUNT(DISTINCT CASE WHEN oi.product_id IS NOT NULL THEN oi.product_id END) as products_sold,
            COALESCE(SUM(oi.quantity), 0) as units_sold,
            COALESCE(SUM(oi.subtotal), 0) as product_revenue
        ")->first();

        $productsSold = (int) ($linesStats->products_sold ?? 0);
        $unitsSold = (int) ($linesStats->units_sold ?? 0);
        $productRevenue = (float) ($linesStats->product_revenue ?? 0.00);

        // Si no existen productos de catálogo ni ventas históricas en el scope
        if ($productsCount === 0 && $unitsSold === 0) {
            return new ProductsAnalyticsDTO(
                status: MetricDataStatus::NO_DATA,
                catalogSummary: [
                    'products_count' => 0,
                ],
                orderLinesSummary: [
                    'products_sold' => 0,
                    'units_sold' => 0,
                    'product_revenue' => 0.00,
                ],
                byCategoryCurrent: $byCategoryCurrent,
                topProducts: [],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: $timezone,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'products_analytics',
                    sourceOfTruth: 'products + order_items.subtotal',
                    calculationSource: 'GetProductsAnalyticsQuery (DIRECT_QUERY)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                message: 'No existen productos de catálogo ni líneas de venta para este tenant en el período seleccionado.'
            );
        }

        // 5. Top Products Ranking (Ordered by product_revenue)
        $topProductsRaw = (clone $orderLinesQuery)
            ->selectRaw("
                oi.product_id,
                oi.product_name,
                COALESCE(SUM(oi.quantity), 0) as units_sold,
                COALESCE(SUM(oi.subtotal), 0) as product_revenue
            ")
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('product_revenue')
            ->limit(10)
            ->get();

        $topProducts = [];
        foreach ($topProductsRaw as $row) {
            $productId = $row->product_id ? (int) $row->product_id : null;
            $productName = (string) $row->product_name;

            $isCurrentlyDeleted = false;
            $identityStatus = 'RESOLVED';

            if ($productId === null) {
                $identityStatus = 'PRODUCT_IDENTITY_UNRESOLVED';
            } else {
                $productRecord = DB::table('products')
                    ->where('id', $productId)
                    ->first(['deleted_at']);

                if ($productRecord && $productRecord->deleted_at !== null) {
                    $isCurrentlyDeleted = true;
                }
            }

            $topProducts[] = [
                'product_id' => $productId,
                'product_name' => $productName,
                'units_sold' => (int) $row->units_sold,
                'product_revenue' => round((float) $row->product_revenue, 2),
                'is_currently_deleted' => $isCurrentlyDeleted,
                'product_identity_status' => $identityStatus,
            ];
        }

        return new ProductsAnalyticsDTO(
            status: MetricDataStatus::KNOWN,
            catalogSummary: [
                'products_count' => $productsCount,
            ],
            orderLinesSummary: [
                'products_sold' => $productsSold,
                'units_sold' => $unitsSold,
                'product_revenue' => round($productRevenue, 2),
            ],
            byCategoryCurrent: $byCategoryCurrent,
            topProducts: $topProducts,
            period: [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            timezone: $timezone,
            currency: $currency,
            provenance: new MetricProvenanceDTO(
                metricName: 'products_analytics',
                sourceOfTruth: 'products (current catalog) + order_items.subtotal (historical lines)',
                calculationSource: 'GetProductsAnalyticsQuery (DIRECT_QUERY)',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            ),
            message: null
        );
    }
}
