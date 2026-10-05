<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Illuminate\Support\Facades\DB;

class GetOrdersStatusQuery
{
    /**
     * Ejecuta la consulta de distribución de estados de pedidos (Comercial y Logístico).
     * Preserva literalmente todos los estados observados en BD sin whitelist eliminatoria.
     */
    public function execute(AnalyticsFilterSet $filterSet): array
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

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

        // 1. Verificar si existen órdenes en el período
        $totalOrders = (clone $baseOrdersQuery)->count();
        if ($totalOrders === 0) {
            return [
                'status' => MetricDataStatus::NO_DATA,
                'total_orders' => 0,
                'orders_by_status' => [],
                'orders_by_fulfillment_status' => [],
                'period' => [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                'timezone' => $timezone,
                'currency' => $currency,
                'provenance' => new MetricProvenanceDTO(
                    metricName: 'orders_status_distribution',
                    sourceOfTruth: 'orders.status + orders.fulfillment_status',
                    calculationSource: 'GetOrdersStatusQuery (DIRECT_QUERY)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                'message' => 'No existen pedidos para calcular la distribución de estados.',
            ];
        }

        // 2. Distribución de Estado Comercial (orders_by_status)
        $rawStatuses = (clone $baseOrdersQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $ordersByStatus = [];
        foreach ($rawStatuses as $row) {
            $st = trim((string) $row->status);
            $key = ($st === '') ? 'UNKNOWN' : $st;
            $ordersByStatus[$key] = (int) $row->count;
        }

        // 3. Distribución de Estado de Fulfillment (orders_by_fulfillment_status)
        // Ortogonalidad estricta: lee exclusivamente orders.fulfillment_status
        $rawFulfillment = (clone $baseOrdersQuery)
            ->selectRaw('fulfillment_status, COUNT(*) as count')
            ->groupBy('fulfillment_status')
            ->get();

        $ordersByFulfillmentStatus = [];
        foreach ($rawFulfillment as $row) {
            $fst = trim((string) $row->fulfillment_status);
            $key = ($fst === '') ? 'UNKNOWN' : $fst;
            $ordersByFulfillmentStatus[$key] = (int) $row->count;
        }

        return [
            'status' => MetricDataStatus::KNOWN,
            'total_orders' => $totalOrders,
            'orders_by_status' => $ordersByStatus,
            'orders_by_fulfillment_status' => $ordersByFulfillmentStatus,
            'period' => [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            'timezone' => $timezone,
            'currency' => $currency,
            'provenance' => new MetricProvenanceDTO(
                metricName: 'orders_status_distribution',
                sourceOfTruth: 'orders.status + orders.fulfillment_status',
                calculationSource: 'GetOrdersStatusQuery (DIRECT_QUERY)',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            ),
        ];
    }
}
