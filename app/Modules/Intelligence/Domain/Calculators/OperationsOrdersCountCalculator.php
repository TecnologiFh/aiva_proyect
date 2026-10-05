<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Volumen Total de Órdenes Operativas (P8).
 */
class OperationsOrdersCountCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('orders')->where('tenant_id', $tenantId);

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['fulfillment_status'])) {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }
        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        return (int) $query->count();
    }
}
