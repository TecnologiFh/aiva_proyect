<?php

namespace App\Modules\AI\Services;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\DTOs\MenuGuardrailResultDTO;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\AI\Providers\FallbackVisionProvider;
use App\Modules\AI\Providers\GeminiVisionProvider;
use App\Modules\AI\Providers\MockVisionProvider;
use App\Modules\AI\Providers\OpenAiVisionProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;

class P310BloqueA1VerificationService
{
    public function runVerification(): array
    {
        $results = [];
        $gates = [
            'G-A01' => 'FAIL',
            'G-A02' => 'FAIL',
            'G-A03' => 'FAIL',
            'G-A04' => 'FAIL',
            'G-A05' => 'FAIL',
            'G-A06' => 'FAIL',
        ];

        // --- G-A01: Interface & DTO Immutability ---
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
            items: [$item]
        );
        $extraction = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_SUCCESS,
            globalConfidence: 0.95,
            categories: [$category],
            provider: 'MOCK_VISION',
            model: 'mock-v1'
        );

        Assert::assertTrue($extraction->isSuccess());
        Assert::assertFalse($extraction->isTechnicalFailure());
        Assert::assertEquals('Chaufa Amazónico', $extraction->categories[0]->items[0]->name);
        Assert::assertEquals([18.00], $extraction->categories[0]->items[0]->detectedPrices);

        $results[] = ['gate' => 'G-A01', 'test' => 'DTOs & Interface Immutability', 'status' => 'PASS'];
        $gates['G-A01'] = 'PASS';

        // --- G-A02: OpenAI Vision Provider Config & Multi-modal Payload ---
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

        $openAiProvider = new OpenAiVisionProvider();
        Assert::assertInstanceOf(VisionProviderInterface::class, $openAiProvider);
        Assert::assertEquals('OPENAI_VISION', $openAiProvider->getProviderName());

        $openAiRes = $openAiProvider->analyzeMenuImage(base64_encode('fake-image-bytes'), 'image/png');
        Assert::assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $openAiRes->status);
        Assert::assertEquals('gpt-4o-test-model', $openAiRes->model);
        Assert::assertEquals('Chicha Morada', $openAiRes->categories[0]->items[0]->name);

        $results[] = ['gate' => 'G-A02', 'test' => 'OpenAI Vision Payload & Dynamic Config Model', 'status' => 'PASS'];
        $gates['G-A02'] = 'PASS';

        // --- G-A03: Gemini Vision Provider Config & inlineData Payload ---
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

        $geminiProvider = new GeminiVisionProvider();
        Assert::assertInstanceOf(VisionProviderInterface::class, $geminiProvider);
        Assert::assertEquals('GEMINI_VISION', $geminiProvider->getProviderName());

        $geminiRes = $geminiProvider->analyzeMenuImage(base64_encode('fake-gemini-bytes'), 'image/jpeg');
        Assert::assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $geminiRes->status);
        Assert::assertEquals('gemini-1.5-pro-test', $geminiRes->model);
        Assert::assertEquals('Pie de Limón', $geminiRes->categories[0]->items[0]->name);

        $results[] = ['gate' => 'G-A03', 'test' => 'Gemini Vision inlineData & Dynamic Config Model', 'status' => 'PASS'];
        $gates['G-A03'] = 'PASS';

        // --- G-A04: Fallback Vision Provider & TECHNICAL_FAILURE ---
        $primaryError = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_PROVIDER_ERROR);
        $secondarySuccess = new MockVisionProvider(
            forcedStatus: VisionMenuExtractionDTO::STATUS_SUCCESS,
            forcedGlobalConfidence: 0.90,
            customCategories: [
                new VisionMenuCategoryDTO(
                    name: 'Secundaria',
                    normalizedName: 'secundaria',
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

        $fallback = new FallbackVisionProvider($primaryError, $secondarySuccess);
        $fallbackRes = $fallback->analyzeMenuImage(base64_encode('test-bytes'));
        Assert::assertEquals(VisionMenuExtractionDTO::STATUS_SUCCESS, $fallbackRes->status);
        Assert::assertEquals('Lomo Saltado', $fallbackRes->categories[0]->items[0]->name);

        // Fallback cuando AMBOS fallan -> TECHNICAL_FAILURE (Jamás UNREADABLE ni LOW_CONFIDENCE)
        $secondaryError = new MockVisionProvider(forcedStatus: VisionMenuExtractionDTO::STATUS_INVALID_RESPONSE);
        $bothFailed = new FallbackVisionProvider($primaryError, $secondaryError);
        $bothRes = $bothFailed->analyzeMenuImage(base64_encode('test-bytes'));

        Assert::assertEquals(VisionMenuExtractionDTO::STATUS_TECHNICAL_FAILURE, $bothRes->status);
        Assert::assertTrue($bothRes->isTechnicalFailure());
        Assert::assertNotEquals(VisionMenuExtractionDTO::STATUS_UNREADABLE, $bothRes->status);
        Assert::assertNotEquals(VisionMenuExtractionDTO::STATUS_LOW_CONFIDENCE, $bothRes->status);

        $results[] = ['gate' => 'G-A04', 'test' => 'Fallback Conmutation & TECHNICAL_FAILURE Isolation', 'status' => 'PASS'];
        $gates['G-A04'] = 'PASS';

        // --- G-A05: Guardrail Global Confidence < 0.80 Block ---
        $guardrailService = new MenuIngestionGuardrailService();
        $lowConfExt = new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_LOW_CONFIDENCE,
            globalConfidence: 0.75,
            categories: [
                new VisionMenuCategoryDTO(
                    name: 'Entradas',
                    normalizedName: 'entradas',
                    items: [new VisionMenuItemDTO(name: 'Causa Limeña', basePrice: 18.0, confidence: 0.90, detectedPrices: [18.0])]
                )
            ]
        );
        $lowConfRes = $guardrailService->evaluateGuardrail($lowConfExt);
        Assert::assertEquals(MenuGuardrailResultDTO::STATUS_BLOCKED, $lowConfRes->status);
        Assert::assertFalse($lowConfRes->isIngestable());
        Assert::assertEmpty($lowConfRes->acceptedCategories);

        $results[] = ['gate' => 'G-A05', 'test' => 'Global Confidence < 0.80 Block', 'status' => 'PASS'];
        $gates['G-A05'] = 'PASS';

        // --- G-A06: Item Ambiguity Filter ($detectedPrices) & Partial Review ---
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
        $ambiguousItem = new VisionMenuItemDTO(
            name: 'Chicha Morada Jarra',
            normalizedName: 'chicha morada jarra',
            basePrice: null,
            confidence: 0.88,
            isPriceAmbiguous: true,
            priceRaw: 'S/ 12.00 / S/ 15.00',
            detectedPrices: [12.0, 15.0]
        );
        $invalidPriceItem = new VisionMenuItemDTO(
            name: 'Inka Kola 1.5L',
            normalizedName: 'inka kola 15l',
            basePrice: 0.0,
            confidence: 0.90,
            detectedPrices: [0.0]
        );

        $extractionPartial = new VisionMenuExtractionDTO(
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

        $partialRes = $guardrailService->evaluateGuardrail($extractionPartial);
        Assert::assertEquals(MenuGuardrailResultDTO::STATUS_PARTIAL_REVIEW, $partialRes->status);
        Assert::assertTrue($partialRes->isIngestable());
        Assert::assertCount(1, $partialRes->acceptedCategories);
        Assert::assertCount(2, $partialRes->acceptedCategories[0]->items);
        Assert::assertEquals('Causa Limeña', $partialRes->acceptedCategories[0]->items[0]->name);
        Assert::assertEquals('Tequeños', $partialRes->acceptedCategories[0]->items[1]->name);
        Assert::assertCount(2, $partialRes->rejectedItems);
        Assert::assertEquals('Chicha Morada Jarra', $partialRes->rejectedItems[0]['name']);
        Assert::assertEquals('Inka Kola 1.5L', $partialRes->rejectedItems[1]['name']);

        $results[] = ['gate' => 'G-A06', 'test' => 'Granular Ambiguity Rejection ($detectedPrices) & Partial Review', 'status' => 'PASS'];
        $gates['G-A06'] = 'PASS';

        $allGatesPassed = !in_array('FAIL', $gates, true);

        return [
            'status' => 'COMPLETED',
            'exit_code' => 0,
            'scope' => 'P3.10-A BLOQUE A1 ONLY',
            'all_gates_passed' => $allGatesPassed,
            'gates' => $gates,
            'tests_summary' => [
                'total' => count($results),
                'passed' => count(array_filter($results, fn($r) => $r['status'] === 'PASS')),
            ],
            'results' => $results,
            'timestamp' => now()->toIso8601String()
        ];
    }
}
