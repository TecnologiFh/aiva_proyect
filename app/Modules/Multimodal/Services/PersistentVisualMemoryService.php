<?php

namespace App\Modules\Multimodal\Services;

use App\Modules\Multimodal\Enums\ReconciliationState;
use App\Modules\Multimodal\Enums\TemporalStatus;
use App\Modules\Multimodal\Models\VisualCatalogBinding;
use App\Modules\Multimodal\Models\VisualEntity;
use Illuminate\Support\Facades\DB;

class PersistentVisualMemoryService
{
    /**
     * Consulta el recuerdo de un concepto visual en la memoria persistente del tenant
     * aplicando la Barrera Antialucinación contra la versión activa de AG-46.
     *
     * Precision 2:
     * - Si el producto NO existe en el catálogo activo -> STALE / NO_SELL.
     * - Si el producto EXISTE pero stock = 0 -> MATCH_CANONICAL + OUT_OF_STOCK / NO_SELL (Identidad != Disponibilidad).
     * - Cero mutaciones comerciales en carts, orders o products.
     */
    public function queryConceptMemory(int $tenantId, string $conceptName): array
    {
        // 1. Buscar entidad visual en la memoria histórica del tenant
        $entity = VisualEntity::where('tenant_id', $tenantId)
            ->where(function ($q) use ($conceptName) {
                $q->where('raw_label', 'LIKE', "%{$conceptName}%")
                    ->orWhere('normalized_label', 'LIKE', "%{$conceptName}%");
            })
            ->latest('created_at')
            ->first();

        if (!$entity) {
            return [
                'status' => 'NOT_FOUND_IN_MEMORY',
                'sellable' => false,
                'commercial_mutation_allowed' => false,
                'reason' => 'No historical visual memory of this concept for this tenant.',
            ];
        }

        // 2. Localizar el binding asociado
        $binding = VisualCatalogBinding::where('tenant_id', $tenantId)
            ->where('visual_entity_id', $entity->id)
            ->latest('last_reconciled_at')
            ->first();

        // 3. Consultar la versión actualmente activa del catálogo de AG-46
        $activeVersion = DB::table('catalog_versions')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'PUBLISHED')
            ->first();

        $activeVersionId = $activeVersion ? (int)$activeVersion->id : 0;
        $sku = $binding?->canonical_product_identifier;

        // Caso A: Si es un concepto VISUAL_ONLY o no tiene SKU
        if (!$binding || $binding->resolution_state === ReconciliationState::VISUAL_ONLY->value || empty($sku)) {
            return [
                'status' => 'VISUAL_ONLY',
                'entity_label' => $entity->raw_label,
                'sellable' => false,
                'commercial_mutation_allowed' => false,
                'explanation' => 'Concepto visual informativo reconocido en memoria, no es una unidad comercial transaccionable.',
                'commercial_mutations_count' => 0,
            ];
        }

        // Caso B: Verificar si el SKU existe en el catálogo activo de AG-46
        $activeProduct = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('catalog_version_id', $activeVersionId)
            ->where('sku', $sku)
            ->first();

        if (!$activeProduct) {
            // El producto ya no existe en el catálogo activo -> STALE / NO_SELL
            if ($binding) {
                $binding->update(['temporal_status' => TemporalStatus::STALE->value]);
            }

            return [
                'status' => 'STALE_NO_SELL',
                'entity_label' => $entity->raw_label,
                'canonical_sku' => $sku,
                'temporal_status' => TemporalStatus::STALE->value,
                'sellable' => false,
                'commercial_mutation_allowed' => false,
                'explanation' => 'El producto apareció en nuestra carta histórica, pero actualmente no se encuentra en el catálogo activo.',
                'commercial_mutations_count' => 0,
            ];
        }

        // Caso C: El producto existe en el catálogo activo, pero su stock es 0 (Precision 2)
        $currentStock = (int) $activeProduct->stock;
        $authoritativePrice = (float) $activeProduct->base_price;

        if ($currentStock <= 0) {
            // Conserva la identidad (MATCH_CANONICAL), pero su disponibilidad es OUT_OF_STOCK
            return [
                'status' => 'OUT_OF_STOCK',
                'resolution_state' => ReconciliationState::MATCH_CANONICAL->value,
                'entity_label' => $entity->raw_label,
                'canonical_sku' => $sku,
                'stock' => 0,
                'authoritative_price' => $authoritativePrice,
                'sellable' => false,
                'commercial_mutation_allowed' => false,
                'explanation' => 'Producto reconocido en memoria e identificado en catálogo, pero actualmente agotado (stock 0).',
                'commercial_mutations_count' => 0,
            ];
        }

        // Caso D: El producto existe y tiene stock > 0
        return [
            'status' => 'SELLABLE',
            'resolution_state' => ReconciliationState::MATCH_CANONICAL->value,
            'entity_label' => $entity->raw_label,
            'canonical_sku' => $sku,
            'stock' => $currentStock,
            'authoritative_price' => $authoritativePrice,
            'sellable' => true,
            'commercial_mutation_allowed' => true,
            'commercial_mutations_count' => 0, // P3.12 no muta por sí mismo
        ];
    }
}
