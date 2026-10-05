<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class CustomersPurchaseFrequencyCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly CustomersActiveCalculator $activeCalculator,
        private readonly SalesOrdersCountCalculator $ordersCountCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $activeCustomers = $this->activeCalculator->calculate($tenantId, $period, $filters);
        if ($activeCustomers <= 0) {
            return 0.00;
        }

        $ordersCount = $this->ordersCountCalculator->calculate($tenantId, $period, $filters);

        return round($ordersCount / $activeCustomers, 2);
    }
}
