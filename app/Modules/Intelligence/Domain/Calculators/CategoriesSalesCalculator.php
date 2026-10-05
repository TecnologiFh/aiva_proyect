<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class CategoriesSalesCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', function ($join) use ($tenantId) {
                $join->on('oi.product_id', '=', 'p.id')
                     ->where('p.tenant_id', '=', $tenantId);
            })
            ->join('categories as c', function ($join) use ($tenantId) {
                $join->on('p.category_id', '=', 'c.id')
                     ->where('c.tenant_id', '=', $tenantId);
            })
            ->where('oi.tenant_id', $tenantId)
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED');

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('o.created_at', '<=', $endUtc);
        }

        if (!empty($filters['category_id'])) {
            $query->where('c.id', (int) $filters['category_id']);
        }
        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }

        $rows = $query->selectRaw("
            c.id as category_id,
            c.name as category_name,
            c.slug as category_slug,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COUNT(DISTINCT oi.order_id) as orders_count,
            COALESCE(SUM(oi.subtotal), 0) as revenue
        ")
        ->groupBy('c.id', 'c.name', 'c.slug')
        ->orderByDesc('revenue')
        ->get();

        return $rows->map(fn($row) => [
            'category_id' => (int) $row->category_id,
            'category_name' => (string) $row->category_name,
            'category_slug' => (string) $row->category_slug,
            'quantity' => (int) $row->quantity,
            'orders_count' => (int) $row->orders_count,
            'revenue' => round((float) $row->revenue, 2),
        ])->all();
    }
}
