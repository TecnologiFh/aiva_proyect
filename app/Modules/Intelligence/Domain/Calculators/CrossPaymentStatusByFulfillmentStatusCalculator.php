<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Cruce Observacional entre Estado Financiero y Estado Logístico (P9).
 * Grain: ORDER.
 */
class CrossPaymentStatusByFulfillmentStatusCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        $query = DB::table('orders')->where('tenant_id', $tenantId);

        if ($startUtc) {
            $query->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        $rows = $query->selectRaw("
            payment_status,
            fulfillment_status,
            COUNT(id) as orders_count,
            COALESCE(SUM(total_amount), 0) as total_amount
        ")
        ->groupBy('payment_status', 'fulfillment_status')
        ->orderByDesc('orders_count')
        ->get();

        $totalOrders = (int) $rows->sum('orders_count');

        $matrix = [];
        foreach ($rows as $r) {
            $count = (int) $r->orders_count;
            $share = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 2) : 0.00;

            $matrix[] = [
                'payment_status' => (string) $r->payment_status,
                'fulfillment_status' => (string) $r->fulfillment_status,
                'orders_count' => $count,
                'total_amount' => round((float) $r->total_amount, 2),
                'percentage' => $share,
            ];
        }

        return $matrix;
    }
}
