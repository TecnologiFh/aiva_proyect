<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Órdenes Canceladas (P8).
 * Utiliza la autoridad canónica de cancelación (cancelled_at, cancelled_by, cancellation_channel).
 */
class OperationsCancelledOrdersCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('cancelled_at');

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('cancelled_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('cancelled_at', '<=', $endUtc);
        }

        if (!empty($filters['cancelled_by'])) {
            $query->where('cancelled_by', $filters['cancelled_by']);
        }
        if (!empty($filters['cancellation_channel'])) {
            $query->where('cancellation_channel', $filters['cancellation_channel']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        return (int) $query->count();
    }
}
