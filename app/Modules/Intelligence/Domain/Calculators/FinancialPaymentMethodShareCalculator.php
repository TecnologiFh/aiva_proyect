<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class FinancialPaymentMethodShareCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly FinancialPaymentMethodsCalculator $methodsCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $targetMethod = !empty($filters['payment_method']) ? strtoupper(trim((string) $filters['payment_method'])) : null;
        if (!$targetMethod) {
            return 0.00;
        }

        $breakdown = $this->methodsCalculator->calculate($tenantId, $period, []);
        foreach ($breakdown as $row) {
            if ($row['method'] === $targetMethod) {
                return (float) $row['share'];
            }
        }

        return 0.00;
    }
}
