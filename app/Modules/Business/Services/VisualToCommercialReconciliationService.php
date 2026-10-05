<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\MediaVisualEntity;
use App\Modules\Business\Models\MediaVisualRelation;
use App\Modules\Business\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use Illuminate\Support\Str;

class VisualToCommercialReconciliationService
{
    private CatalogReferenceResolverService $catalogResolver;

    public function __construct()
    {
        $this->catalogResolver = new CatalogReferenceResolverService();
    }

    /**
     * Reconciliación determinística de una entidad visual contra la autoridad comercial de MySQL.
     * Retorna estado reconciliado y reglas de autorización operacional.
     */
    public function reconcileVisualEntity(int $tenantId, MediaVisualEntity $entity): array
    {
        $entityName = $entity->raw_text;
        $normName = Str::of($entityName)->lower()->ascii()->trim()->squish()->toString();

        // 1. Obtener precio visual si existe relación PRICED_BY
        $priceRel = MediaVisualRelation::where('tenant_id', $tenantId)
            ->where('source_entity_id', $entity->id)
            ->where('relation_type', 'PRICED_BY')
            ->first();

        $visualPrice = null;
        if ($priceRel) {
            $priceTarget = MediaVisualEntity::find($priceRel->target_entity_id);
            $visualPrice = $priceTarget ? (float)$priceTarget->raw_text : null;
        }

        // 2. Consultar autoridad comercial canónica en MySQL mediante el resolver determinístico de P3.11
        $refDto = $this->catalogResolver->resolveCustomerReference($tenantId, $entityName);

        if ($refDto->status === 'RESOLVED' && $refDto->entityName) {
            $dbPrice = (float)$refDto->price;

            // Comparar precios: si la imagen difiere de MySQL, CONFLICT
            if ($visualPrice !== null && abs($visualPrice - $dbPrice) > 0.01) {
                return [
                    'visual_state' => 'OBSERVED',
                    'commercial_state' => 'CONFLICT',
                    'authority_winner' => 'MYSQL',
                    'commercial_product_name' => $refDto->entityName,
                    'visual_price' => $visualPrice,
                    'authorized_price' => $dbPrice,
                    'can_request_media' => true,
                    'can_order' => true,
                    'explanation' => "Conflicto detectado: la imagen muestra S/ {$visualPrice} pero MySQL autoriza S/ {$dbPrice}. Prevalece MySQL.",
                ];
            }

            return [
                'visual_state' => 'OBSERVED',
                'commercial_state' => 'MATCH',
                'authority_winner' => 'MYSQL',
                'commercial_product_name' => $refDto->entityName,
                'visual_price' => $visualPrice,
                'authorized_price' => $dbPrice,
                'can_request_media' => true,
                'can_order' => true,
                'explanation' => "Coincidencia plena entre carta visual y catálogo MySQL.",
            ];
        }

        // 3. Si no se resolvió en MySQL pero existe visualmente: VISUAL_ONLY (Regla Constitucional AG-45)
        return [
            'visual_state' => 'OBSERVED',
            'commercial_state' => 'VISUAL_ONLY',
            'authority_winner' => 'NONE',
            'commercial_product_name' => null,
            'visual_price' => $visualPrice,
            'authorized_price' => null,
            'can_request_media' => true,  // Permitido para REQUEST_MEDIA
            'can_order' => false,          // Prohibido terminantemente para ORDER_INTENT
            'explanation' => "El ítem '{$entityName}' existe en la carta visual pero no está habilitado en MySQL para pedidos.",
        ];
    }
}
