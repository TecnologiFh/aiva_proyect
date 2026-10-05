<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;

class MockVisionProvider implements VisionProviderInterface
{
    private string $forcedStatus;
    private float $forcedGlobalConfidence;
    private ?array $customCategories;

    public function __construct(
        string $forcedStatus = VisionMenuExtractionDTO::STATUS_SUCCESS,
        float $forcedGlobalConfidence = 0.95,
        ?array $customCategories = null
    ) {
        $this->forcedStatus = $forcedStatus;
        $this->forcedGlobalConfidence = $forcedGlobalConfidence;
        $this->customCategories = $customCategories;
    }

    public function analyzeMenuImage(string $imageBase64, ?string $mimeType = 'image/jpeg'): VisionMenuExtractionDTO
    {
        if ($this->forcedStatus === VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR) {
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR,
                provider: $this->getProviderName(),
                model: 'mock-vision-v1',
                errorMessage: 'Mock provider simulated network failure'
            );
        }

        if ($this->forcedStatus === VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE) {
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE,
                provider: $this->getProviderName(),
                model: 'mock-vision-v1',
                errorMessage: 'Mock provider simulated invalid JSON'
            );
        }

        if ($this->forcedStatus === VisionMenuExtractionDTO::STATUS_UNREADABLE) {
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_UNREADABLE,
                globalConfidence: 0.30,
                categories: [],
                provider: $this->getProviderName(),
                model: 'mock-vision-v1'
            );
        }

        $categories = $this->customCategories ?? [
            new VisionMenuCategoryDTO(
                name: 'Entradas',
                normalizedName: 'entradas',
                description: 'Entradas tradicionales',
                items: [
                    new VisionMenuItemDTO(
                        name: 'Causa Limeña',
                        normalizedName: 'causa limena',
                        description: 'Causa rellena de pollo',
                        basePrice: 18.00,
                        currency: 'PEN',
                        confidence: 0.95,
                        isAvailable: true,
                        isPriceAmbiguous: false,
                        priceRaw: 'S/ 18.00',
                        detectedPrices: [18.00]
                    ),
                    new VisionMenuItemDTO(
                        name: 'Tequeños',
                        normalizedName: 'tequenos',
                        description: 'Con guacamole',
                        basePrice: 15.00,
                        currency: 'PEN',
                        confidence: 0.92,
                        isAvailable: true,
                        isPriceAmbiguous: false,
                        priceRaw: 'S/ 15.00',
                        detectedPrices: [15.00]
                    ),
                ]
            ),
            new VisionMenuCategoryDTO(
                name: 'Fondos',
                normalizedName: 'fondos',
                description: 'Platos de fondo',
                items: [
                    new VisionMenuItemDTO(
                        name: 'Chaufa Amazónico',
                        normalizedName: 'chaufa amazonico',
                        description: 'Con cecina y tacacho',
                        basePrice: 18.00,
                        currency: 'PEN',
                        confidence: 0.98,
                        isAvailable: true,
                        isPriceAmbiguous: false,
                        priceRaw: 'S/ 18.00',
                        detectedPrices: [18.00]
                    ),
                ]
            ),
        ];

        return new VisionMenuExtractionDTO(
            status: $this->forcedStatus,
            globalConfidence: $this->forcedGlobalConfidence,
            categories: $categories,
            provider: $this->getProviderName(),
            model: 'mock-vision-v1',
            latencyMs: 50,
            inputTokens: 150,
            outputTokens: 80
        );
    }

    public function getProviderName(): string
    {
        return 'MOCK_VISION';
    }
}
