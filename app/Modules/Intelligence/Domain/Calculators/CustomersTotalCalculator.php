<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class CustomersTotalCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $query = DB::table('customers')->where('tenant_id', $tenantId);

        if (!empty($filters['tag'])) {
            $query->where('tag', $filters['tag']);
        }
        if (!empty($filters['preferred_channel'])) {
            $query->where('preferred_channel', $filters['preferred_channel']);
        }

        return (int) $query->count();
    }
}
