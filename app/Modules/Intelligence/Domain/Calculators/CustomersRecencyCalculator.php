<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomersRecencyCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $endUtc = $period->getEndUtcString();
        $referenceDate = $endUtc ? Carbon::parse($endUtc) : now('UTC');

        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

        if ($endUtc) {
            $query->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        // Subconsulta: última compra por cliente
        $sub = $query->selectRaw("
            COALESCE(NULLIF(customer_phone_canonical, ''), customer_phone) as cust_phone,
            MAX(created_at) as last_order_at
        ")->groupBy('cust_phone');

        $rows = DB::table(DB::raw("({$sub->toSql()}) as customer_recencies"))
            ->mergeBindings($query)
            ->get();

        if ($rows->isEmpty()) {
            return 0.00;
        }

        $totalDays = 0;
        foreach ($rows as $row) {
            $lastOrder = Carbon::parse($row->last_order_at);
            $diffDays = max(0, (int) $lastOrder->diffInDays($referenceDate));
            $totalDays += $diffDays;
        }

        return round($totalDays / $rows->count(), 2);
    }
}
