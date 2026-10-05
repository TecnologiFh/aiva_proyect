<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class InventoryOutOfStockCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly InventoryStockByProductCalculator $stockByProductCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $productsStock = $this->stockByProductCalculator->calculate($tenantId, $period, $filters);

        $outCount = 0;
        foreach ($productsStock as $p) {
            if ($p['is_out_of_stock']) {
                $outCount++;
            }
        }

        return $outCount;
    }
}
