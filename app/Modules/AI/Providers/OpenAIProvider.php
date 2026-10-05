<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\AIProviderInterface;
use App\Modules\AI\DTOs\AIAnalysisResult;
use App\Modules\AI\DTOs\PromptContextDTO;
use App\Modules\AI\Services\PromptBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private readonly PromptBuilderService $promptBuilder,
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gpt-4o-mini'
    ) {}

    public function generateResponse(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        $startTime = microtime(true);
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($context);
        $key = $this->apiKey ?? config('services.openai.api_key', env('OPENAI_API_KEY'));

        if (!$key) {
            // Fallback elegante a Mock en desarrollo si no hay API key configurada
            return (new MockAIProvider())->generateResponse($context, $userMessage);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $rawContent = $data['choices'][0]['message']['content'] ?? '{}';
                $parsed = json_decode($rawContent, true);

                $inputTokens = $data['usage']['prompt_tokens'] ?? 0;
                $outputTokens = $data['usage']['completion_tokens'] ?? 0;
                $cost = ($inputTokens * 0.00000015) + ($outputTokens * 0.00000060); // Tarifas gpt-4o-mini

                return new AIAnalysisResult(
                    intent: $parsed['intent'] ?? 'GREETING_INTENT',
                    confidence: (float) ($parsed['confidence'] ?? 0.90),
                    entities: $parsed['entities'] ?? [],
                    needsHuman: (bool) ($parsed['needs_human'] ?? false),
                    replyText: $parsed['reply_text'] ?? 'Gracias por comunicarte con nosotros.',
                    provider: 'OPENAI',
                    model: $this->model,
                    promptVersion: $context->promptVersion,
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    estimatedCost: $cost,
                    latencyMs: $latencyMs
                );
            }
        } catch (\Throwable $e) {
            Log::error("Error en OpenAIProvider: " . $e->getMessage());
        }

        // Fallback a Mock si falla la llamada HTTP
        return (new MockAIProvider())->generateResponse($context, $userMessage);
    }

    public function analyzeIntent(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        return $this->generateResponse($context, $userMessage);
    }

    public function getProviderName(): string
    {
        return 'OPENAI';
    }
}
