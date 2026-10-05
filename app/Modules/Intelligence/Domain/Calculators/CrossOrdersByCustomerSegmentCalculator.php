<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Distribución de Órdenes por Segmento de Cliente (P9).
 * Grain: ORDER.
 */
class CrossOrdersByCustomerSegmentCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        $customersSub = DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->selectRaw('phone, MAX(tag) as tag')
            ->groupBy('phone');

        $query = DB::table('orders as o')
            ->leftJoinSub($customersSub, 'c', function ($join) {
                $join->on('c.phone', '=', 'o.customer_phone');
            })
            ->where('o.tenant_id', $tenantId);

        if ($startUtc) {
            $query->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('o.created_at', '<=', $endUtc);
        }

        if (!empty($filters['status'])) {
            $query->where('o.status', $filters['status']);
        }
        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }

        $rows = $query->selectRaw("
            COALESCE(c.tag, 'UNKNOWN') as segment,
            COUNT(o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as total_volume
        ")
        ->groupBy(DB::raw("COALESCE(c.tag, 'UNKNOWN')"))
        ->orderByDesc('orders_count')
        ->get();

        $totalOrders = (int) $rows->sum('orders_count');

        $result = [];
        foreach ($rows as $r) {
            $count = (int) $r->orders_count;
            $pct = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 2) : 0.00;

            $result[] = [
                'segment' => (string) $r->segment,
                'orders_count' => $count,
                'total_volume' => round((float) $r->total_volume, 2),
                'percentage' => $pct,
            ];
        }

        return $result;
    }
}
