<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Ventas de Producto con Posición de Stock Actual (P9).
 * Grain: PRODUCT.
 * Combina ventas del período (order_items) con la autoridad de stock actual P6.
 * Invariante: CURRENT STOCK != HISTORICAL STOCK.
 */
class CrossProductSalesWithCurrentStockCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        // 1. Subquery preagregada de ventas históricas en el período (order_items + orders)
        $salesSub = DB::table('order_items as oi')
            ->join('orders as o', function ($join) use ($tenantId) {
                $join->on('o.id', '=', 'oi.order_id')
                     ->where('o.tenant_id', '=', $tenantId)
                     ->where('o.status', '!=', 'CANCELLED');
            })
            ->where('oi.tenant_id', $tenantId)
            ->whereNotNull('oi.product_id');

        if ($startUtc) {
            $salesSub->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $salesSub->where('o.created_at', '<=', $endUtc);
        }

        $salesGrouped = $salesSub->selectRaw("
            oi.product_id,
            SUM(oi.quantity) as units_sold,
            SUM(oi.subtotal) as sales_revenue
        ")->groupBy('oi.product_id');

        // 2. Query sobre productos aplicando la precedencia canónica de stock P6
        $query = DB::table('products as p')
            ->leftJoinSub($salesGrouped, 's', function ($join) {
                $join->on('p.id', '=', 's.product_id');
            })
            ->leftJoin('inventory_stocks as inv', function ($join) use ($tenantId) {
                $join->on('p.id', '=', 'inv.product_id')
                     ->where('inv.tenant_id', '=', $tenantId);
            })
            ->where('p.tenant_id', $tenantId);

        if (!empty($filters['product_id'])) {
            $query->where('p.id', (int) $filters['product_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('p.category_id', (int) $filters['category_id']);
        }

        $rows = $query->selectRaw("
            p.id as product_id,
            p.name as product_name,
            COALESCE(p.sku, 'N/A') as sku,
            COALESCE(s.units_sold, 0) as units_sold,
            COALESCE(s.sales_revenue, 0) as sales_revenue,
            COALESCE(
                inv.current_stock,
                NULLIF(p.stock, 0),
                (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv WHERE pv.product_id = p.id AND pv.tenant_id = ?),
                p.stock,
                0
            ) as current_stock,
            COALESCE(inv.minimum_stock, 0) as minimum_stock
        ", [$tenantId])
        ->orderByDesc('sales_revenue')
        ->get();

        $result = [];
        foreach ($rows as $r) {
            $units = (int) $r->units_sold;
            $revenue = round((float) $r->sales_revenue, 2);
            $stock = max(0, (int) $r->current_stock);
            $minStock = max(0, (int) $r->minimum_stock);

            $result[] = [
                'product_id' => (int) $r->product_id,
                'product_name' => (string) $r->product_name,
                'sku' => (string) $r->sku,
                'units_sold' => $units,
                'sales_revenue' => $revenue,
                'current_stock' => $stock,
                'is_low_stock' => ($minStock > 0 && $stock <= $minStock),
                'is_out_of_stock' => ($stock === 0),
            ];
        }

        return $result;
    }
}
