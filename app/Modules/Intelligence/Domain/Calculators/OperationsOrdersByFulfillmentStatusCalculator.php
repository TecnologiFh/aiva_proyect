<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Distribución por Estado de Fulfillment Físico (P8).
 */
class OperationsOrdersByFulfillmentStatusCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
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

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        $grouped = $query
            ->select('fulfillment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('fulfillment_status')
            ->orderByDesc('count')
            ->get();

        $total = (int) $grouped->sum('count');

        $result = [];
        foreach ($grouped as $row) {
            $fStatus = (string) $row->fulfillment_status;
            $count = (int) $row->count;
            $pct = $total > 0 ? round(($count / $total) * 100, 2) : 0.0;

            $result[] = [
                'fulfillment_status' => $fStatus,
                'count' => $count,
                'percentage' => $pct,
            ];
        }

        return $result;
    }
}
