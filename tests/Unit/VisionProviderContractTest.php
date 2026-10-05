<?php

namespace Tests\Unit;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\AI\Providers\GeminiVisionProvider;
use App\Modules\AI\Providers\MockVisionProvider;
use App\Modules\AI\Providers\OpenAiVisionProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisionProviderContractTest extends TestCase
{
    /**
     * G-A01: Interface y DTOs reales e inmutables.
     */
    public function test_ga01_contracts_and_dtos_are_immutable_and_typed(): void
    {
        $item = new VisionMenuItemDTO(
            name: 'Chaufa Amazónico',
            normalizedName: 'chaufa amazonico',
            description: 'Con cecina',
            basePrice: 18.00,
            currency: 'PEN',
            confidence: 0.95,
            isAvailable: true,
            isPriceAmbiguous: false,
            priceRaw: 'S/ 18.00',
            detectedPrices: [18.00]
        );

        $category = new VisionMenuCategoryDTO(
            name: 'Fondos',
            normalizedName: 'fondos',
            description: 'Platos principales',
            items: [$item]
        );

        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_SUCCESS,
            globalConfidence: 0.95,
            categories: [$category],
            provider: 'MOCK_VISION',
            model: 'mock-v1'
        );

        $this->assertTrue($extraction->isSuccess());
        $this->assertFalse($extraction->isTechnicalFailure());
        $this->assertEquals('Chaufa Amazónico', $extraction->categories[0]->items[0]->name);
        $this->assertEquals([18.00], $extraction->categories[0]->items[0]->detectedPrices);
    }

    /**
     * G-A02: OpenAI Vision Provider recibe modelo dinámico de config y envía payload Base64 con JSON Schema.
     */
    public function test_ga02_openai_vision_provider_uses_config_and_multimodal_payload(): void
    {
        config(['services.openai.api_key' => 'test-key-123']);
        config(['services.openai.vision_model' => 'gpt-4o-test-model']);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'global_confidence' => 0.92,
                                'categories' => [
                                    [
                                        'name' => 'Bebidas',
                                        'items' => [
                                            [
                                                'name' => 'Chicha Morada',
                                                'base_price' => 8.0,
                                                'price_raw' => 'S/ 8.00',
                                                'detected_prices' => [8.0],
                                                'is_price_ambiguous' => false,
                                                'confidence' => 0.95,
                                                'is_available' => true
                                            ]
                                        ]
                                    ]
                                ]
                            ])
                        ]
                    ]
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]
            ], 200)
        ]);

        $provider = new OpenAiVisionProvider();
        $this->assertInstanceOf(VisionProviderInterface::class, $provider);
        $this->assertEquals('OPENAI_VISION', $provider->getProviderName());

        $dummyBase64 = base64_encode('fake-image-bytes');
        $result = $provider->analyzeMenuImage($dummyBase64, 'image/png');

        $this->assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $result->status);
        $this->assertEquals('gpt-4o-test-model', $result->model);
        $this->assertEquals('Chicha Morada', $result->categories[0]->items[0]->name);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                   $request['model'] === 'gpt-4o-test-model' &&
                   str_contains($request['messages'][0]['content'][1]['image_url']['url'], 'data:image/png;base64,');
        });
    }

    /**
     * G-A03: Gemini Vision Provider recibe modelo dinámico de config y envía inlineData.
     */
    public function test_ga03_gemini_vision_provider_uses_config_and_inlinedata_payload(): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);
        config(['services.gemini.vision_model' => 'gemini-1.5-pro-test']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'global_confidence' => 0.88,
                                        'categories' => [
                                            [
                                                'name' => 'Postres',
                                                'items' => [
                                                    [
                                                        'name' => 'Pie de Limón',
                                                        'base_price' => 14.0,
                                                        'price_raw' => 'S/ 14.00',
                                                        'detected_prices' => [14.0],
                                                        'is_price_ambiguous' => false,
                                                        'confidence' => 0.90,
                                                        'is_available' => true
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ],
                'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 40]
            ], 200)
        ]);

        $provider = new GeminiVisionProvider();
        $this->assertInstanceOf(VisionProviderInterface::class, $provider);
        $this->assertEquals('GEMINI_VISION', $provider->getProviderName());

        $dummyBase64 = base64_encode('fake-gemini-bytes');
        $result = $provider->analyzeMenuImage($dummyBase64, 'image/jpeg');

        $this->assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $result->status);
        $this->assertEquals('gemini-1.5-pro-test', $result->model);
        $this->assertEquals('Pie de Limón', $result->categories[0]->items[0]->name);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-1.5-pro-test:generateContent') &&
                   $request['contents'][0]['parts'][1]['inlineData']['mimeType'] === 'image/jpeg' &&
                   $request['contents'][0]['parts'][1]['inlineData']['data'] === base64_encode('fake-gemini-bytes');
        });
    }
}
