<?php

namespace App\Modules\Multimodal\Runtime\Services;

use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualContextDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualEntityDTO;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use Illuminate\Support\Str;

/**
 * Servicio de Resolución de Contexto Visual Conversacional.
 * Permite que una imagen previa provea contexto a mensajes posteriores sin requerir reenvío.
 */
class VisualContextResolverService
{
    private VisualEvidenceNormalizationService $normalizer;

    public function __construct(?VisualEvidenceNormalizationService $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new VisualEvidenceNormalizationService();
    }

    /**
     * Resuelve una entidad visual a partir de la entrada del cliente y la evidencia visual activa.
     *
     * @param string $customerMessage Mensaje del cliente (ej: "Quiero la pechuga de la carta")
     * @param StructuredVisualEvidenceDTO|array|null $visualEvidence Evidencia estructurada activa en la conversación
     * @param array<string, mixed> $context Metadatos de contexto
     * @return array<string, mixed>
     */
    public function resolveFromVisualContext(
        string $customerMessage,
        StructuredVisualEvidenceDTO|array|null $visualEvidence,
        array $context = []
    ): array {
        if ($visualEvidence === null) {
            return [
                'status' => VisualResolutionStatus::NOT_FOUND->value,
                'entity' => null,
                'reason' => 'No hay contexto visual activo en la conversación.',
            ];
        }

        $entities = [];
        if ($visualEvidence instanceof StructuredVisualEvidenceDTO) {
            $entities = $visualEvidence->entities;
        } elseif (is_array($visualEvidence) && isset($visualEvidence['entities'])) {
            $entities = $visualEvidence['entities'];
        }

        if (empty($entities)) {
            return [
                'status' => VisualResolutionStatus::NOT_FOUND->value,
                'entity' => null,
                'reason' => 'La evidencia visual activa no contiene entidades comerciales estructuradas.',
            ];
        }

        $normInput = $this->normalizer->normalizeText($customerMessage);

        // Limpiar preámbulos conversacionales ("quiero", "dame", "la", "de la carta", "por favor", etc.)
        $cleanSearch = preg_replace('/^(?:quiero|deseo|dame|deme|traeme|pido|favor\s+de\s+darme|por\s+favor)\s+/i', '', $normInput);
        $cleanSearch = preg_replace('/\b(?:de\s+la\s+carta|de\s+la\s+foto|del\s+menu|de\s+la\s+imagen)\b/i', '', $cleanSearch);
        $cleanSearch = trim(preg_replace('/\s+/', ' ', $cleanSearch));
        $cleanSearch = trim(preg_replace('/^(?:el|la|los|las|un|una|unos|unas)\s+/i', '', $cleanSearch));

        // Si el cliente pide una sección completa (ej: "Quiero la carta de parrillas", "muéstrame parrillas")
        $sectionMatches = [];
        foreach ($entities as $e) {
            $eType = $e instanceof VisualEntityDTO ? $e->type : ($e['type'] ?? '');
            $eName = $e instanceof VisualEntityDTO ? $e->normalizedName : ($e['normalized_name'] ?? '');
            $rawName = $e instanceof VisualEntityDTO ? $e->name : ($e['name'] ?? '');

            if ($eType === 'MENU_SECTION' && (!empty($cleanSearch) && (str_contains($cleanSearch, $eName) || str_contains($eName, $cleanSearch)))) {
                $sectionMatches[] = $e;
            }
        }

        if (count($sectionMatches) === 1 && (str_contains($normInput, 'carta') || str_contains($normInput, 'seccion') || str_contains($normInput, 'categoria'))) {
            $matchedSection = $sectionMatches[0];
            return [
                'status' => VisualResolutionStatus::MATCH_EXACT->value,
                'entity_type' => 'MENU_SECTION',
                'entity' => $matchedSection,
                'name' => $matchedSection instanceof VisualEntityDTO ? $matchedSection->name : $matchedSection['name'],
                'reason' => 'Sección visual resuelta unívocamente desde el contexto visual.',
            ];
        }

        // Buscar coincidencia en productos individuales (PRODUCT_ITEM, CATALOG_ITEM)
        $productCandidates = [];
        foreach ($entities as $e) {
            $eType = $e instanceof VisualEntityDTO ? $e->type : ($e['type'] ?? '');
            $eName = $e instanceof VisualEntityDTO ? $e->normalizedName : ($e['normalized_name'] ?? '');
            $rawName = $e instanceof VisualEntityDTO ? $e->name : ($e['name'] ?? '');

            if ($eType === 'MENU_SECTION') {
                continue;
            }

            // Coincidencia exacta o contenida
            if ($eName === $cleanSearch || str_contains($cleanSearch, $eName) || str_contains($eName, $cleanSearch)) {
                $productCandidates[] = $e;
            }
        }

        // Caso 1: Cero coincidencias
        if (empty($productCandidates)) {
            return [
                'status' => VisualResolutionStatus::NOT_FOUND->value,
                'entity' => null,
                'reason' => "No se encontró ningún producto en la imagen que coincida con '{$customerMessage}'.",
            ];
        }

        // Caso 2: Ambigüedad (ej: "Pechuga", "Pechuga a la Parrilla", "Pechuga Especial")
        if (count($productCandidates) > 1) {
            $candidateNames = array_map(
                fn($c) => $c instanceof VisualEntityDTO ? $c->name : ($c['name'] ?? ''),
                $productCandidates
            );

            return [
                'status' => VisualResolutionStatus::AMBIGUOUS->value,
                'candidates' => $candidateNames,
                'reason' => "Se encontraron múltiples opciones en la carta: " . implode(', ', $candidateNames) . ". Por favor especifica cuál deseas.",
            ];
        }

        // Caso 3: Coincidencia única
        $resolved = $productCandidates[0];
        $resolvedName = $resolved instanceof VisualEntityDTO ? $resolved->name : $resolved['name'];
        $observedPrice = $resolved instanceof VisualEntityDTO ? $resolved->observedPrice : ($resolved['observed_price'] ?? null);

        return [
            'status' => VisualResolutionStatus::MATCH_EXACT->value,
            'entity_type' => 'PRODUCT_ITEM',
            'entity' => $resolved,
            'name' => $resolvedName,
            'observed_price' => $observedPrice,
            'reason' => "Producto '{$resolvedName}' resuelto unívocamente desde el contexto visual de la carta.",
        ];
    }
}
