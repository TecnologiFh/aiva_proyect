<?php

namespace App\Modules\Multimodal\Runtime\Adapters;

use App\Modules\Multimodal\Runtime\Contracts\VisualEvidenceAdapterContract;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualEntityDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de Evidencia Visual Comercial hacia AG-46 (v2.3).
 * 
 * Regla Constitucional:
 * La imagen puede aportar evidencia; AG-46 rige absolutamente la autoridad comercial.
 * observed_price != authorized_price -> CONFLICT -> se vende a authorized_price.
 * Auditoría intacta: jamás la imagen sobrescribe la base de datos de catálogo.
 */
class VisualCatalogAdapter implements VisualEvidenceAdapterContract
{
    private VisualEvidenceNormalizationService $normalizer;
    private CatalogReferenceResolverService $catalogResolver;

    public function __construct(
        ?VisualEvidenceNormalizationService $normalizer = null,
        ?CatalogReferenceResolverService $catalogResolver = null
    ) {
        $this->normalizer = $normalizer ?? new VisualEvidenceNormalizationService();
        $this->catalogResolver = $catalogResolver ?? new CatalogReferenceResolverService();
    }

    public function supports(StructuredVisualEvidenceDTO $evidence): bool
    {
        return $evidence->hasEvidence(CommercialVisualEvidenceType::MENU_CARD)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::MENU_SECTION)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::PRODUCT_ITEM)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::CATALOG_ITEM)
            || $evidence->assetClassification === CommercialVisualEvidenceType::MENU_CARD->value
            || $evidence->assetClassification === 'PRODUCT_IMAGE';
    }

    public function reconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array
    {
        $tenantId = $evidence->tenantId;
        $targetEntityName = $context['target_entity'] ?? null;

        // Si se pide reconciliar una entidad específica (ej: V7 "Quiero la pechuga de la carta" o V22 carenados)
        if (!empty($targetEntityName)) {
            return $this->reconcileSpecificEntity($evidence, $targetEntityName, $context);
        }

        // Reconciliación por lote de todas las entidades contenidas en la evidencia
        $reconciledEntities = [];
        $conflictsCount = 0;

        foreach ($evidence->entities as $entity) {
            $entityResult = $this->reconcileSpecificEntity($evidence, $entity->name, $context);
            $reconciledEntities[] = $entityResult;
            if (($entityResult['reconciliation_status'] ?? '') === VisualResolutionStatus::CONFLICT->value) {
                $conflictsCount++;
            }
        }

        return [
            'status' => 'RECONCILED',
            'tenant_id' => $tenantId,
            'correlation_id' => $evidence->correlationId,
            'entities_count' => count($reconciledEntities),
            'conflicts_count' => $conflictsCount,
            'reconciled_entities' => $reconciledEntities,
            'message' => 'Catálogo visual reconciliado contra la autoridad oficial de AG-46.',
        ];
    }

    /**
     * Reconcilia una entidad específica contra AG-46 garantizando soberanía de catálogo.
     */
    public function reconcileSpecificEntity(
        StructuredVisualEvidenceDTO $evidence,
        string $entityName,
        array $context = []
    ): array {
        $tenantId = $evidence->tenantId;
        $entity = $evidence->getEntity($entityName);
        $observedPriceStr = $entity ? $entity->observedPrice : null;

        // 1. Consultar AG-46 vía su contrato público CatalogReferenceResolverService
        $ag46Resolution = $this->catalogResolver->resolveReference($tenantId, $entityName, $context);

        // Caso A: Ambigüedad en catálogo (V10 / V23)
        if ($ag46Resolution->isAmbiguous()) {
            return [
                'status' => VisualResolutionStatus::AMBIGUOUS->value,
                'reconciliation_status' => VisualResolutionStatus::AMBIGUOUS->value,
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'entity_name' => $entityName,
                'candidates' => $ag46Resolution->candidates ?? [],
                'reason' => $ag46Resolution->reason ?? 'Múltiples candidatos compatibles encontrados en el catálogo.',
            ];
        }

        // Caso B: No encontrado en catálogo oficial (VISUAL_ONLY)
        if (!$ag46Resolution->isResolved()) {
            return [
                'status' => VisualResolutionStatus::NOT_FOUND->value,
                'reconciliation_status' => VisualResolutionStatus::NOT_FOUND->value,
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'entity_name' => $entityName,
                'observed_price' => $observedPriceStr,
                'authorized_price' => null,
                'reason' => $ag46Resolution->reason ?? 'El producto visual no existe en el catálogo publicado de AG-46.',
            ];
        }

        // Caso C: Resuelto inequívocamente en AG-46
        $authorizedPriceStr = $this->normalizer->normalizeDecimalAmount($ag46Resolution->price);
        $authorizedSku = $ag46Resolution->additionalData['sku'] ?? ('SKU-' . $ag46Resolution->entityId);
        $authorizedStock = $ag46Resolution->additionalData['stock'] ?? 99;
        $compatibility = $ag46Resolution->additionalData['compatibility'] ?? null;

        // Evaluar discrepancia entre precio observado en imagen y precio autorizado por MySQL (V3, V11, V21)
        $hasPriceConflict = false;
        $status = VisualResolutionStatus::MATCH_CANONICAL->value;

        if ($observedPriceStr !== null && $authorizedPriceStr !== null) {
            if (bccomp($observedPriceStr, $authorizedPriceStr, 2) !== 0) {
                $hasPriceConflict = true;
                $status = VisualResolutionStatus::CONFLICT->value;

                Log::warning("AG-46 PRICE CONFLICT AUDIT: Entidad '{$entityName}' observada en S/ {$observedPriceStr}, pero AG-46 autoriza S/ {$authorizedPriceStr}.", [
                    'tenant_id' => $tenantId,
                    'correlation_id' => $evidence->correlationId,
                    'sku' => $authorizedSku,
                    'observed_price' => $observedPriceStr,
                    'authorized_price' => $authorizedPriceStr,
                ]);
            }
        }

        return [
            'status' => $status,
            'reconciliation_status' => $status,
            'tenant_id' => $tenantId,
            'correlation_id' => $evidence->correlationId,
            'entity_name' => $entityName,
            'sku' => $authorizedSku,
            'observed_price' => $observedPriceStr,
            'authorized_price' => $authorizedPriceStr,
            'sale_price' => $authorizedPriceStr, // Soberanía comercial absoluta: se vende a authorized_price
            'stock' => $authorizedStock,
            'compatibility' => $compatibility,
            'has_conflict' => $hasPriceConflict,
            'audit_note' => $hasPriceConflict 
                ? "La evidencia visual registró S/ {$observedPriceStr}, pero el catálogo comercial publicado autorizaba S/ {$authorizedPriceStr}."
                : "Precio y producto autorizados conforme a AG-46.",
        ];
    }
}
