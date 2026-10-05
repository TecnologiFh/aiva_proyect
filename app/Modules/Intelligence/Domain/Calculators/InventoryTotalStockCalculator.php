<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

class InventoryTotalStockCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly InventoryStockByProductCalculator $stockByProductCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
    {
        $productsStock = $this->stockByProductCalculator->calculate($tenantId, $period, $filters);

        $total = 0;
        foreach ($productsStock as $p) {
            $total += (int) $p['current_stock'];
        }

        return $total;
    }
}
