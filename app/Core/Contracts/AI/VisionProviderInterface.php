<?php

namespace App\Core\Contracts\AI;

use App\Modules\AI\DTOs\VisionMenuExtractionDTO;

interface VisionProviderInterface
{
    /**
     * Analiza una imagen Base64 de carta/menú y retorna la extracción estructurada DTO.
     */
    public function analyzeMenuImage(string $imageBase64, ?string $mimeType = 'image/jpeg'): VisionMenuExtractionDTO;

    /**
     * Retorna el identificador del proveedor (OPENAI, GEMINI, FALLBACK, MOCK).
     */
    public function getProviderName(): string;
}
