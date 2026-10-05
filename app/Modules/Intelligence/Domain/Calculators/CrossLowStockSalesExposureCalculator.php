<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Exposición Comercial de Productos con Stock Crítico (P9).
 * Grain: PRODUCT.
 * Identifica productos con ventas activas en el período cuya posición actual de stock está comprometida.
 */
class CrossLowStockSalesExposureCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly ?CrossProductSalesWithCurrentStockCalculator $productSalesStockCalc = null
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $calculator = $this->productSalesStockCalc ?? new CrossProductSalesWithCurrentStockCalculator();
        $allProducts = $calculator->calculate($tenantId, $period, $filters);

        // Filtrar productos que tuvieron ventas activas y que están en stock bajo o agotados
        $exposed = [];
        foreach ($allProducts as $p) {
            $hasSales = $p['units_sold'] > 0;
            $isCritical = $p['is_low_stock'] || $p['is_out_of_stock'];

            if ($hasSales && $isCritical) {
                $status = $p['is_out_of_stock'] ? 'OUT_OF_STOCK' : 'LOW_STOCK';
                $exposed[] = array_merge($p, [
                    'stock_exposure_status' => $status,
                ]);
            }
        }

        usort($exposed, fn ($a, $b) => $b['sales_revenue'] <=> $a['sales_revenue']);

        return $exposed;
    }
}
