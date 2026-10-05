<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use InvalidArgumentException;

class ProductsSalesShareCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly ProductsRevenueCalculator $productRevenueCalculator,
        private readonly SalesRevenueCalculator $totalRevenueCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        if (empty($filters['product_id'])) {
            throw new InvalidArgumentException("Filtro 'product_id' es obligatorio para calcular 'products.sales_share'.");
        }

        $totalRevenue = $this->totalRevenueCalculator->calculate($tenantId, $period, $filters);
        if ($totalRevenue <= 0.00) {
            return 0.00;
        }

        $productRevenue = $this->productRevenueCalculator->calculate($tenantId, $period, [
            'product_id' => (int) $filters['product_id']
        ]);

        return round(($productRevenue / $totalRevenue) * 100, 2);
    }
}
