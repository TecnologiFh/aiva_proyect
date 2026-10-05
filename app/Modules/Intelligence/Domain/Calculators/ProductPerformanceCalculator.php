<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\PeriodComparison;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use InvalidArgumentException;

class ProductPerformanceCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly ProductsRevenueCalculator $revenueCalculator,
        private readonly ProductsUnitsSoldCalculator $unitsCalculator,
        private readonly ProductsOrdersCountCalculator $ordersCalculator,
        private readonly ProductsSalesShareCalculator $shareCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        if (empty($filters['product_id'])) {
            throw new InvalidArgumentException("Filtro 'product_id' es obligatorio para 'products.performance'.");
        }

        $productId = (int) $filters['product_id'];

        $product = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.id', $productId)
            ->select('p.id', 'p.name', 'p.sku', 'p.base_price', 'p.category_id', 'c.name as category_name')
            ->first();

        if (!$product) {
            throw new NotFoundHttpException("Producto #{$productId} no encontrado o no pertenece a este tenant.");
        }

        $revenue = $this->revenueCalculator->calculate($tenantId, $period, ['product_id' => $productId]);
        $units = $this->unitsCalculator->calculate($tenantId, $period, ['product_id' => $productId]);
        $ordersCount = $this->ordersCalculator->calculate($tenantId, $period, ['product_id' => $productId]);
        $salesShare = $this->shareCalculator->calculate($tenantId, $period, ['product_id' => $productId]);
        $aov = $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.00;

        // Comparación con periodo anterior si existe
        $previousPeriod = $period->getPreviousPeriod();
        $comparison = null;
        if ($previousPeriod !== null) {
            $prevRevenue = $this->revenueCalculator->calculate($tenantId, $previousPeriod, ['product_id' => $productId]);
            $comparison = new PeriodComparison(
                currentValue: $revenue,
                previousValue: $prevRevenue,
                previousPeriod: $previousPeriod->toArray()
            );
        }

        return [
            'product_id' => (int) $product->id,
            'name' => (string) $product->name,
            'sku' => (string) ($product->sku ?? 'N/A'),
            'current_base_price' => round((float) $product->base_price, 2),
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'category_name' => (string) ($product->category_name ?? 'Sin categoría'),
            'units_sold' => $units,
            'revenue' => $revenue,
            'orders_count' => $ordersCount,
            'average_revenue_per_order' => $aov,
            'sales_share_percentage' => $salesShare,
            'revenue_comparison' => $comparison ? $comparison->toArray() : null,
        ];
    }
}
