<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\MediaAsset;
use App\Modules\Business\Models\MediaVisualRegion;
use App\Modules\Business\Models\MediaVisualEntity;
use App\Modules\Business\Models\MediaVisualRelation;
use App\Modules\Business\Models\MediaSemanticIndex;
use App\Modules\Business\Models\MediaSemanticConcept;
use Illuminate\Support\Str;

class VisualMemoryService
{
    /**
     * Reconstrucción semántica completa de una carta desde la memoria persistente en base de datos.
     * Cero llamadas a Visión, cero llamadas a OCR (Garantía AG-52).
     */
    public function reconstructCardHierarchy(int $tenantId, int $mediaAssetId): ?array
    {
        $asset = MediaAsset::where('tenant_id', $tenantId)->where('id', $mediaAssetId)->first();
        if (!$asset) return null;

        $regions = MediaVisualRegion::where('tenant_id', $tenantId)
            ->where('media_asset_id', $mediaAssetId)
            ->orderBy('reading_order', 'asc')
            ->get();

        $structuredSections = [];
        foreach ($regions as $r) {
            $entities = MediaVisualEntity::where('tenant_id', $tenantId)
                ->where('region_id', $r->id)
                ->get();

            $products = [];
            $decorations = [];

            // Agrupar entidades por producto con sus relaciones DESCRIBES y PRICED_BY
            foreach ($entities->where('entity_type', 'PRODUCT') as $pEnt) {
                // Buscar precio vinculado
                $pricedRel = MediaVisualRelation::where('tenant_id', $tenantId)
                    ->where('source_entity_id', $pEnt->id)
                    ->where('relation_type', 'PRICED_BY')
                    ->first();

                $priceVal = null;
                if ($pricedRel) {
                    $pTarget = $entities->firstWhere('id', $pricedRel->target_entity_id);
                    $priceVal = $pTarget ? (float)$pTarget->raw_text : null;
                }

                // Buscar descripción vinculada
                $descRel = MediaVisualRelation::where('tenant_id', $tenantId)
                    ->where('source_entity_id', $pEnt->id)
                    ->where('relation_type', 'DESCRIBES')
                    ->first();

                $descVal = null;
                if ($descRel) {
                    $dTarget = $entities->firstWhere('id', $descRel->target_entity_id);
                    $descVal = $dTarget ? $dTarget->raw_text : null;
                }

                // Buscar conceptos semánticos asociados
                $conceptEntries = MediaSemanticIndex::where('tenant_id', $tenantId)
                    ->where('entity_id', $pEnt->id)
                    ->with('concept')
                    ->get();

                $conceptsList = $conceptEntries->pluck('concept.concept')->filter()->values()->toArray();

                $products[] = [
                    'entity_id' => $pEnt->id,
                    'name' => $pEnt->raw_text,
                    'normalized_name' => $pEnt->normalized_label,
                    'visual_price' => $priceVal,
                    'description' => $descVal,
                    'bbox' => ['x' => $pEnt->bbox_x, 'y' => $pEnt->bbox_y, 'w' => $pEnt->bbox_w, 'h' => $pEnt->bbox_h],
                    'visual_role' => $pEnt->visual_role,
                    'concepts' => $conceptsList,
                ];
            }

            foreach ($entities->where('visual_role', 'DECORATION') as $dEnt) {
                $decorations[] = [
                    'entity_id' => $dEnt->id,
                    'label' => $dEnt->raw_text,
                    'entity_type' => $dEnt->entity_type,
                    'bbox' => ['x' => $dEnt->bbox_x, 'y' => $dEnt->bbox_y, 'w' => $dEnt->bbox_w, 'h' => $dEnt->bbox_h],
                ];
            }

            $structuredSections[] = [
                'region_id' => $r->id,
                'label' => $r->label,
                'normalized_label' => $r->normalized_label,
                'reading_order' => $r->reading_order,
                'bbox' => ['x' => $r->bbox_x, 'y' => $r->bbox_y, 'w' => $r->bbox_w, 'h' => $r->bbox_h],
                'products' => $products,
                'decorations' => $decorations,
            ];
        }

        return [
            'media_asset_id' => $asset->id,
            'title' => $asset->title,
            'category' => $asset->category,
            'file_path' => $asset->file_path,
            'sha256' => $asset->sha256,
            'status' => $asset->status,
            'is_dispatchable' => $asset->checkIsDispatchable(),
            'sections' => $structuredSections,
        ];
    }

    /**
     * Verificar si un término o plato fue observado visualmente en alguna carta del negocio
     */
    public function findVisualEvidence(int $tenantId, string $query): ?array
    {
        $norm = Str::of($query)->lower()->ascii()->trim()->squish()->toString();
        if (empty($norm)) return null;

        // 1. Coincidencia exacta o parcial en entidades visuales de productos
        $entity = MediaVisualEntity::where('tenant_id', $tenantId)
            ->where(function ($q) use ($norm) {
                $q->where('normalized_label', $norm)
                  ->orWhere('normalized_label', 'LIKE', '%' . $norm . '%');
            })
            ->where('visual_role', 'PRODUCT_REPRESENTATION')
            ->first();

        if ($entity) {
            $asset = MediaAsset::find($entity->media_asset_id);
            $region = MediaVisualRegion::find($entity->region_id);
            return [
                'has_visual_evidence' => true,
                'entity_id' => $entity->id,
                'raw_text' => $entity->raw_text,
                'media_asset_id' => $asset?->id,
                'media_title' => $asset?->title,
                'media_file_path' => $asset?->file_path,
                'region_label' => $region?->label,
                'confidence' => $entity->confidence,
            ];
        }

        // 2. Coincidencia en observaciones crudas
        $obs = \App\Modules\Business\Models\MediaVisualObservation::where('tenant_id', $tenantId)
            ->where('raw_text', 'LIKE', '%' . $norm . '%')
            ->first();

        if ($obs) {
            $asset = MediaAsset::find($obs->media_asset_id);
            return [
                'has_visual_evidence' => true,
                'raw_text' => $obs->raw_text,
                'media_asset_id' => $asset?->id,
                'media_title' => $asset?->title,
                'media_file_path' => $asset?->file_path,
                'region_label' => null,
                'confidence' => $obs->confidence,
            ];
        }

        return null;
    }
}
