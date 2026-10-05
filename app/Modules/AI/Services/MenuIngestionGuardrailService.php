<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\DTOs\MenuGuardrailResultDTO;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;

class MenuIngestionGuardrailService
{
    public const GLOBAL_CONFIDENCE_THRESHOLD = 0.80;
    public const ITEM_CONFIDENCE_THRESHOLD = 0.70;

    /**
     * Evalúa el resultado del Vision AI Provider y aplica las reglas de guardrail de 2 niveles.
     */
    public function evaluateGuardrail(VisionMenuExtractionDTO $extraction): MenuGuardrailResultDTO
    {
        // 1. Manejo explícito de Fallo Técnico de Proveedores (0 DB mutation, NO marcar ilegible)
        if ($extraction->isTechnicalFailure()) {
            return new MenuGuardrailResultDTO(
                status: MenuGuardrailResultDTO::STATUS_TECHNICAL_FAILURE,
                globalConfidence: 0.0,
                acceptedCategories: [],
                rejectedItems: [],
                reasons: [
                    'Fallos técnicos en la cadena de proveedores de visión: ' . ($extraction->errorMessage ?? $extraction->status)
                ]
            );
        }

        // 2. Nivel Global: Si globalConfidence < 0.80 o estado UNREADABLE -> Bloqueo completo sin ingesta
        if ($extraction->status === VisionMenuExtractionDTO::STATUS_UNREADABLE || $extraction->globalConfidence < self::GLOBAL_CONFIDENCE_THRESHOLD) {
            return new MenuGuardrailResultDTO(
                status: MenuGuardrailResultDTO::STATUS_BLOCKED,
                globalConfidence: $extraction->globalConfidence,
                acceptedCategories: [],
                rejectedItems: [],
                reasons: [
                    "Confianza global de extracción ({$extraction->globalConfidence}) por debajo del umbral seguro (0.80) o menú ilegible."
                ]
            );
        }

        // 3. Nivel por Ítem: Filtrado granular
        $acceptedCategories = [];
        $rejectedItems = [];

        foreach ($extraction->categories as $category) {
            $acceptedItems = [];

            foreach ($category->items as $item) {
                $reasons = [];

                if (empty(trim($item->name))) {
                    $reasons[] = 'Nombre del producto vacío o nulo.';
                }

                if ($item->confidence < self::ITEM_CONFIDENCE_THRESHOLD) {
                    $reasons[] = "Confianza del ítem ({$item->confidence}) menor al umbral (0.70).";
                }

                if ($item->basePrice === null || $item->basePrice <= 0.0) {
                    $reasons[] = 'Precio base inexistente o menor/igual a 0.';
                }

                if ($item->isPriceAmbiguous || count($item->detectedPrices) !== 1) {
                    $reasons[] = 'Precio ambiguo detectado (múltiples precios o bandera de ambigüedad activa).';
                }

                if (empty($reasons)) {
                    $acceptedItems[] = $item;
                } else {
                    $rejectedItems[] = [
                        'category' => $category->name,
                        'name' => $item->name,
                        'base_price' => $item->basePrice,
                        'price_raw' => $item->priceRaw,
                        'detected_prices' => $item->detectedPrices,
                        'is_price_ambiguous' => $item->isPriceAmbiguous,
                        'confidence' => $item->confidence,
                        'reasons' => $reasons,
                    ];
                }
            }

            if (!empty($acceptedItems)) {
                $acceptedCategories[] = new VisionMenuCategoryDTO(
                    name: $category->name,
                    normalizedName: $category->normalizedName,
                    description: $category->description,
                    items: $acceptedItems
                );
            }
        }

        // 4. Determinar estado final de ingesta
        if (empty($acceptedCategories)) {
            return new MenuGuardrailResultDTO(
                status: MenuGuardrailResultDTO::STATUS_BLOCKED,
                globalConfidence: $extraction->globalConfidence,
                acceptedCategories: [],
                rejectedItems: $rejectedItems,
                reasons: ['Todos los productos de la carta fueron rechazados por validaciones del guardrail.']
            );
        }

        $finalStatus = !empty($rejectedItems) 
            ? MenuGuardrailResultDTO::STATUS_PARTIAL_REVIEW 
            : MenuGuardrailResultDTO::STATUS_ACCEPTED;

        return new MenuGuardrailResultDTO(
            status: $finalStatus,
            globalConfidence: $extraction->globalConfidence,
            acceptedCategories: $acceptedCategories,
            rejectedItems: $rejectedItems,
            reasons: !empty($rejectedItems) ? ['Algunos ítems con precio ambiguo o baja confianza fueron rechazados.'] : []
        );
    }
}
