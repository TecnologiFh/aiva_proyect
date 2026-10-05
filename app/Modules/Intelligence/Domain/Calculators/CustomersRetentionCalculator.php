<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class CustomersRetentionCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $prevPeriod = $period->getPreviousPeriod();
        if ($prevPeriod === null) {
            return 0.00;
        }

        $prevStart = $prevPeriod->getStartUtcString();
        $prevEnd = $prevPeriod->getEndUtcString();

        $currStart = $period->getStartUtcString();
        $currEnd = $period->getEndUtcString();

        // 1. Clientes activos en el periodo anterior
        $prevQuery = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

        if ($prevStart) {
            $prevQuery->where('created_at', '>=', $prevStart);
        }
        if ($prevEnd) {
            $prevQuery->where('created_at', '<=', $prevEnd);
        }

        $prevCustomers = $prevQuery->selectRaw("DISTINCT COALESCE(NULLIF(customer_phone_canonical, ''), customer_phone) as cust_phone")
            ->pluck('cust_phone')
            ->filter()
            ->all();

        $baseCount = count($prevCustomers);
        if ($baseCount === 0) {
            return 0.00;
        }

        // 2. Cuántos de esos clientes compraron de nuevo en el periodo actual
        $currQuery = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

        if ($currStart) {
            $currQuery->where('created_at', '>=', $currStart);
        }
        if ($currEnd) {
            $currQuery->where('created_at', '<=', $currEnd);
        }

        $retainedCount = $currQuery->where(function ($q) use ($prevCustomers) {
            $q->whereIn('customer_phone_canonical', $prevCustomers)
              ->orWhereIn('customer_phone', $prevCustomers);
        })->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(customer_phone_canonical, ''), customer_phone)) as retained")
          ->value('retained');

        return round(((int) $retainedCount / $baseCount) * 100, 2);
    }
}
