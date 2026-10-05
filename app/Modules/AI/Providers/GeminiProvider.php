<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\AIProviderInterface;
use App\Modules\AI\DTOs\AIAnalysisResult;
use App\Modules\AI\DTOs\PromptContextDTO;
use App\Modules\AI\Services\PromptBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    public function __construct(
        private readonly PromptBuilderService $promptBuilder,
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gemini-1.5-flash'
    ) {}

    public function generateResponse(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        $startTime = microtime(true);
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($context);
        $key = $this->apiKey ?? config('services.gemini.api_key', env('GEMINI_API_KEY'));

        if (!$key) {
            return (new MockAIProvider())->generateResponse($context, $userMessage);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$key}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.3,
                ],
            ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $rawContent = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $parsed = json_decode($rawContent, true);

                $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
                $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
                $cost = ($inputTokens * 0.000000075) + ($outputTokens * 0.00000030); // Tarifas Gemini 1.5 Flash

                return new AIAnalysisResult(
                    intent: $parsed['intent'] ?? 'GREETING_INTENT',
                    confidence: (float) ($parsed['confidence'] ?? 0.90),
                    entities: $parsed['entities'] ?? [],
                    needsHuman: (bool) ($parsed['needs_human'] ?? false),
                    replyText: $parsed['reply_text'] ?? 'Gracias por comunicarte con nosotros.',
                    provider: 'GEMINI',
                    model: $this->model,
                    promptVersion: $context->promptVersion,
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    estimatedCost: $cost,
                    latencyMs: $latencyMs
                );
            }
        } catch (\Throwable $e) {
            Log::error("Error en GeminiProvider: " . $e->getMessage());
        }

        return (new MockAIProvider())->generateResponse($context, $userMessage);
    }

    public function analyzeIntent(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        return $this->generateResponse($context, $userMessage);
    }

    public function getProviderName(): string
    {
        return 'GEMINI';
    }
}
