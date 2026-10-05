<?php

namespace App\Modules\AI\DTOs;

class AIAnalysisResult
{
    public function __construct(
        public readonly string $intent,
        public readonly float $confidence,
        public readonly array $entities,
        public readonly bool $needsHuman,
        public readonly string $replyText,
        public readonly string $provider = 'MOCK',
        public readonly string $model = 'mock-v1',
        public readonly string $promptVersion = 'v1.0',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly float $estimatedCost = 0.000000,
        public readonly int $latencyMs = 0
    ) {}

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'confidence' => $this->confidence,
            'entities' => $this->entities,
            'needs_human' => $this->needsHuman,
            'reply_text' => $this->replyText,
            'meta' => [
                'provider' => $this->provider,
                'model' => $this->model,
                'prompt_version' => $this->promptVersion,
                'input_tokens' => $this->inputTokens,
                'output_tokens' => $this->outputTokens,
                'estimated_cost' => $this->estimatedCost,
                'latency_ms' => $this->latencyMs,
            ],
        ];
    }
}
