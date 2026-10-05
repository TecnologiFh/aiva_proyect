<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class ProductsTopByRevenueCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 10;

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->leftJoin('products as p', function ($join) use ($tenantId) {
                $join->on('oi.product_id', '=', 'p.id')
                     ->where('p.tenant_id', '=', $tenantId);
            })
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
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
            $query->where('p.category_id', (int) $filters['category_id']);
        }
        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }

        $rows = $query->selectRaw("
            oi.product_id,
            COALESCE(MAX(p.name), MAX(oi.product_name)) as product_name,
            COALESCE(MAX(p.sku), 'N/A') as sku,
            MAX(p.category_id) as category_id,
            COALESCE(MAX(c.name), 'Sin categoría') as category_name,
            COALESCE(SUM(oi.quantity), 0) as quantity,
            COUNT(DISTINCT oi.order_id) as orders_count,
            COALESCE(SUM(oi.subtotal), 0) as revenue
        ")
        ->groupBy('oi.product_id')
        ->orderByDesc('revenue')
        ->limit($limit)
        ->get();

        return $rows->map(fn($row) => [
            'product_id' => (int) $row->product_id,
            'product_name' => (string) $row->product_name,
            'sku' => (string) $row->sku,
            'category_id' => $row->category_id ? (int) $row->category_id : null,
            'category_name' => (string) $row->category_name,
            'quantity' => (int) $row->quantity,
            'orders_count' => (int) $row->orders_count,
            'revenue' => round((float) $row->revenue, 2),
        ])->all();
    }
}
