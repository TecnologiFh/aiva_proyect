<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class InventoryStockByVariantCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $query = DB::table('product_variants as pv')
            ->join('products as p', function ($join) use ($tenantId) {
                $join->on('pv.product_id', '=', 'p.id')
                     ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('pv.tenant_id', $tenantId);

        if (!empty($filters['product_id'])) {
            $query->where('pv.product_id', (int) $filters['product_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('pv.status', $filters['status']);
        }

        $rows = $query->select(
            'pv.id as variant_id',
            'pv.product_id',
            'p.name as product_name',
            'pv.sku',
            'pv.color',
            'pv.talla',
            'pv.stock',
            'pv.status'
        )
        ->orderBy('p.name')
        ->orderBy('pv.sku')
        ->get();

        return $rows->map(fn($row) => [
            'variant_id' => (int) $row->variant_id,
            'product_id' => (int) $row->product_id,
            'product_name' => (string) $row->product_name,
            'sku' => (string) $row->sku,
            'color' => (string) ($row->color ?? ''),
            'talla' => (string) ($row->talla ?? ''),
            'stock' => max(0, (int) $row->stock),
            'status' => (string) ($row->status ?? 'ACTIVE'),
            'is_out_of_stock' => (int) $row->stock <= 0,
        ])->all();
    }
}
