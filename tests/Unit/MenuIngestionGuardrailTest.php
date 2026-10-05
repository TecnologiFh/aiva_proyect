<?php

namespace Tests\Unit;

use App\Modules\AI\DTOs\MenuGuardrailResultDTO;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\AI\Services\MenuIngestionGuardrailService;
use Tests\TestCase;

class MenuIngestionGuardrailTest extends TestCase
{
    private MenuIngestionGuardrailService $guardrailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardrailService = new MenuIngestionGuardrailService();
    }

    /**
     * G-A05: Confianza global < 0.80 o estado UNREADABLE bloquea completamente la ingesta.
     */
    public function test_ga05_global_confidence_below_080_blocks_entire_menu(): void
    {
        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_LOW_CONFIDENCE,
            globalConfidence: 0.75, // Menor a 0.80
            categories: [
                new VisionMenuCategoryDTO(
                    name: 'Entradas',
                    normalizedName: 'entradas',
                    items: [
                        new VisionMenuItemDTO(
                            name: 'Causa Limeña',
                            normalizedName: 'causa limena',
                            basePrice: 18.0,
                            confidence: 0.90,
                            detectedPrices: [18.0]
                        )
                    ]
                )
            ]
        );

        $result = $this->guardrailService->evaluateGuardrail($extraction);

        $this->assertEquals(MenuGuardrailResultDTO::STATUS_BLOCKED, $result->status);
        $this->assertFalse($result->isIngestable());
        $this->assertEmpty($result->acceptedCategories);
        $this->assertStringContainsString('por debajo del umbral seguro', $result->reasons[0]);
    }

    /**
     * G-A05: Carta marcada como UNREADABLE bloquea completamente la ingesta.
     */
    public function test_ga05_unreadable_status_blocks_entire_menu(): void
    {
        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_UNREADABLE,
            globalConfidence: 0.30,
            categories: []
        );

        $result = $this->guardrailService->evaluateGuardrail($extraction);

        $this->assertEquals(MenuGuardrailResultDTO::STATUS_BLOCKED, $result->status);
        $this->assertFalse($result->isIngestable());
    }

    /**
     * G-A06: Precio ambiguo (count(detectedPrices) != 1 o isPriceAmbiguous=true) envía el ítem a rejectedItems[].
     */
    public function test_ga06_ambiguous_price_rejects_item_individually_and_allows_valid_items(): void
    {
        $validItem1 = new VisionMenuItemDTO(
            name: 'Causa Limeña',
            normalizedName: 'causa limena',
            basePrice: 18.0,
            confidence: 0.95,
            isPriceAmbiguous: false,
            detectedPrices: [18.0]
        );

        $validItem2 = new VisionMenuItemDTO(
            name: 'Tequeños',
            normalizedName: 'tequenos',
            basePrice: 15.0,
            confidence: 0.92,
            isPriceAmbiguous: false,
            detectedPrices: [15.0]
        );

        // Ítem ambiguo con múltiples precios detectados (ej: S/ 12 / S/ 15)
        $ambiguousItem = new VisionMenuItemDTO(
            name: 'Chicha Morada Jarra',
            normalizedName: 'chicha morada jarra',
            basePrice: null,
            confidence: 0.88,
            isPriceAmbiguous: true,
            priceRaw: 'S/ 12.00 / S/ 15.00',
            detectedPrices: [12.0, 15.0]
        );

        // Ítem sin precio válido (precio <= 0)
        $invalidPriceItem = new VisionMenuItemDTO(
            name: 'Inka Kola 1.5L',
            normalizedName: 'inka kola 15l',
            basePrice: 0.0,
            confidence: 0.90,
            detectedPrices: [0.0]
        );

        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_SUCCESS,
            globalConfidence: 0.92,
            categories: [
                new VisionMenuCategoryDTO(
                    name: 'Entradas y Bebidas',
                    normalizedName: 'entradas y bebidas',
                    items: [$validItem1, $validItem2, $ambiguousItem, $invalidPriceItem]
                )
            ]
        );

        $result = $this->guardrailService->evaluateGuardrail($extraction);

        // Debe dar PARTIAL_REVIEW
        $this->assertEquals(MenuGuardrailResultDTO::STATUS_PARTIAL_REVIEW, $result->status);
        $this->assertTrue($result->isIngestable());

        // Debe aceptar únicamente los 2 ítems válidos
        $this->assertCount(1, $result->acceptedCategories);
        $this->assertCount(2, $result->acceptedCategories[0]->items);
        $this->assertEquals('Causa Limeña', $result->acceptedCategories[0]->items[0]->name);
        $this->assertEquals('Tequeños', $result->acceptedCategories[0]->items[1]->name);

        // Debe rechazar individualmente los 2 ítems inválidos
        $this->assertCount(2, $result->rejectedItems);
        $this->assertEquals('Chicha Morada Jarra', $result->rejectedItems[0]['name']);
        $this->assertEquals('Inka Kola 1.5L', $result->rejectedItems[1]['name']);
    }

    /**
     * Prueba de fallo técnico en Guardrail.
     */
    public function test_technical_failure_extraction_returns_technical_failure_guardrail_result(): void
    {
        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_TECHNICAL_FAILURE,
            globalConfidence: 0.0,
            errorMessage: 'Network timeout'
        );

        $result = $this->guardrailService->evaluateGuardrail($extraction);

        $this->assertEquals(MenuGuardrailResultDTO::STATUS_TECHNICAL_FAILURE, $result->status);
        $this->assertFalse($result->isIngestable());
        $this->assertEmpty($result->acceptedCategories);
    }
}
