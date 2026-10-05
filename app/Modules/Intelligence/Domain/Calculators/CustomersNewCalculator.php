<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class CustomersNewCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('customers')->where('tenant_id', $tenantId);

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['preferred_channel'])) {
            $query->where('preferred_channel', $filters['preferred_channel']);
        }
        if (!empty($filters['tag'])) {
            $query->where('tag', $filters['tag']);
        }

        return (int) $query->count();
    }
}
