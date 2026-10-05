<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Tasa de Cancelación de Órdenes (P8).
 * Retorna el ratio porcentual canónico: (órdenes canceladas / total órdenes) * 100.
 */
class OperationsCancellationRateCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $baseQuery = DB::table('orders')->where('tenant_id', $tenantId);

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $baseQuery->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $baseQuery->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $baseQuery->where('channel', $filters['channel']);
        }
        if (!empty($filters['order_type'])) {
            $baseQuery->where('order_type', $filters['order_type']);
        }

        $totalOrders = (int) (clone $baseQuery)->count();
        if ($totalOrders === 0) {
            return 0.00;
        }

        $cancelledOrders = (int) (clone $baseQuery)
            ->where(function ($q) {
                $q->where('status', 'CANCELLED')
                  ->orWhere('fulfillment_status', 'CANCELLED')
                  ->orWhereNotNull('cancelled_at');
            })
            ->count();

        return round(($cancelledOrders / $totalOrders) * 100, 2);
    }
}
