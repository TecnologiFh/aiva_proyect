<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class CustomersMonetaryValueCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly CustomersActiveCalculator $activeCalculator,
        private readonly SalesRevenueCalculator $revenueCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $activeCustomers = $this->activeCalculator->calculate($tenantId, $period, $filters);
        if ($activeCustomers <= 0) {
            return 0.00;
        }

        $revenue = $this->revenueCalculator->calculate($tenantId, $period, $filters);

        return round($revenue / $activeCustomers, 2);
    }
}
