<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class FinancialPaidOrdersCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'PAID');

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

        return (int) $query->count();
    }
}
