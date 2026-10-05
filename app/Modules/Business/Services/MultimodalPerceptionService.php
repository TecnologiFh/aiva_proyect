<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\MediaAsset;
use App\Modules\Business\Models\MediaVisualRegion;
use App\Modules\Business\Models\MediaVisualObservation;
use App\Modules\Business\Models\MediaVisualEntity;
use App\Modules\Business\Models\MediaVisualRelation;
use App\Modules\Business\Models\MediaSemanticConcept;
use App\Modules\Business\Models\MediaSemanticConceptRelation;
use App\Modules\Business\Models\MediaSemanticIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MultimodalPerceptionService
{
    /**
     * Ingesta estructurada de un recurso visual comercial produciendo la memoria multimodal completa.
     * WHAT, WHERE, GROUP, RELATION, MEANING, VISUAL ROLE.
     */
    public function ingestStructuredCard(
        int $tenantId,
        string $filePath,
        string $title,
        string $category,
        int $displayOrder,
        array $sectionsData,
        ?int $catalogVersionId = null,
        ?int $commercialMediaId = null
    ): MediaAsset {
        return DB::transaction(function () use ($tenantId, $filePath, $title, $category, $displayOrder, $sectionsData, $catalogVersionId, $commercialMediaId) {
            $sha256 = file_exists(public_path($filePath)) 
                ? hash_file('sha256', public_path($filePath)) 
                : (file_exists($filePath) ? hash_file('sha256', $filePath) : hash('sha256', $filePath . '_' . $tenantId . '_' . $displayOrder));

            // 1. Crear o actualizar MediaAsset
            $asset = MediaAsset::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'file_path' => $filePath,
                ],
                [
                    'commercial_media_id' => $commercialMediaId,
                    'lineage_id' => (string) Str::uuid(),
                    'storage_key' => $filePath,
                    'mime_type' => 'image/png',
                    'media_type' => 'MENU_CARD',
                    'category' => strtoupper($category),
                    'title' => $title,
                    'sha256' => $sha256,
                    'catalog_version_id' => $catalogVersionId,
                    'display_order' => $displayOrder,
                    'revision_number' => 1,
                    'status' => 'ACTIVE',
                    'is_dispatchable' => true,
                ]
            );

            // Limpiar datos previos si se reingesta
            MediaVisualRelation::where('tenant_id', $tenantId)->where('media_asset_id', $asset->id)->delete();
            MediaVisualEntity::where('tenant_id', $tenantId)->where('media_asset_id', $asset->id)->delete();
            MediaVisualObservation::where('tenant_id', $tenantId)->where('media_asset_id', $asset->id)->delete();
            MediaVisualRegion::where('tenant_id', $tenantId)->where('media_asset_id', $asset->id)->delete();
            MediaSemanticIndex::where('tenant_id', $tenantId)->where('media_asset_id', $asset->id)->delete();

            $regionOrder = 1;
            foreach ($sectionsData as $sec) {
                // 2. Crear Región Visual (Sección / Bloque)
                $normRegionLabel = Str::of($sec['label'])->lower()->ascii()->trim()->squish()->toString();
                $region = MediaVisualRegion::create([
                    'tenant_id' => $tenantId,
                    'media_asset_id' => $asset->id,
                    'parent_region_id' => null,
                    'region_type' => $sec['type'] ?? 'CATEGORY_SECTION',
                    'label' => $sec['label'],
                    'normalized_label' => $normRegionLabel,
                    'bbox_x' => $sec['bbox']['x'] ?? 0.0,
                    'bbox_y' => $sec['bbox']['y'] ?? (0.2 * $regionOrder),
                    'bbox_w' => $sec['bbox']['w'] ?? 1.0,
                    'bbox_h' => $sec['bbox']['h'] ?? 0.25,
                    'reading_order' => $regionOrder++,
                    'confidence' => 0.99,
                ]);

                // Indexar concepto de región primaria
                $this->indexConceptForMedia($tenantId, $sec['label'], 'CATEGORY', $asset->id, $region->id, null, 'REGION_MATCH', 1.0, true);

                // 3. Procesar elementos dentro de la región
                if (!empty($sec['items'])) {
                    foreach ($sec['items'] as $item) {
                        $itemName = $item['name'];
                        $itemPrice = $item['price'] ?? null;
                        $itemDesc = $item['desc'] ?? null;
                        $itemImage = $item['image_ref'] ?? null;
                        $rawObservationText = $itemName . ($itemPrice ? " S/ " . number_format($itemPrice, 2) : '') . ($itemDesc ? " - {$itemDesc}" : '');

                        // A. Visual Observation
                        $observation = MediaVisualObservation::create([
                            'tenant_id' => $tenantId,
                            'media_asset_id' => $asset->id,
                            'region_id' => $region->id,
                            'raw_text' => $rawObservationText,
                            'confidence' => 0.98,
                            'bbox_x' => $item['bbox']['x'] ?? $region->bbox_x,
                            'bbox_y' => $item['bbox']['y'] ?? $region->bbox_y,
                            'bbox_w' => $item['bbox']['w'] ?? $region->bbox_w,
                            'bbox_h' => $item['bbox']['h'] ?? 0.08,
                            'detected_features_json' => ['has_price' => ($itemPrice !== null), 'has_desc' => ($itemDesc !== null)],
                        ]);

                        // B. Visual Entity (Product)
                        $normItem = Str::of($itemName)->lower()->ascii()->trim()->squish()->toString();
                        $productEntity = MediaVisualEntity::create([
                            'tenant_id' => $tenantId,
                            'media_asset_id' => $asset->id,
                            'region_id' => $region->id,
                            'observation_id' => $observation->id,
                            'entity_type' => 'PRODUCT',
                            'visual_role' => 'PRODUCT_REPRESENTATION',
                            'raw_text' => $itemName,
                            'normalized_label' => $normItem,
                            'confidence' => 0.98,
                            'bbox_x' => $observation->bbox_x,
                            'bbox_y' => $observation->bbox_y,
                            'bbox_w' => $observation->bbox_w,
                            'bbox_h' => 0.04,
                            'meta_json' => ['price' => $itemPrice],
                        ]);

                        // C. Relación: PRODUCT -> UNDER_SECTION -> REGION
                        // (representada semánticamente en las relaciones visuales)

                        // D. Entidad Precio y Relación PRICED_BY
                        if ($itemPrice !== null) {
                            $priceEntity = MediaVisualEntity::create([
                                'tenant_id' => $tenantId,
                                'media_asset_id' => $asset->id,
                                'region_id' => $region->id,
                                'observation_id' => $observation->id,
                                'entity_type' => 'PRICE',
                                'visual_role' => 'PRODUCT_REPRESENTATION',
                                'raw_text' => (string)$itemPrice,
                                'normalized_label' => number_format((float)$itemPrice, 2, '.', ''),
                                'confidence' => 0.99,
                                'bbox_x' => $observation->bbox_x + 0.6,
                                'bbox_y' => $observation->bbox_y,
                                'bbox_w' => 0.2,
                                'bbox_h' => 0.04,
                            ]);

                            MediaVisualRelation::create([
                                'tenant_id' => $tenantId,
                                'media_asset_id' => $asset->id,
                                'source_entity_id' => $productEntity->id,
                                'relation_type' => 'PRICED_BY',
                                'target_entity_id' => $priceEntity->id,
                                'confidence' => 0.99,
                            ]);
                        }

                        // E. Entidad Descripción y Relación DESCRIBES
                        if ($itemDesc) {
                            $descEntity = MediaVisualEntity::create([
                                'tenant_id' => $tenantId,
                                'media_asset_id' => $asset->id,
                                'region_id' => $region->id,
                                'observation_id' => $observation->id,
                                'entity_type' => 'DESCRIPTION',
                                'visual_role' => 'PRODUCT_REPRESENTATION',
                                'raw_text' => $itemDesc,
                                'normalized_label' => Str::of($itemDesc)->lower()->ascii()->trim()->squish()->toString(),
                                'confidence' => 0.95,
                                'bbox_x' => $observation->bbox_x,
                                'bbox_y' => $observation->bbox_y + 0.04,
                                'bbox_w' => $observation->bbox_w,
                                'bbox_h' => 0.04,
                            ]);

                            MediaVisualRelation::create([
                                'tenant_id' => $tenantId,
                                'media_asset_id' => $asset->id,
                                'source_entity_id' => $productEntity->id,
                                'relation_type' => 'DESCRIBES',
                                'target_entity_id' => $descEntity->id,
                                'confidence' => 0.95,
                            ]);
                        }

                        // F. Indexar producto y conceptos semánticos
                        $this->indexConceptForMedia($tenantId, $itemName, 'PRODUCT', $asset->id, $region->id, $productEntity->id, 'PRODUCT_MATCH', 1.0, false);

                        if (!empty($item['concepts'])) {
                            foreach ($item['concepts'] as $cName) {
                                $this->indexConceptForMedia($tenantId, $cName, 'PREPARATION', $asset->id, $region->id, $productEntity->id, 'DIRECT_CONCEPT', 0.9, false);
                            }
                        }
                    }
                }

                // Elementos decorativos de la región
                if (!empty($sec['decorations'])) {
                    foreach ($sec['decorations'] as $dec) {
                        MediaVisualEntity::create([
                            'tenant_id' => $tenantId,
                            'media_asset_id' => $asset->id,
                            'region_id' => $region->id,
                            'entity_type' => $dec['type'] ?? 'DECORATION', // DECORATION, BRAND_LOGO, BACKGROUND
                            'visual_role' => 'DECORATION',
                            'raw_text' => $dec['label'] ?? 'Decoración visual',
                            'normalized_label' => Str::of($dec['label'] ?? 'decoracion')->lower()->ascii()->trim()->squish()->toString(),
                            'confidence' => 0.90,
                            'bbox_x' => $dec['bbox']['x'] ?? 0.8,
                            'bbox_y' => $dec['bbox']['y'] ?? 0.1,
                            'bbox_w' => $dec['bbox']['w'] ?? 0.15,
                            'bbox_h' => $dec['bbox']['h'] ?? 0.15,
                        ]);
                    }
                }
            }

            return $asset;
        });
    }

    /**
     * Indexación de concepto con normalización e inserción en el Media Semantic Index
     */
    public function indexConceptForMedia(
        int $tenantId,
        string $conceptName,
        string $conceptType,
        int $mediaAssetId,
        ?int $regionId,
        ?int $entityId,
        string $evidenceType = 'DIRECT_CONCEPT',
        float $matchScore = 1.0,
        bool $isPrimary = false
    ): void {
        $norm = Str::of($conceptName)->lower()->ascii()->trim()->squish()->toString();
        if (empty($norm)) return;

        $concept = MediaSemanticConcept::firstOrCreate(
            ['tenant_id' => $tenantId, 'normalized_concept' => $norm],
            ['concept' => $conceptName, 'concept_type' => $conceptType, 'relevance_weight' => 1.0]
        );

        MediaSemanticIndex::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'concept_id' => $concept->id,
                'media_asset_id' => $mediaAssetId,
                'region_id' => $regionId,
                'entity_id' => $entityId,
            ],
            [
                'evidence_type' => $evidenceType,
                'match_score' => $matchScore,
                'is_primary' => $isPrimary,
            ]
        );
    }

    /**
     * Registro de relación en el grafo de conceptos (ej. MARACUMANGO -> RELATED_TO -> MANGO)
     */
    public function linkRelatedConcepts(int $tenantId, string $sourceConcept, string $targetConcept, string $relationType = 'RELATED_TO', float $strength = 1.0): void
    {
        $normSource = Str::of($sourceConcept)->lower()->ascii()->trim()->squish()->toString();
        $normTarget = Str::of($targetConcept)->lower()->ascii()->trim()->squish()->toString();

        $c1 = MediaSemanticConcept::firstOrCreate(
            ['tenant_id' => $tenantId, 'normalized_concept' => $normSource],
            ['concept' => $sourceConcept, 'concept_type' => 'PREPARATION', 'relevance_weight' => 1.0]
        );

        $c2 = MediaSemanticConcept::firstOrCreate(
            ['tenant_id' => $tenantId, 'normalized_concept' => $normTarget],
            ['concept' => $targetConcept, 'concept_type' => 'INGREDIENT', 'relevance_weight' => 1.0]
        );

        MediaSemanticConceptRelation::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source_concept_id' => $c1->id,
                'relation_type' => $relationType,
                'target_concept_id' => $c2->id,
            ],
            ['strength' => $strength]
        );
    }
}
