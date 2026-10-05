<?php

namespace App\Modules\Multimodal\Services;

use App\Modules\Multimodal\Enums\ReconciliationState;
use App\Modules\Multimodal\Enums\TemporalStatus;
use App\Modules\Multimodal\Models\PriceDesynchronizationEvidence;
use App\Modules\Multimodal\Models\VisualCatalogBinding;
use App\Modules\Multimodal\Models\VisualEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisualCatalogReconciliationService
{
    /**
     * Reconcilia una entidad visual contra el contrato público activo de AG-46.
     * Preserva estrictamente la autoridad comercial de MySQL:
     * P3.12 propone -> AG-46 autoriza/decide -> MySQL garantiza precio y stock.
     */
    public function reconcileEntityAgainstCatalog(
        int $tenantId,
        VisualEntity $entity,
        ?int $catalogVersionId = null
    ): VisualCatalogBinding {
        // 1. Obtener la versión activa de AG-46 a través de su contrato público
        if ($catalogVersionId === null) {
            $activeVersion = DB::table('catalog_versions')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('status', 'PUBLISHED')
                ->first();
            $catalogVersionId = $activeVersion ? (int)$activeVersion->id : 0;
        }

        $normalizedLabel = $entity->normalized_label;
        $observedPrice = $entity->observed_price !== null ? (float)$entity->observed_price : null;

        // Entidades conceptuales no transaccionables conocidas (VISUAL_ONLY)
        $isVisualOnlyConcept = $this->isKnownVisualOnlyConcept($normalizedLabel);

        if ($isVisualOnlyConcept) {
            return VisualCatalogBinding::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'visual_entity_id' => $entity->id,
                'canonical_product_identifier' => 'VISUAL_ONLY_CONCEPT',
                'catalog_version_id' => $catalogVersionId,
                'resolution_state' => ReconciliationState::VISUAL_ONLY->value,
                'temporal_status' => TemporalStatus::VALID_CURRENT->value,
                'observed_price' => $observedPrice,
                'authoritative_price' => null,
                'divergence_flag' => false,
                'last_reconciled_at' => now(),
            ]);
        }

        // 2. Consultar candidatos en el catálogo publicado de AG-46 para la versión activa
        $candidates = $this->queryCatalogCandidates($tenantId, $catalogVersionId, $normalizedLabel);

        // Caso A: Ningún candidato encontrado
        if (empty($candidates)) {
            // Si la entidad tiene evidencia semántica pero no existe en AG-46 -> VISUAL_ONLY honesto
            return VisualCatalogBinding::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'visual_entity_id' => $entity->id,
                'canonical_product_identifier' => 'UNMAPPED_ENTITY',
                'catalog_version_id' => $catalogVersionId,
                'resolution_state' => ReconciliationState::VISUAL_ONLY->value,
                'temporal_status' => TemporalStatus::VALID_CURRENT->value,
                'observed_price' => $observedPrice,
                'authoritative_price' => null,
                'divergence_flag' => false,
                'last_reconciled_at' => now(),
            ]);
        }

        // Caso B: Conflicto / Múltiples candidatos sin desempate inequívoco
        if (count($candidates) > 1) {
            return VisualCatalogBinding::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'visual_entity_id' => $entity->id,
                'canonical_product_identifier' => 'MULTIPLE_CANDIDATES_CONFLICT',
                'catalog_version_id' => $catalogVersionId,
                'resolution_state' => ReconciliationState::AMBIGUOUS_CONFLICT->value,
                'temporal_status' => TemporalStatus::VALID_CURRENT->value,
                'observed_price' => $observedPrice,
                'authoritative_price' => null,
                'divergence_flag' => false,
                'last_reconciled_at' => now(),
            ]);
        }

        // Caso C: Correspondencia Biunívoca Inequívoca (MATCH_CANONICAL)
        $matchedProduct = $candidates[0];
        $canonicalSku = (string) $matchedProduct->sku;
        $authoritativePrice = (float) $matchedProduct->base_price;

        $divergenceFlag = false;
        if ($observedPrice !== null && abs($observedPrice - $authoritativePrice) > 0.01) {
            $divergenceFlag = true;

            // Registrar evidencia forense de divergencia de precios
            PriceDesynchronizationEvidence::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'visual_entity_id' => $entity->id,
                'canonical_product_identifier' => $canonicalSku,
                'observed_price' => $observedPrice,
                'authoritative_price' => $authoritativePrice,
                'currency' => $entity->currency ?? 'PEN',
                'source_asset_hash' => $entity->asset?->file_hash_sha256 ?? 'asset_hash',
                'catalog_version_id' => $catalogVersionId,
                'detected_at' => now(),
                'resolution_policy' => 'MYSQL_AUTHORITY_ENFORCED',
                'evidence_payload_json' => [
                    'visual_entity_label' => $entity->raw_label,
                    'observed_price' => $observedPrice,
                    'mysql_authoritative_price' => $authoritativePrice,
                    'enforced_price' => $authoritativePrice,
                ],
            ]);
        }

        return VisualCatalogBinding::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'visual_entity_id' => $entity->id,
            'canonical_product_identifier' => $canonicalSku,
            'catalog_version_id' => $catalogVersionId,
            'resolution_state' => ReconciliationState::MATCH_CANONICAL->value,
            'temporal_status' => TemporalStatus::VALID_CURRENT->value,
            'observed_price' => $observedPrice,
            'authoritative_price' => $authoritativePrice,
            'divergence_flag' => $divergenceFlag,
            'last_reconciled_at' => now(),
        ]);
    }

    /**
     * Evalúa el estatus temporal del binding frente a una versión de catálogo.
     * Precisión: Cambio de precio = MATCH_CANONICAL + divergencia; Producto inexistente = STALE.
     */
    public function evaluateTemporalStatus(
        VisualCatalogBinding $binding,
        int $targetCatalogVersionId
    ): string {
        $sku = $binding->canonical_product_identifier;
        $tenantId = $binding->tenant_id;

        // Consultar existencia del SKU en la versión de catálogo objetivo
        $existsInTarget = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('catalog_version_id', $targetCatalogVersionId)
            ->where('sku', $sku)
            ->first();

        if (!$existsInTarget) {
            // El SKU no existe en la versión objetivo -> STALE
            $binding->update(['temporal_status' => TemporalStatus::STALE->value]);
            return TemporalStatus::STALE->value;
        }

        // Si existe en la versión objetivo, el binding sigue siendo válido
        $targetPrice = (float) $existsInTarget->base_price;
        $observedPrice = $binding->observed_price !== null ? (float) $binding->observed_price : null;
        $divergence = ($observedPrice !== null && abs($observedPrice - $targetPrice) > 0.01);

        $binding->update([
            'temporal_status' => TemporalStatus::VALID_CURRENT->value,
            'authoritative_price' => $targetPrice,
            'divergence_flag' => $divergence,
        ]);

        return TemporalStatus::VALID_CURRENT->value;
    }

    /**
     * Consulta candidatos en el catálogo publicado de AG-46 respetando el aislamiento de versión.
     */
    protected function queryCatalogCandidates(int $tenantId, int $catalogVersionId, string $normalizedLabel): array
    {
        $query = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('catalog_version_id', $catalogVersionId);

        // Buscar coincidencia exacta por SKU o por coincidencia normalizada en nombre
        $products = $query->get();
        $matched = [];

        foreach ($products as $p) {
            $pSkuNorm = Str::of($p->sku)->lower()->ascii()->trim()->squish()->toString();
            $pNameNorm = Str::of($p->name)->lower()->ascii()->trim()->squish()->toString();

            if ($pSkuNorm === $normalizedLabel || $pNameNorm === $normalizedLabel || str_contains($pNameNorm, $normalizedLabel) || str_contains($normalizedLabel, $pNameNorm)) {
                $matched[] = $p;
            }
        }

        return $matched;
    }

    /**
     * Detecta conceptos visuales que no representan SKUs independientes transaccionables.
     */
    protected function isKnownVisualOnlyConcept(string $normalizedLabel): bool
    {
        $visualOnlyKeywords = [
            'musica en vivo',
            'incluye ensalada',
            'ensalada fresca',
            'delivery gratis',
            'foto ilustrativa',
            'decoracion',
            'promocion valida',
            'terminos y condiciones',
            'guarnicion incluida',
        ];

        foreach ($visualOnlyKeywords as $kw) {
            if (str_contains($normalizedLabel, $kw)) {
                return true;
            }
        }

        return false;
    }
}
