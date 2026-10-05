<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

class VariantsSalesCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $productId = !empty($filters['product_id']) ? (int) $filters['product_id'] : null;

        // Consultar variantes del catálogo para el tenant
        $variantsQuery = DB::table('product_variants as pv')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('pv.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId);

        if ($productId) {
            $variantsQuery->where('pv.product_id', $productId);
        }

        $variants = $variantsQuery->select(
            'pv.id as variant_id',
            'pv.product_id',
            'p.name as product_name',
            'pv.sku',
            'pv.color',
            'pv.talla',
            'pv.price_override',
            'p.base_price'
        )->get();

        if ($variants->isEmpty()) {
            return [];
        }

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        $results = [];
        foreach ($variants as $v) {
            // Consultar ventas en order_items matching variant via options_snapshot o sku
            $itemsQuery = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->where('oi.tenant_id', $tenantId)
                ->where('o.tenant_id', $tenantId)
                ->where('o.status', '!=', 'CANCELLED')
                ->where('oi.product_id', $v->product_id)
                ->where(function ($q) use ($v) {
                    $q->where('oi.options_snapshot', 'LIKE', '%"variant_id":' . $v->variant_id . '%')
                      ->orWhere('oi.options_snapshot', 'LIKE', '%"sku":"' . $v->sku . '"%')
                      ->orWhere('oi.options_snapshot', 'LIKE', '%"color":"' . $v->color . '"%');
                });

            if ($startUtc) {
                $itemsQuery->where('o.created_at', '>=', $startUtc);
            }
            if ($endUtc) {
                $itemsQuery->where('o.created_at', '<=', $endUtc);
            }

            $stats = $itemsQuery->selectRaw("
                COALESCE(SUM(oi.quantity), 0) as quantity,
                COUNT(DISTINCT oi.order_id) as orders_count,
                COALESCE(SUM(oi.subtotal), 0) as revenue
            ")->first();

            $results[] = [
                'variant_id' => (int) $v->variant_id,
                'product_id' => (int) $v->product_id,
                'product_name' => (string) $v->product_name,
                'sku' => (string) $v->sku,
                'color' => (string) ($v->color ?? ''),
                'talla' => (string) ($v->talla ?? ''),
                'effective_price' => $v->price_override !== null ? round((float) $v->price_override, 2) : round((float) $v->base_price, 2),
                'quantity' => (int) ($stats->quantity ?? 0),
                'orders_count' => (int) ($stats->orders_count ?? 0),
                'revenue' => round((float) ($stats->revenue ?? 0.00), 2),
            ];
        }

        return $results;
    }
}
