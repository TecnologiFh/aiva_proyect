<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Ventas Históricas por Segmento de Cliente (P9).
 * Grain: ORDER_ITEM.
 * Relaciona orden -> cliente mediante teléfono canónico sin duplicar hechos monetarios.
 */
class CrossSalesByCustomerSegmentCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        // 1. Subquery deduplicada de clientes para evitar multiplicación si existen duplicados de teléfono
        $customersSub = DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->selectRaw('phone, MAX(tag) as tag')
            ->groupBy('phone');

        // 2. Query sobre order_items unidos a orders activas (status != 'CANCELLED')
        $query = DB::table('order_items as oi')
            ->join('orders as o', function ($join) use ($tenantId) {
                $join->on('o.id', '=', 'oi.order_id')
                     ->where('o.tenant_id', '=', $tenantId)
                     ->where('o.status', '!=', 'CANCELLED');
            })
            ->leftJoinSub($customersSub, 'c', function ($join) {
                $join->on('c.phone', '=', 'o.customer_phone');
            })
            ->where('oi.tenant_id', $tenantId);

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
            SUM(oi.subtotal) as total_revenue,
            SUM(oi.quantity) as total_units,
            COUNT(DISTINCT o.id) as distinct_orders
        ")
        ->groupBy(DB::raw("COALESCE(c.tag, 'UNKNOWN')"))
        ->orderByDesc('total_revenue')
        ->get();

        $overallRevenue = (float) $rows->sum('total_revenue');

        $result = [];
        foreach ($rows as $r) {
            $rev = round((float) $r->total_revenue, 2);
            $share = $overallRevenue > 0 ? round(($rev / $overallRevenue) * 100, 2) : 0.00;

            $result[] = [
                'segment' => (string) $r->segment,
                'revenue' => $rev,
                'units_sold' => (int) $r->total_units,
                'orders_count' => (int) $r->distinct_orders,
                'share' => $share,
            ];
        }

        return $result;
    }
}
