<?php

namespace App\Modules\Messaging\Tools;

use Illuminate\Support\Facades\DB;

class GetProductInfoTool
{
    /**
     * Consultar la Ficha Técnica de Producto con Jerarquía de Autoridad Core.
     */
    public function execute(int $tenantId, string $productQuery): array
    {
        $product = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($productQuery) {
                $q->where('name', 'LIKE', '%' . $productQuery . '%')
                  ->orWhere('sku', $productQuery);
            })
            ->first();

        if (!$product) {
            return [
                'found' => false,
                'status' => 'NOT_FOUND',
                'message' => "Producto '{$productQuery}' no existe en el catálogo."
            ];
        }

        $coreRules = [
            'requires_advance' => (bool) ($product->requires_advance ?? false),
            'advance_hours' => (int) ($product->advance_hours ?? 0),
            'requires_deposit' => (bool) ($product->requires_deposit ?? false),
            'deposit_percentage' => (float) ($product->deposit_percentage ?? 0.00),
        ];

        return [
            'found' => true,
            'product_id' => $product->id,
            'product' => $product->name,
            'description' => $product->description ?? 'Producto comercial oficial.',
            'price' => (float) ($product->base_price ?? $product->price ?? 0),
            'core_rules' => $coreRules,
            'ai_instructions' => $product->ai_instructions ?? null,
            'authority' => [
                'core_rules' => 'AUTHORITATIVE_MANDATORY',
                'ai_instructions' => 'NON_AUTHORITATIVE_CONTEXTUAL_ONLY',
                'rule' => 'El LLM puede explicar una decisión del Core, pero nunca puede contradecirla.'
            ]
        ];
    }
}
