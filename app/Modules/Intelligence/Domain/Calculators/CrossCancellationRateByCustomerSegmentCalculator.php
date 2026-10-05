<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Tasa de Cancelación por Segmento de Cliente (P9).
 * Grain: ORDER.
 * Aplica la semántica ratificada Modelo A (Cohorte de órdenes creadas en el período).
 */
class CrossCancellationRateByCustomerSegmentCalculator implements MetricCalculatorInterface
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

        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }

        $rows = $query->selectRaw("
            COALESCE(c.tag, 'UNKNOWN') as segment,
            COUNT(o.id) as total_orders,
            COUNT(CASE WHEN o.status = 'CANCELLED' OR o.fulfillment_status = 'CANCELLED' OR o.cancelled_at IS NOT NULL THEN 1 END) as cancelled_orders
        ")
        ->groupBy(DB::raw("COALESCE(c.tag, 'UNKNOWN')"))
        ->orderByDesc('total_orders')
        ->get();

        $result = [];
        foreach ($rows as $r) {
            $total = (int) $r->total_orders;
            $cancelled = (int) $r->cancelled_orders;
            $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.00;

            $result[] = [
                'segment' => (string) $r->segment,
                'total_orders' => $total,
                'cancelled_orders' => $cancelled,
                'cancellation_rate' => $rate,
            ];
        }

        return $result;
    }
}
