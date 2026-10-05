<?php

namespace App\Modules\Multimodal\Services;

use App\Modules\Multimodal\Enums\ProvenanceClassification;
use App\Modules\Multimodal\Enums\VisualRelationType;
use App\Modules\Multimodal\Models\VisualAsset;
use App\Modules\Multimodal\Models\VisualEntity;
use App\Modules\Multimodal\Models\VisualRelation;
use App\Modules\Multimodal\Models\VisualSemanticBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisualSemanticParserService
{
    /**
     * Estructura bloques semánticos y entidades observadas para un asset.
     * Garantiza linaje de procedencia estricto (Provenance & Lineage).
     */
    public function parseAndStoreStructure(
        int $tenantId,
        VisualAsset $asset,
        array $blocksData
    ): array {
        return DB::transaction(function () use ($tenantId, $asset, $blocksData) {
            $createdBlocks = [];
            $createdEntities = [];
            $createdRelations = [];

            foreach ($blocksData as $bData) {
                // 1. Crear bloque semántico
                $block = VisualSemanticBlock::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'asset_id' => $asset->id,
                    'block_name' => $bData['block_name'],
                    'block_type' => $bData['block_type'] ?? 'SECTION',
                    'region_box_json' => $bData['region_box'] ?? [0.0, 0.0, 1.0, 0.3],
                    'confidence' => (float) ($bData['confidence'] ?? 1.0),
                ]);
                $createdBlocks[] = $block;

                // 2. Procesar entidades dentro del bloque
                $entities = $bData['entities'] ?? [];
                foreach ($entities as $eData) {
                    $rawLabel = $eData['raw_label'] ?? $eData['label'] ?? '';
                    $normalizedLabel = Str::of($rawLabel)->lower()->ascii()->trim()->squish()->toString();

                    // Regla de Linaje: Inferencia jamás puede promoverse a OBSERVED_FROM_IMAGE
                    $provenance = $eData['provenance'] ?? ProvenanceClassification::OBSERVED_FROM_IMAGE->value;
                    if (isset($eData['is_inferred']) && $eData['is_inferred']) {
                        $provenance = $eData['provenance'] ?? ProvenanceClassification::INFERRED_FROM_VISUAL_STRUCTURE->value;
                    }

                    $entity = VisualEntity::create([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'asset_id' => $asset->id,
                        'block_id' => $block->id,
                        'raw_label' => $rawLabel,
                        'normalized_label' => $normalizedLabel,
                        'region_box_json' => $eData['region_box'] ?? [0.1, 0.1, 0.3, 0.5],
                        'observed_price' => isset($eData['observed_price']) ? (float) $eData['observed_price'] : null,
                        'currency' => $eData['currency'] ?? 'PEN',
                        'extraction_method' => $eData['extraction_method'] ?? 'MULTIMODAL_SEMANTIC',
                        'evidence_confidence' => (float) ($eData['confidence'] ?? 1.0),
                        'provenance_classification' => $provenance,
                    ]);
                    $createdEntities[] = $entity;

                    // 3. Crear relación CONTAINS: Block CONTAINS Entity
                    $relContains = VisualRelation::create([
                        'tenant_id' => $tenantId,
                        'asset_id' => $asset->id,
                        'source_entity_id' => $entity->id,
                        'target_entity_id' => $entity->id,
                        'relation_type' => VisualRelationType::CONTAINS->value,
                        'confidence' => 1.0,
                        'metadata_json' => [
                            'block_id' => $block->id,
                            'block_name' => $block->block_name,
                            'relation' => 'BLOCK_CONTAINS_ENTITY',
                        ],
                    ]);
                    $createdRelations[] = $relContains;

                    // 4. Si tiene precio observado, crear relación PRICED_AS
                    if ($entity->observed_price !== null) {
                        $relPriced = VisualRelation::create([
                            'tenant_id' => $tenantId,
                            'asset_id' => $asset->id,
                            'source_entity_id' => $entity->id,
                            'target_entity_id' => $entity->id,
                            'relation_type' => VisualRelationType::PRICED_AS->value,
                            'confidence' => 1.0,
                            'metadata_json' => [
                                'observed_price' => $entity->observed_price,
                                'currency' => $entity->currency,
                            ],
                        ]);
                        $createdRelations[] = $relPriced;
                    }
                }
            }

            return [
                'blocks' => $createdBlocks,
                'entities' => $createdEntities,
                'relations' => $createdRelations,
            ];
        });
    }
}
