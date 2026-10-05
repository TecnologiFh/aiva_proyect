<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class ProductsUnitsSoldCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
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

        if (!empty($filters['product_id'])) {
            $query->where('oi.product_id', (int) $filters['product_id']);
        }
        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }
        if (!empty($filters['payment_method'])) {
            $query->where('o.payment_method', $filters['payment_method']);
        }

        return (int) $query->sum('oi.quantity');
    }
}
