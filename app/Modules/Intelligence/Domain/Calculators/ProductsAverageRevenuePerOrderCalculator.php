<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class ProductsAverageRevenuePerOrderCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly ProductsRevenueCalculator $revenueCalculator,
        private readonly ProductsOrdersCountCalculator $ordersCountCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $ordersCount = $this->ordersCountCalculator->calculate($tenantId, $period, $filters);
        if ($ordersCount <= 0) {
            return 0.00;
        }

        $revenue = $this->revenueCalculator->calculate($tenantId, $period, $filters);

        return round($revenue / $ordersCount, 2);
    }
}
