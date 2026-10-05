<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Analytics\Services\FinancialAllocationTruthEngineService;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\DTOs\SalesAnalyticsDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetSalesAnalyticsQuery
{
    public function __construct(
        private readonly FinancialAllocationTruthEngineService $truthEngine
    ) {}

    /**
     * Ejecutar consulta analítica de ventas para un tenant y filtro de período.
     * Estrictamente READ-ONLY, tenant-scoped, timezone-aware y currency-aware.
     */
    public function execute(AnalyticsFilterSet $filterSet): SalesAnalyticsDTO
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

        // Si la zona horaria no pudo ser resuelta válidamente (NULL, vacía o inválida en tenants.timezone)
        // se prohíbe el uso de sustitutos silenciosos (UTC / America/Lima) y se rechaza la generación
        // de series temporales con estado explícito INSUFFICIENT_DATA.
        if (!$filterSet->isTimezoneResolved || $timezone === AnalyticsFilterSet::TIMEZONE_UNRESOLVED) {
            return new SalesAnalyticsDTO(
                status: MetricDataStatus::INSUFFICIENT_DATA,
                metrics: [
                    'gross_sales' => 0.00,
                    'discounts' => 0.00,
                    'net_sales' => 0.00,
                    'paid_sales' => 0.00,
                    'pending_sales' => 0.00,
                    'refunds' => 0.00,
                    'orders_count' => 0,
                    'average_order_value' => 0.00,
                    'units_sold' => 0,
                    'cancelled_sales' => 0.00,
                ],
                dimensions: [
                    'sales_by_day' => [],
                    'sales_by_hour' => [],
                    'sales_by_payment_method' => [],
                    'sales_by_channel' => [],
                    'sales_by_order_status' => [],
                    'top_products' => [],
                ],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'sales_analytics',
                    sourceOfTruth: 'tenants.timezone',
                    calculationSource: 'GetSalesAnalyticsQuery',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                    currency: $currency
                ),
                message: 'Zona horaria del tenant no disponible o inválida (TIMEZONE_UNRESOLVED). Se prohíbe fallback silencioso a UTC o valores arbitrarios.'
            );
        }

        // 1. Verificar si el tenant posee órdenes registradas históricamente
        $totalTenantOrders = DB::table('orders')->where('tenant_id', $tenantId)->count();
        if ($totalTenantOrders === 0) {
            return new SalesAnalyticsDTO(
                status: MetricDataStatus::NO_DATA,
                metrics: [
                    'gross_sales' => 0.00,
                    'discounts' => 0.00,
                    'net_sales' => 0.00,
                    'paid_sales' => 0.00,
                    'pending_sales' => 0.00,
                    'refunds' => 0.00,
                    'orders_count' => 0,
                    'average_order_value' => 0.00,
                    'units_sold' => 0,
                    'cancelled_sales' => 0.00,
                ],
                dimensions: [
                    'sales_by_day' => [],
                    'sales_by_hour' => [],
                    'sales_by_payment_method' => [],
                    'sales_by_channel' => [],
                    'sales_by_order_status' => [],
                    'top_products' => [],
                ],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: $timezone,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'sales_analytics',
                    sourceOfTruth: 'orders + order_items + payment_allocations + payment_evidences + refunds',
                    calculationSource: 'FinancialAllocationTruthEngineService + GetSalesAnalyticsQuery',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                message: 'No existen registros de ventas ni órdenes para este tenant.'
            );
        }

        // 2. Consumir la verdad financiera única (FinancialAllocationTruthEngineService)
        // Garantiza cero fórmulas duplicadas para gross_sales, net_sales, paid_sales, etc.
        $financialTruth = $this->truthEngine->calculateFinancialMetrics($tenantId, $dateFrom, $dateTo);
        $summary = $financialTruth['financial_summary'];
        $orderCounts = $financialTruth['order_counts'];

        $ordersCount = (int) $orderCounts['total_orders'];

        // Si el período filtrado no arrojó órdenes
        if ($ordersCount === 0) {
            return new SalesAnalyticsDTO(
                status: MetricDataStatus::NO_DATA,
                metrics: [
                    'gross_sales' => 0.00,
                    'discounts' => 0.00,
                    'net_sales' => 0.00,
                    'paid_sales' => 0.00,
                    'pending_sales' => 0.00,
                    'refunds' => 0.00,
                    'orders_count' => 0,
                    'average_order_value' => 0.00,
                    'units_sold' => 0,
                    'cancelled_sales' => 0.00,
                ],
                dimensions: [
                    'sales_by_day' => [],
                    'sales_by_hour' => [],
                    'sales_by_payment_method' => [],
                    'sales_by_channel' => [],
                    'sales_by_order_status' => [],
                    'top_products' => [],
                ],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: $timezone,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'sales_analytics',
                    sourceOfTruth: 'orders + order_items + payment_allocations + payment_evidences + refunds',
                    calculationSource: 'FinancialAllocationTruthEngineService + GetSalesAnalyticsQuery',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                message: 'No se encontraron órdenes registradas en el período seleccionado.'
            );
        }

        // 3. Consulta base tenant-scoped y con filtro de fecha para órdenes
        $baseOrdersQuery = DB::table('orders')->where('tenant_id', $tenantId);
        if ($dateFrom) {
            $baseOrdersQuery->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $baseOrdersQuery->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        if ($filterSet->channel) {
            $baseOrdersQuery->where('channel', $filterSet->channel);
        }
        if ($filterSet->paymentMethod) {
            $baseOrdersQuery->where('payment_method', $filterSet->paymentMethod);
        }

        // 4. Métricas operativas adicionales
        // Ventas canceladas
        $cancelledSales = (float) (clone $baseOrdersQuery)
            ->where('status', 'CANCELLED')
            ->sum('total_amount');

        // Unidades vendidas (solo órdenes no canceladas)
        $unitsSoldQuery = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED');

        if ($dateFrom) {
            $unitsSoldQuery->where('o.created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $unitsSoldQuery->where('o.created_at', '<=', $dateTo . ' 23:59:59');
        }
        $unitsSold = (int) $unitsSoldQuery->sum('oi.quantity');

        // 5. Dimensiones analíticas
        // 5.1 Ventas por día (en timezone del tenant)
        $ordersForDimensions = (clone $baseOrdersQuery)->get(['total_amount', 'status', 'created_at', 'payment_method', 'channel']);

        $salesByDay = [];
        $salesByHour = array_fill(0, 24, ['hour' => 0, 'sales' => 0.00, 'count' => 0]);
        for ($h = 0; $h < 24; $h++) {
            $salesByHour[$h]['hour'] = $h;
        }

        $salesByPaymentMethod = [];
        $salesByChannel = [];
        $salesByOrderStatus = [];

        foreach ($ordersForDimensions as $order) {
            // Conversión estricta a timezone del tenant
            $localDt = Carbon::parse($order->created_at, 'UTC')->setTimezone($timezone);
            $dayKey = $localDt->toDateString();
            $hourKey = (int) $localDt->format('G');

            $amount = (float) $order->total_amount;
            $isCancelled = ($order->status === 'CANCELLED');

            // Por día
            if (!isset($salesByDay[$dayKey])) {
                $salesByDay[$dayKey] = ['date' => $dayKey, 'sales' => 0.00, 'orders_count' => 0];
            }
            $salesByDay[$dayKey]['orders_count']++;
            if (!$isCancelled) {
                $salesByDay[$dayKey]['sales'] = round($salesByDay[$dayKey]['sales'] + $amount, 2);
            }

            // Por hora
            $salesByHour[$hourKey]['count']++;
            if (!$isCancelled) {
                $salesByHour[$hourKey]['sales'] = round($salesByHour[$hourKey]['sales'] + $amount, 2);
            }

            // Por método de pago
            $pm = $order->payment_method ?? 'UNKNOWN';
            if (!isset($salesByPaymentMethod[$pm])) {
                $salesByPaymentMethod[$pm] = ['method' => $pm, 'total' => 0.00, 'count' => 0];
            }
            $salesByPaymentMethod[$pm]['count']++;
            if (!$isCancelled) {
                $salesByPaymentMethod[$pm]['total'] = round($salesByPaymentMethod[$pm]['total'] + $amount, 2);
            }

            // Por canal
            $ch = $order->channel ?? 'UNKNOWN';
            if (!isset($salesByChannel[$ch])) {
                $salesByChannel[$ch] = ['channel' => $ch, 'total' => 0.00, 'count' => 0];
            }
            $salesByChannel[$ch]['count']++;
            if (!$isCancelled) {
                $salesByChannel[$ch]['total'] = round($salesByChannel[$ch]['total'] + $amount, 2);
            }

            // Por estado comercial
            $st = $order->status ?? 'UNKNOWN';
            if (!isset($salesByOrderStatus[$st])) {
                $salesByOrderStatus[$st] = ['status' => $st, 'count' => 0, 'total' => 0.00];
            }
            $salesByOrderStatus[$st]['count']++;
            $salesByOrderStatus[$st]['total'] = round($salesByOrderStatus[$st]['total'] + $amount, 2);
        }

        // Ordenar ventas por día cronológicamente
        ksort($salesByDay);
        $salesByDay = array_values($salesByDay);

        // 5.2 Top Productos (solo órdenes no canceladas, respetando catálogo AG-46)
        $topProductsQuery = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED');

        if ($dateFrom) {
            $topProductsQuery->where('o.created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $topProductsQuery->where('o.created_at', '<=', $dateTo . ' 23:59:59');
        }

        $topProducts = $topProductsQuery
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'product_id' => $p->product_id,
                'product_name' => $p->product_name,
                'total_qty' => (int) $p->total_qty,
                'total_revenue' => round((float) $p->total_revenue, 2),
            ])
            ->toArray();

        return new SalesAnalyticsDTO(
            status: MetricDataStatus::KNOWN,
            metrics: [
                'gross_sales' => (float) $summary['gross_sales'],
                'discounts' => (float) $summary['discounts'],
                'net_sales' => (float) $summary['net_sales'],
                'paid_sales' => (float) $summary['paid_sales'],
                'pending_sales' => (float) $summary['pending_sales'],
                'refunds' => (float) $summary['refunds'],
                'orders_count' => $ordersCount,
                'average_order_value' => (float) $summary['avg_ticket'],
                'units_sold' => $unitsSold,
                'cancelled_sales' => round($cancelledSales, 2),
            ],
            dimensions: [
                'sales_by_day' => $salesByDay,
                'sales_by_hour' => array_values($salesByHour),
                'sales_by_payment_method' => array_values($salesByPaymentMethod),
                'sales_by_channel' => array_values($salesByChannel),
                'sales_by_order_status' => array_values($salesByOrderStatus),
                'top_products' => $topProducts,
            ],
            period: [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            timezone: $timezone,
            currency: $currency,
            provenance: new MetricProvenanceDTO(
                metricName: 'sales_analytics',
                sourceOfTruth: 'orders + order_items + payment_allocations + payment_evidences + refunds',
                calculationSource: 'FinancialAllocationTruthEngineService + GetSalesAnalyticsQuery',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            )
        );
    }
}
