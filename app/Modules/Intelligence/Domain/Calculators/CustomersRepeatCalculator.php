<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class CustomersRepeatCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
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

        $sub = $query->selectRaw("COALESCE(NULLIF(customer_phone_canonical, ''), customer_phone) as cust_phone, COUNT(id) as ord_count")
            ->groupBy('cust_phone')
            ->havingRaw('COUNT(id) >= 2');

        return (int) DB::table(DB::raw("({$sub->toSql()}) as repeat_customers"))
            ->mergeBindings($query)
            ->count();
    }
}
