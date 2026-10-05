<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Ingresos / Valor Comercial por Estado Operativo (P9).
 * Grain: ORDER.
 * Anti-Cartesian: Agregación analítica directa sobre la tabla raíz orders.
 */
class CrossRevenueByOrderStatusCalculator implements MetricCalculatorInterface
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
        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        $rows = $query->selectRaw("
            status,
            fulfillment_status,
            COUNT(id) as orders_count,
            COALESCE(SUM(total_amount), 0) as total_volume
        ")
        ->groupBy('status', 'fulfillment_status')
        ->orderByDesc('total_volume')
        ->get();

        $overallVolume = (float) $rows->sum('total_volume');
        $overallOrders = (int) $rows->sum('orders_count');

        $breakdown = [];
        foreach ($rows as $r) {
            $vol = round((float) $r->total_volume, 2);
            $cnt = (int) $r->orders_count;
            $share = $overallVolume > 0 ? round(($vol / $overallVolume) * 100, 2) : 0.00;

            $breakdown[] = [
                'status' => (string) $r->status,
                'fulfillment_status' => (string) $r->fulfillment_status,
                'orders_count' => $cnt,
                'total_volume' => $vol,
                'volume_share' => $share,
            ];
        }

        return [
            'total_volume' => round($overallVolume, 2),
            'total_orders' => $overallOrders,
            'breakdown' => $breakdown,
        ];
    }
}
