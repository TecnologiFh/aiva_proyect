<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class InventoryStockByProductCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
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
        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        $rows = $query->selectRaw("
            p.id as product_id,
            p.name as product_name,
            COALESCE(p.sku, 'N/A') as sku,
            p.category_id,
            COALESCE(c.name, 'Sin categoría') as category_name,
            COALESCE(
                inv.current_stock,
                NULLIF(p.stock, 0),
                (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv WHERE pv.product_id = p.id AND pv.tenant_id = ?),
                p.stock,
                0
            ) as current_stock,
            COALESCE(inv.reserved_stock, 0) as reserved_stock,
            COALESCE(inv.minimum_stock, 0) as minimum_stock,
            p.is_available
        ", [$tenantId])
        ->orderBy('p.name')
        ->get();

        return $rows->map(function ($row) {
            $current = max(0, (int) $row->current_stock);
            $reserved = max(0, (int) $row->reserved_stock);
            $available = max(0, $current - $reserved);
            $minStock = (int) $row->minimum_stock;
            $isOutOfStock = ($available <= 0);
            $isLowStock = ($minStock > 0 && $available <= $minStock);

            return [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'sku' => (string) $row->sku,
                'category_id' => $row->category_id ? (int) $row->category_id : null,
                'category_name' => (string) $row->category_name,
                'current_stock' => $current,
                'reserved_stock' => $reserved,
                'available_stock' => $available,
                'minimum_stock' => $minStock,
                'is_out_of_stock' => $isOutOfStock,
                'is_low_stock' => $isLowStock,
            ];
        })->all();
    }
}
