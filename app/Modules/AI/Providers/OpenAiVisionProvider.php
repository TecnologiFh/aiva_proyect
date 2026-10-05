<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenAiVisionProvider implements VisionProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? (config('services.openai.api_key') ?: (env('OPENAI_API_KEY') ?: ''));
        $this->model = $model ?? (config('services.openai.vision_model') ?: (env('OPENAI_VISION_MODEL') ?: 'gpt-4o'));
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
                errorMessage: 'OPENAI_API_KEY is missing'
            );
        }

        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64);

        $prompt = <<<PROMPT
Eres un sistema de Visión AI para restaurantes. Analiza la imagen del menú/carta adjunta y extrae las categorías y productos en formato JSON estricto.

Debes responder ÚNICAMENTE con un objeto JSON con la siguiente estructura exacta:
{
  "global_confidence": 0.95,
  "categories": [
    {
      "name": "Platos Principales",
      "description": "Nuestras especialidades de la casa",
      "items": [
        {
          "name": "Arroz Chaufa de Pollo",
          "description": "Chaufa tradicional con pollo y trozos de tortilla",
          "base_price": 18.00,
          "price_raw": "S/ 18.00",
          "detected_prices": [18.00],
          "is_price_ambiguous": false,
          "confidence": 0.96,
          "is_available": true
        }
      ]
    }
  ]
}

Reglas importantes:
- "global_confidence": número decimal de 0.0 a 1.0 representando la visibilidad y legibilidad general de la carta.
- "detected_prices": array con todos los valores numéricos de precio detectados para un producto (ejemplo: si dice "S/18 / S/20", incluir [18.0, 20.0]).
- "is_price_ambiguous": true si existen múltiples precios o si el precio es confuso/ilegible, de lo contrario false.
- Si la imagen es borrosa o ilegible, devuelve "global_confidence": 0.3 y categories como array vacío [].
PROMPT;

        try {
            $dataUrl = "data:{$mime};base64,{$cleanBase64}";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(20)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => $dataUrl],
                            ],
                        ],
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
            ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                Log::warning("OpenAiVisionProvider HTTP failure: " . $response->status());
                return new VisionMenuExtractionDTO(
                    status: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR,
                    provider: $this->getProviderName(),
                    model: $this->model,
                    latencyMs: $latencyMs,
                    errorMessage: "HTTP " . $response->status()
                );
            }

            $resData = $response->json();
            $rawContent = $resData['choices'][0]['message']['content'] ?? null;

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

            $inputTokens = $resData['usage']['prompt_tokens'] ?? 0;
            $outputTokens = $resData['usage']['completion_tokens'] ?? 0;

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
            Log::error("OpenAiVisionProvider Exception: " . $e->getMessage());
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
        return 'OPENAI_VISION';
    }
}
