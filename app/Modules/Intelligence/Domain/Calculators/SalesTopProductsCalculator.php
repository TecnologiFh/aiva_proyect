<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class SalesTopProductsCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $limit = isset($filters['limit']) ? min(100, max(1, (int) $filters['limit'])) : 10;
        $orderBy = (isset($filters['by']) && strtolower($filters['by']) === 'revenue') ? 'revenue' : 'quantity';

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
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

        $results = $query
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as quantity, SUM(oi.subtotal) as revenue, COUNT(DISTINCT o.id) as orders_count')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            return [
                'product_id' => $row->product_id ? (int) $row->product_id : null,
                'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
            ];
        })->toArray();
    }
}
