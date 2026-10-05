<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\MediaAsset;
use App\Modules\Business\Models\MediaSemanticIndex;
use App\Modules\Business\Models\MediaSemanticConcept;
use App\Modules\Business\Models\MediaSemanticConceptRelation;
use App\Modules\Business\Models\ConversationVisualContext;
use Illuminate\Support\Str;

class MediaSemanticSearchService
{
    /**
     * Búsqueda de activo visual aplicando el Ranking Lexicográfico de 8 Niveles con score secundario.
     */
    public function search(int $tenantId, string $target, ?string $conversationId = null, ?int $activeCatalogVersionId = null): array
    {
        $norm = Str::of($target)->lower()->ascii()->trim()->squish()->toString();
        // Deduplicación de caracteres (ej. cartaaaa -> carta)
        $norm = preg_replace('/([a-z])\1{2,}/i', '$1', $norm);

        if (empty($norm)) {
            return ['status' => 'NOT_FOUND', 'asset' => null, 'message' => 'Target vacío'];
        }

        // 1. Obtener todos los activos del tenant que sean DISPATCHABLE
        $query = MediaAsset::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->where('is_dispatchable', true);

        if ($activeCatalogVersionId) {
            $query->where(function ($q) use ($activeCatalogVersionId, $tenantId) {
                $q->where('catalog_version_id', $activeCatalogVersionId)
                  ->orWhereNull('catalog_version_id')
                  ->orWhereIn('catalog_version_id', function($sub) use ($tenantId) {
                      $sub->select('id')->from('catalog_versions')->where('tenant_id', $tenantId);
                  });
            });
        }

        $candidates = $query->get()->filter(fn($a) => $a->checkIsDispatchable());
        if ($candidates->isEmpty()) {
            return ['status' => 'NOT_FOUND', 'asset' => null, 'message' => 'No hay medios dispatchables para el tenant'];
        }

        // Contexto conversacional previo
        $lastMediaId = null;
        if ($conversationId) {
            $lastMediaId = ConversationVisualContext::where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->value('last_media_asset_id');
        }

        // Evaluar cada candidato asignando prioridad lexicográfica y score secundario
        $ranked = [];
        foreach ($candidates as $asset) {
            $eval = $this->evaluateLexicographicalCandidate($tenantId, $asset, $norm, $lastMediaId, $activeCatalogVersionId);
            $ranked[] = [
                'asset' => $asset,
                'priority_level' => $eval['priority_level'], // 1 (más alto) a 9 (más bajo)
                'score' => $eval['secondary_score'],
                'resolution_type' => $eval['resolution_type'],
                'match_concept' => $eval['match_concept'],
            ];
        }

        // Ordenar por: Priority Level ASC (1 es el más específico), luego Secondary Score DESC
        usort($ranked, function ($a, $b) {
            if ($a['priority_level'] !== $b['priority_level']) {
                return $a['priority_level'] <=> $b['priority_level'];
            }
            return $b['score'] <=> $a['score'];
        });

        $best = $ranked[0] ?? null;
        if (!$best || $best['priority_level'] >= 8) {
            // No hubo coincidencia suficiente
            return ['status' => 'NOT_FOUND', 'asset' => null, 'message' => "No se encontró medio visual para '{$target}'"];
        }

        // Detección de Empates (AMBIGUOUS_MEDIA_MATCH)
        if (count($ranked) > 1) {
            $second = $ranked[1];
            if ($best['priority_level'] === $second['priority_level']) {
                $diff = abs($best['score'] - $second['score']);
                if ($diff < 0.15 && $best['asset']->id !== $second['asset']->id) {
                    // Si ambos tienen igual nivel y score cercano, verificar desempate por display_order o context
                    if ($lastMediaId && $best['asset']->id === $lastMediaId) {
                        // Desempate por contexto
                    } elseif ($best['asset']->display_order !== $second['asset']->display_order) {
                        // El orden de visualización resuelve
                    } else {
                        return [
                            'status' => 'AMBIGUOUS_MEDIA_MATCH',
                            'asset' => null,
                            'candidates' => [$best['asset'], $second['asset']],
                            'message' => "Coincidencia ambigua entre múltiples cartas para '{$target}'",
                        ];
                    }
                }
            }
        }

        return [
            'status' => 'MATCH_EXACT',
            'asset' => $best['asset'],
            'priority_level' => $best['priority_level'],
            'score' => $best['score'],
            'resolution_type' => $best['resolution_type'],
            'match_concept' => $best['match_concept'],
            'dispatch_allowed' => true,
        ];
    }

    /**
     * Evaluación lexicográfica del activo
     */
    private function evaluateLexicographicalCandidate(
        int $tenantId,
        MediaAsset $asset,
        string $normTarget,
        ?int $lastMediaId,
        ?int $catalogVersionId
    ): array {
        $categoryNorm = Str::of($asset->category ?? '')->lower()->ascii()->trim()->squish()->toString();
        $titleNorm = Str::of($asset->title)->lower()->ascii()->trim()->squish()->toString();

        // Nivel 3: Coincidencia Exacta con Categoría o Título Dedicado (DEDICATED ASSET)
        if ($categoryNorm === $normTarget || $titleNorm === $normTarget || str_contains($titleNorm, $normTarget) || str_contains($categoryNorm, $normTarget)) {
            // Diferenciar si es carta especializada vs carta general
            $isGeneral = ($asset->display_order === 1 || str_contains($categoryNorm, 'general') || str_contains($titleNorm, 'general'));
            $isExactTargetGeneral = in_array($normTarget, ['carta', 'menu', 'la carta', 'el menu', 'carta general']);

            if (!$isGeneral || $isExactTargetGeneral) {
                return [
                    'priority_level' => 3,
                    'secondary_score' => 1.00,
                    'resolution_type' => 'DEDICATED_ASSET',
                    'match_concept' => $asset->category,
                ];
            }
        }

        // Nivel 4: Coincidencia en Media Semantic Index con Concepto Primario o Región Dedicada
        $indexMatches = MediaSemanticIndex::where('tenant_id', $tenantId)
            ->where('media_asset_id', $asset->id)
            ->with('concept')
            ->get();

        foreach ($indexMatches as $idx) {
            $cNorm = $idx->concept?->normalized_concept ?? '';
            if ($cNorm === $normTarget || str_contains($normTarget, $cNorm) || str_contains($cNorm, $normTarget)) {
                if ($idx->is_primary || $idx->evidence_type === 'REGION_MATCH') {
                    return [
                        'priority_level' => 4,
                        'secondary_score' => 0.90 + ($idx->match_score * 0.05),
                        'resolution_type' => 'DEDICATED_REGION',
                        'match_concept' => $idx->concept?->concept,
                    ];
                }
                if ($idx->evidence_type === 'PRODUCT_MATCH' || $idx->evidence_type === 'DIRECT_CONCEPT') {
                    return [
                        'priority_level' => 5,
                        'secondary_score' => 0.80 + ($idx->match_score * 0.05),
                        'resolution_type' => $idx->evidence_type,
                        'match_concept' => $idx->concept?->concept,
                    ];
                }
            }
        }

        // Nivel 6: Coincidencia mediante Grafo de Conceptos Relacionados (ej. maracumango -> mango)
        $relatedConceptIds = MediaSemanticConceptRelation::where('tenant_id', $tenantId)
            ->whereHas('targetConcept', function ($q) use ($normTarget) {
                $q->where('normalized_concept', $normTarget);
            })
            ->pluck('source_concept_id');

        if ($relatedConceptIds->isNotEmpty()) {
            $relatedMatch = MediaSemanticIndex::where('tenant_id', $tenantId)
                ->where('media_asset_id', $asset->id)
                ->whereIn('concept_id', $relatedConceptIds)
                ->first();

            if ($relatedMatch) {
                return [
                    'priority_level' => 6,
                    'secondary_score' => 0.70,
                    'resolution_type' => 'RELATION_GRAPH_MATCH',
                    'match_concept' => $normTarget,
                ];
            }
        }

        // Nivel 7: Continuidad por Contexto Conversacional (Referente Anafórico previo)
        if ($lastMediaId && (int)$asset->id === (int)$lastMediaId && in_array($normTarget, ['esa', 'esa carta', 'la anterior', 'mandamela', 'mandamela otra vez', 'verla'])) {
            return [
                'priority_level' => 2, // Anafórico tiene alta prioridad contextual
                'secondary_score' => 0.95,
                'resolution_type' => 'CONVERSATIONAL_REFERENT',
                'match_concept' => 'REFERENT',
            ];
        }

        // Nivel 8: Fallback General si la consulta es genérica
        if (in_array($normTarget, ['carta', 'menu', 'la carta', 'el menu']) && $asset->display_order === 1) {
            return [
                'priority_level' => 5,
                'secondary_score' => 0.75,
                'resolution_type' => 'GENERAL_MENU_DEFAULT',
                'match_concept' => 'GENERAL',
            ];
        }

        return [
            'priority_level' => 9,
            'secondary_score' => 0.0,
            'resolution_type' => 'NO_MATCH',
            'match_concept' => null,
        ];
    }
}
