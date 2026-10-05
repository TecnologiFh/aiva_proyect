<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class FinancialAveragePaymentCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly FinancialCollectedRevenueCalculator $collectedRevenueCalculator,
        private readonly FinancialPaymentCountCalculator $paymentCountCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $revenue = $this->collectedRevenueCalculator->calculate($tenantId, $period, $filters);
        $count = $this->paymentCountCalculator->calculate($tenantId, $period, $filters);

        if ($count <= 0) {
            return 0.00;
        }

        return round($revenue / $count, 2);
    }
}
