<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class SalesRevenueCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

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
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Si se pide explícitamente gross: subtotal + delivery_fee, por defecto net: total_amount
        if (!empty($filters['type']) && $filters['type'] === 'gross') {
            $gross = (float) $query->selectRaw('COALESCE(SUM(COALESCE(subtotal, 0) + COALESCE(delivery_fee, 0)), 0) as gross_sum')->value('gross_sum');
            return round($gross, 2);
        }

        return round((float) $query->sum('total_amount'), 2);
    }
}

