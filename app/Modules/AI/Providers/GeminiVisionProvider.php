<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiVisionProvider implements VisionProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? (config('services.gemini.api_key') ?: (env('GEMINI_API_KEY') ?: ''));
        $this->model = $model ?? (config('services.gemini.vision_model') ?: (env('GEMINI_VISION_MODEL') ?: 'gemini-1.5-flash'));
    }

    public function analyzeMenuImage(string $imageBase64, ?string $mimeType = 'image/jpeg'): VisionMenuExtractionDTO
    {
        $startTime = microtime(true);
        $mime = $mimeType ?: 'image/jpeg';

        if (empty($this->apiKey)) {
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR,
                provider: $this->getProviderName(),
                model: $this->model,
                errorMessage: 'GEMINI_API_KEY is missing'
            );
        }

        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64);

        $prompt = <<<PROMPT
Analiza la imagen de la carta o menú enviada y extrae categorías y productos en JSON estricto.
Estructura requerida:
{
  "global_confidence": 0.90,
  "categories": [
    {
      "name": "Entradas",
      "description": "Entradas frías y calientes",
      "items": [
        {
          "name": "Papa a la Huancaína",
          "description": "Papas con crema de huancaína",
          "base_price": 12.00,
          "price_raw": "S/ 12.00",
          "detected_prices": [12.00],
          "is_price_ambiguous": false,
          "confidence": 0.95,
          "is_available": true
        }
      ]
    }
  ]
}
PROMPT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime,
                                    'data' => $cleanBase64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                ],
            ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::warning("GeminiVisionProvider HTTP failure: " . $response->status());
                return new VisionMenuExtractionDTO(
                    status: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR,
                    provider: $this->getProviderName(),
                    model: $this->model,
                    latencyMs: $latencyMs,
                    errorMessage: "HTTP " . $response->status()
                );
            }

            $resData = $response->json();
            $rawContent = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($rawContent)) {
                return new VisionMenuExtractionDTO(
                    status: VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE,
                    provider: $this->getProviderName(),
                    model: $this->model,
                    latencyMs: $latencyMs,
                    rawResponse: json_encode($resData)
                );
            }

            $parsed = json_decode($rawContent, true);
            if (!is_array($parsed)) {
                return new VisionMenuExtractionDTO(
                    status: VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE,
                    provider: $this->getProviderName(),
                    model: $this->model,
                    latencyMs: $latencyMs,
                    rawResponse: $rawContent
                );
            }

            $inputTokens = $resData['usageMetadata']['promptTokenCount'] ?? 0;
            $outputTokens = $resData['usageMetadata']['candidatesTokenCount'] ?? 0;

            return $this->parseStructuredPayload(
                $parsed,
                $this->getProviderName(),
                $this->model,
                $latencyMs,
                $inputTokens,
                $outputTokens,
                $rawContent
            );

        } catch (\Throwable $e) {
            Log::error("GeminiVisionProvider Exception: " . $e->getMessage());
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR,
                provider: $this->getProviderName(),
                model: $this->model,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function parseStructuredPayload(
        array $parsed,
        string $provider,
        string $model,
        int $latencyMs,
        int $inputTokens,
        int $outputTokens,
        string $rawContent
    ): VisionMenuExtractionDTO {
        $globalConf = (float) ($parsed['global_confidence'] ?? 0.85);

        if (empty($parsed['categories']) && $globalConf < 0.40) {
            return new VisionMenuExtractionDTO(
                status: VisionMenuExtractionDTO::STATUS_UNREADABLE,
                globalConfidence: $globalConf,
                categories: [],
                provider: $provider,
                model: $model,
                latencyMs: $latencyMs,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                rawResponse: $rawContent
            );
        }

        $categoryDTOs = [];
        foreach ($parsed['categories'] ?? [] as $cat) {
            $catName = trim($cat['name'] ?? '');
            if (empty($catName)) continue;

            $itemDTOs = [];
            foreach ($cat['items'] ?? [] as $item) {
                $itemName = trim($item['name'] ?? '');
                if (empty($itemName)) continue;

                $detectedPrices = array_map('floatval', (array) ($item['detected_prices'] ?? []));
                $basePrice = isset($item['base_price']) && is_numeric($item['base_price']) ? (float) $item['base_price'] : null;

                if ($basePrice !== null && empty($detectedPrices)) {
                    $detectedPrices = [$basePrice];
                }

                $isAmbiguous = (bool) ($item['is_price_ambiguous'] ?? (count($detectedPrices) !== 1));

                $normalizedName = Str::of($itemName)->lower()->ascii()->trim()->squish()->toString();

                $itemDTOs[] = new VisionMenuItemDTO(
                    name: $itemName,
                    normalizedName: $normalizedName,
                    description: $item['description'] ?? null,
                    basePrice: $basePrice,
                    currency: $item['currency'] ?? 'PEN',
                    confidence: (float) ($item['confidence'] ?? $globalConf),
                    isAvailable: (bool) ($item['is_available'] ?? true),
                    isPriceAmbiguous: $isAmbiguous,
                    priceRaw: (string) ($item['price_raw'] ?? ''),
                    detectedPrices: $detectedPrices
                );
            }

            $normalizedCatName = Str::of($catName)->lower()->ascii()->trim()->squish()->toString();
            $categoryDTOs[] = new VisionMenuCategoryDTO(
                name: $catName,
                normalizedName: $normalizedCatName,
                description: $cat['description'] ?? null,
                items: $itemDTOs
            );
        }

        $status = ($globalConf < 0.80) 
            ? VisionMenuExtractionDTO::STATUS_LOW_CONFIDENCE 
            : VisionMenuExtractionDTO::STATUS_SUCCESS;

        return new VisionMenuExtractionDTO(
            status: $status,
            globalConfidence: $globalConf,
            categories: $categoryDTOs,
            provider: $provider,
            model: $model,
            latencyMs: $latencyMs,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            rawResponse: $rawContent
        );
    }

    public function getProviderName(): string
    {
        return 'GEMINI_VISION';
    }
}
