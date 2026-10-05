<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InventoryStockCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly InventoryStockByProductCalculator $stockByProductCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $productsStock = $this->stockByProductCalculator->calculate($tenantId, $period, $filters);

        if (!empty($filters['product_id'])) {
            $productId = (int) $filters['product_id'];
            $found = collect($productsStock)->firstWhere('product_id', $productId);
            if (!$found) {
                throw new NotFoundHttpException("Producto #{$productId} no encontrado o no pertenece a este tenant.");
            }
            return $found;
        }

        return $productsStock;
    }
}
