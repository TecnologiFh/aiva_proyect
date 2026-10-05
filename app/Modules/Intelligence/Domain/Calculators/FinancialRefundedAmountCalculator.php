<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class FinancialRefundedAmountCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $query = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'PROCESSED');

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where(DB::raw('COALESCE(processed_at, created_at)'), '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where(DB::raw('COALESCE(processed_at, created_at)'), '<=', $endUtc);
        }

        if (!empty($filters['refund_method'])) {
            $query->where('refund_method', $filters['refund_method']);
        }

        return round((float) $query->sum('amount'), 2);
    }
}
