<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class InventoryLowStockCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly InventoryStockByProductCalculator $stockByProductCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $productsStock = $this->stockByProductCalculator->calculate($tenantId, $period, $filters);

        $lowCount = 0;
        foreach ($productsStock as $p) {
            if ($p['is_low_stock']) {
                $lowCount++;
            }
        }

        return $lowCount;
    }
}
