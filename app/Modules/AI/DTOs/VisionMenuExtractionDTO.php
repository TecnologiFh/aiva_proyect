<?php

namespace App\Modules\AI\DTOs;

final class VisionMenuExtractionDTO
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_LOW_CONFIDENCE = 'LOW_CONFIDENCE';
    public const STATUS_UNREADABLE = 'UNREADABLE';
    public const STATUS_INVALID_RESPONSE = 'INVALID_RESPONSE';
    public const STATUS_PROVIDER_ERROR = 'PROVIDER_ERROR';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    /**
     * @param VisionMenuCategoryDTO[] $categories
     */
    public function __construct(
        public readonly string $status,
        public readonly float $globalConfidence = 0.0,
        public readonly array $categories = [],
        public readonly string $provider = 'UNKNOWN',
        public readonly string $model = 'UNKNOWN',
        public readonly int $latencyMs = 0,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $rawResponse = null,
        public readonly ?string $errorMessage = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isTechnicalFailure(): bool
    {
        return $this->status === self::STATUS_TECHNICAL_FAILURE 
            || $this->status === self::STATUS_PROVIDER_ERROR 
            || $this->status === self::STATUS_INVALID_RESPONSE;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'global_confidence' => $this->globalConfidence,
            'categories' => array_map(fn(VisionMenuCategoryDTO $c) => $c->toArray(), $this->categories),
            'provider' => $this->provider,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'raw_response' => $this->rawResponse,
            'error_message' => $this->errorMessage,
        ];
    }
}
