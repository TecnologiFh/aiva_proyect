<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Illuminate\Support\Facades\DB;

class GetOrdersSummaryQuery
{
    /**
     * Ejecuta la consulta de resumen de pedidos (Orders Summary).
     * Estrictamente READ-ONLY, tenant-scoped, determinista y auditable.
     */
    public function execute(AnalyticsFilterSet $filterSet): array
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

        // 1. Base Query tenant-scoped
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

        // 2. Conteo de órdenes totales y desglose por status
        $ordersStats = (clone $baseOrdersQuery)
            ->selectRaw("
                COUNT(id) as orders_count,
                COUNT(CASE WHEN status = 'CANCELLED' THEN 1 END) as cancelled_orders,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN cancelled_at IS NOT NULL AND status != 'CANCELLED' THEN 1 END) as conflict_cancellations
            ")
            ->first();

        $ordersCount = (int) ($ordersStats->orders_count ?? 0);
        $cancelledOrders = (int) ($ordersStats->cancelled_orders ?? 0);
        $completedOrders = (int) ($ordersStats->completed_orders ?? 0);
        $conflictCancellations = (int) ($ordersStats->conflict_cancellations ?? 0);

        // Si no existen órdenes en el período
        if ($ordersCount === 0) {
            return [
                'status' => MetricDataStatus::NO_DATA,
                'metrics' => [
                    'orders_count' => 0,
                    'orders_with_items' => 0,
                    'cancelled_orders' => 0,
                    'completed_orders' => 0,
                    'average_items_per_order' => 0.00,
                ],
                'channel_breakdown' => [],
                'cancellations_by_channel' => [],
                'period' => [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                'timezone' => $timezone,
                'currency' => $currency,
                'provenance' => new MetricProvenanceDTO(
                    metricName: 'orders_summary',
                    sourceOfTruth: 'orders + order_items',
                    calculationSource: 'GetOrdersSummaryQuery (DIRECT_QUERY)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                'message' => 'No existen pedidos registrados para este tenant en el período seleccionado.',
            ];
        }

        // 3. Conteo de pedidos distintos con ítems (orders_with_items) y unidades totales (SUM quantity)
        $itemsQuery = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId);

        if ($dateFrom) {
            $itemsQuery->where('o.created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $itemsQuery->where('o.created_at', '<=', $dateTo . ' 23:59:59');
        }
        if ($filterSet->channel) {
            $itemsQuery->where('o.channel', $filterSet->channel);
        }
        if ($filterSet->paymentMethod) {
            $itemsQuery->where('o.payment_method', $filterSet->paymentMethod);
        }

        $itemsStats = $itemsQuery->selectRaw("
            COUNT(DISTINCT o.id) as orders_with_items,
            COALESCE(SUM(oi.quantity), 0) as total_units
        ")->first();

        $ordersWithItems = (int) ($itemsStats->orders_with_items ?? 0);
        $totalUnits = (int) ($itemsStats->total_units ?? 0);
        $averageItemsPerOrder = ($ordersCount > 0) ? round($totalUnits / $ordersCount, 2) : 0.00;

        // 4. Desglose por canal (orders_by_channel) y cancelaciones por canal
        // Normalización analítica estricta: NULL, vacíos o whitespace -> UNKNOWN
        $rawChannels = (clone $baseOrdersQuery)
            ->selectRaw("
                channel,
                COUNT(id) as count,
                COUNT(CASE WHEN status = 'CANCELLED' THEN 1 END) as cancelled_count
            ")
            ->groupBy('channel')
            ->get();

        $channelBreakdown = [];
        $cancellationsByChannel = [];

        foreach ($rawChannels as $row) {
            $ch = trim((string) $row->channel);
            $normalizedChannel = ($ch === '') ? 'UNKNOWN' : $ch;

            if (!isset($channelBreakdown[$normalizedChannel])) {
                $channelBreakdown[$normalizedChannel] = 0;
            }
            $channelBreakdown[$normalizedChannel] += (int) $row->count;

            if (!isset($cancellationsByChannel[$normalizedChannel])) {
                $cancellationsByChannel[$normalizedChannel] = 0;
            }
            $cancellationsByChannel[$normalizedChannel] += (int) $row->cancelled_count;
        }

        // 5. Estado de datos (DATA_CONFLICT si hay discrepancia de cancelación)
        $dataStatus = ($conflictCancellations > 0) ? MetricDataStatus::DATA_CONFLICT : MetricDataStatus::KNOWN;

        return [
            'status' => $dataStatus,
            'metrics' => [
                'orders_count' => $ordersCount,
                'orders_with_items' => $ordersWithItems,
                'cancelled_orders' => $cancelledOrders,
                'completed_orders' => $completedOrders,
                'average_items_per_order' => $averageItemsPerOrder,
            ],
            'channel_breakdown' => $channelBreakdown,
            'cancellations_by_channel' => $cancellationsByChannel,
            'period' => [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            'timezone' => $timezone,
            'currency' => $currency,
            'provenance' => new MetricProvenanceDTO(
                metricName: 'orders_summary',
                sourceOfTruth: 'orders + order_items',
                calculationSource: 'GetOrdersSummaryQuery (DIRECT_QUERY)',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            ),
            'message' => ($conflictCancellations > 0)
                ? "Se detectaron {$conflictCancellations} pedidos con timestamp cancelled_at pero status diferente a CANCELLED (DATA_CONFLICT)."
                : null,
        ];
    }
}
