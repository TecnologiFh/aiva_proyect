<?php

namespace Tests\Unit;

use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\AI\Providers\FallbackVisionProvider;
use App\Modules\AI\Providers\MockVisionProvider;
use Tests\TestCase;

class FallbackVisionProviderTest extends TestCase
{
    /**
     * G-A04: Fallback primario -> secundario cuando ocurre PROVIDER_ERROR.
     */
    public function test_ga04_fallback_switches_to_secondary_on_primary_technical_error(): void
    {
        // Primary = PROVIDER_ERROR, Secondary = SUCCESS
        $primary = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR);
        $secondary = new MockVisionProvider(
            forcedStatus: VisionMenuExtractionDTO::STATUS_SUCCESS,
            forcedGlobalConfidence: 0.90,
            customCategories: [
                new VisionMenuCategoryDTO(
                    name: 'Secundaria',
                    normalizedName: 'secundaria',
                    description: 'Categoría recuperada por Gemini',
                    items: [
                        new VisionMenuItemDTO(
                            name: 'Lomo Saltado',
                            normalizedName: 'lomo saltado',
                            basePrice: 32.0,
                            confidence: 0.92,
                            detectedPrices: [32.0]
                        )
                    ]
                )
            ]
        );

        $fallbackProvider = new FallbackVisionProvider($primary, $secondary);
        $result = $fallbackProvider->analyzeMenuImage(base64_encode('test-bytes'));

        $this->assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $result->status);
        $this->assertEquals('MOCK_VISION', $result->provider);
        $this->assertEquals('Lomo Saltado', $result->categories[0]->items[0]->name);
    }

    /**
     * G-A04: Si ambos proveedores fallan técnicamente, retornar TECHNICAL_FAILURE (Jamás UNREADABLE ni LOW_CONFIDENCE).
     */
    public function test_ga04_both_providers_technical_failure_returns_technical_failure_status(): void
    {
        $primary = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR);
        $secondary = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE);

        $fallbackProvider = new FallbackVisionProvider($primary, $secondary);
        $result = $fallbackProvider->analyzeMenuImage(base64_encode('test-bytes'));

        // Debe ser TECHNICAL_FAILURE explícito
        $this->assertEquals(VisionMenuExtractionDTO::STATUS_TECHNICAL_FAILURE, $result->status);
        $this->assertTrue($result->isTechnicalFailure());
        $this->assertFalse($result->isSuccess());

        // Verificar que NO se confunda con carta ilegible o baja confianza
        $this->assertNotEquals(VisionMenuExtractionDTO::STATUS_UNREADABLE, $result->status);
        $this->assertNotEquals(VisionMenuExtractionDTO::STATUS_LOW_CONFIDENCE, $result->status);
        $this->assertStringContainsString('Both primary and secondary vision providers failed', $result->errorMessage);
    }

    /**
     * G-A04: Si el primario responde SUCCESS de forma directa, no invoca al secundario.
     */
    public function test_ga04_primary_success_does_not_trigger_secondary(): void
    {
        $primary = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_SUCCESS);
        $secondary = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR);

        $fallbackProvider = new FallbackVisionProvider($primary, $secondary);
        $result = $fallbackProvider->analyzeMenuImage(base64_encode('test-bytes'));

        $this->assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $result->status);
        $this->assertFalse($result->isTechnicalFailure());
    }
}
