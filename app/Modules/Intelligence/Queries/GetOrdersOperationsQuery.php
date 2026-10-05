<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\DTOs\OrdersOperationsAnalyticsDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetOrdersOperationsQuery
{
    public function __construct(
        private readonly ?GetOrdersSummaryQuery $summaryQuery = null,
        private readonly ?GetOrdersStatusQuery $statusQuery = null
    ) {}

    /**
     * Ejecuta la consulta de Operaciones y Timeline, retornando el DTO canónico completo.
     * Si la zona horaria no está resuelta, degrada estrictamente la serie temporal a INSUFFICIENT_DATA.
     */
    public function execute(AnalyticsFilterSet $filterSet): OrdersOperationsAnalyticsDTO
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

        $summaryRunner = $this->summaryQuery ?? new GetOrdersSummaryQuery();
        $statusRunner = $this->statusQuery ?? new GetOrdersStatusQuery();

        $summaryData = $summaryRunner->execute($filterSet);
        $statusData = $statusRunner->execute($filterSet);

        // 1. Base Query para métricas operativas de fulfillment
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

        // 2. Contadores operativos de fulfillment (exclusivamente sobre orders.fulfillment_status)
        $opsStats = (clone $baseOrdersQuery)
            ->selectRaw("
                COUNT(CASE WHEN fulfillment_status = 'PENDING' THEN 1 END) as pending_fulfillment,
                COUNT(CASE WHEN fulfillment_status = 'PREPARING' THEN 1 END) as preparing_orders,
                COUNT(CASE WHEN fulfillment_status = 'READY' THEN 1 END) as ready_orders,
                COUNT(CASE WHEN fulfillment_status = 'DELIVERED' THEN 1 END) as delivered_orders
            ")
            ->first();

        $operations = [
            'pending_fulfillment' => (int) ($opsStats->pending_fulfillment ?? 0),
            'preparing_orders' => (int) ($opsStats->preparing_orders ?? 0),
            'ready_orders' => (int) ($opsStats->ready_orders ?? 0),
            'delivered_orders' => (int) ($opsStats->delivered_orders ?? 0),
        ];

        // 3. Evaluación de Timezone para Series Temporales (Timeline)
        // Regla: Si timezone es TIMEZONE_UNRESOLVED, NO inventar fechas ni hacer fallback silencioso.
        $isTimezoneResolved = $filterSet->isTimezoneResolved && ($timezone !== AnalyticsFilterSet::TIMEZONE_UNRESOLVED);

        $byDay = [];
        $cancellationsByDay = [];
        $overallStatus = $summaryData['status'];

        if (!$isTimezoneResolved) {
            // Degradar la serie temporal por falta de zona horaria certificada
            $overallStatus = MetricDataStatus::INSUFFICIENT_DATA;
            $message = 'Zona horaria del tenant no disponible o inválida (TIMEZONE_UNRESOLVED). Series temporales degradadas a INSUFFICIENT_DATA.';
        } elseif ($summaryData['status'] === MetricDataStatus::NO_DATA) {
            $overallStatus = MetricDataStatus::NO_DATA;
            $message = 'No existen pedidos para el período consultado.';
        } else {
            // Timezone resuelto y órdenes existentes: Calcular timeline con timezone local del tenant
            $ordersForTimeline = (clone $baseOrdersQuery)
                ->get(['created_at', 'status', 'channel']);

            $daysMap = [];
            foreach ($ordersForTimeline as $ord) {
                $localDate = Carbon::parse($ord->created_at, 'UTC')->setTimezone($timezone)->toDateString();
                $isCancelled = ($ord->status === 'CANCELLED');

                if (!isset($daysMap[$localDate])) {
                    $daysMap[$localDate] = [
                        'date' => $localDate,
                        'orders_count' => 0,
                        'cancelled_count' => 0,
                    ];
                }
                $daysMap[$localDate]['orders_count']++;
                if ($isCancelled) {
                    $daysMap[$localDate]['cancelled_count']++;
                }
            }

            ksort($daysMap);
            $byDay = array_values($daysMap);

            foreach ($byDay as $item) {
                if ($item['cancelled_count'] > 0) {
                    $cancellationsByDay[] = [
                        'date' => $item['date'],
                        'count' => $item['cancelled_count'],
                    ];
                }
            }
            $message = $summaryData['message'] ?? null;
        }

        // Desglose formateado de canales para DTO
        $byChannelFormatted = [];
        foreach ($summaryData['channel_breakdown'] as $channelName => $count) {
            $byChannelFormatted[] = [
                'channel' => $channelName,
                'count' => $count,
            ];
        }

        $cancellations = [
            'total_cancelled' => $summaryData['metrics']['cancelled_orders'] ?? 0,
            'cancellations_by_channel' => $summaryData['cancellations_by_channel'] ?? [],
            'cancellations_by_day' => $cancellationsByDay,
        ];

        return new OrdersOperationsAnalyticsDTO(
            status: $overallStatus,
            summary: $summaryData['metrics'],
            byStatus: $statusData['orders_by_status'],
            byFulfillmentStatus: $statusData['orders_by_fulfillment_status'],
            byDay: $byDay,
            byChannel: $byChannelFormatted,
            cancellations: $cancellations,
            operations: $operations,
            period: [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            timezone: $timezone,
            currency: $currency,
            provenance: new MetricProvenanceDTO(
                metricName: 'orders_and_operations_analytics',
                sourceOfTruth: 'orders + order_items',
                calculationSource: 'OrdersOperationsIntelligence (DIRECT_QUERY)',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            ),
            message: $message
        );
    }
}
