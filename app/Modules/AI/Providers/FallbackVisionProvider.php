<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use Illuminate\Support\Facades\Log;

class FallbackVisionProvider implements VisionProviderInterface
{
    private VisionProviderInterface $primary;
    private VisionProviderInterface $secondary;

    public function __construct(
        ?VisionProviderInterface $primary = null,
        ?VisionProviderInterface $secondary = null
    ) {
        $this->primary = $primary ?? new OpenAiVisionProvider();
        $this->secondary = $secondary ?? new GeminiVisionProvider();
    }

    public function analyzeMenuImage(string $imageBase64, ?string $mimeType = 'image/jpeg'): VisionMenuExtractionDTO
    {
        $primaryResult = $this->primary->analyzeMenuImage($imageBase64, $mimeType);

        // Si el primario responde adecuadamente (SUCCESS, LOW_CONFIDENCE, UNREADABLE)
        if (!$primaryResult->isTechnicalFailure()) {
            return $primaryResult;
        }

        Log::warning(
            "FallbackVisionProvider: Primary provider {$this->primary->getProviderName()} failed with status {$primaryResult->status}. Falling back to {$this->secondary->getProviderName()}."
        );

        // Fallback a proveedor secundario
        $secondaryResult = $this->secondary->analyzeMenuImage($imageBase64, $mimeType);

        if (!$secondaryResult->isTechnicalFailure()) {
            return $secondaryResult;
        }

        Log::error(
            "FallbackVisionProvider: Secondary provider {$this->secondary->getProviderName()} also failed with status {$secondaryResult->status}."
        );

        // Ambos proveedores fallaron técnicamente -> TECHNICAL_FAILURE
        return new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_TECHNICAL_FAILURE,
            globalConfidence: 0.0,
            categories: [],
            provider: 'FALLBACK_CHAIN (' . $this->primary->getProviderName() . ' + ' . $this->secondary->getProviderName() . ')',
            model: 'NONE',
            errorMessage: 'Both primary and secondary vision providers failed technically: Primary (' . ($primaryResult->errorMessage ?? $primaryResult->status) . '), Secondary (' . ($secondaryResult->errorMessage ?? $secondaryResult->status) . ')'
        );
    }

    public function getProviderName(): string
    {
        return 'FALLBACK_VISION';
    }
}
