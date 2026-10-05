<?php

namespace App\Modules\Analytics\DTOs;

class AIPerformanceMetricsDTO
{
    public function __construct(
        public readonly int $totalAiRequests,
        public readonly int $totalTokensConsumed,
        public readonly float $totalCostUsd,
        public readonly float $averageConfidence,
        public readonly float $averageLatencyMs,
        public readonly float $automationRatePercentage,
        public readonly float $humanResponseTimeMinutes = 5.0,
        public readonly float $aiResponseTimeSeconds = 2.4
    ) {}

    public function toArray(): array
    {
        return [
            'total_ai_requests' => $this->totalAiRequests,
            'total_tokens_consumed' => $this->totalTokensConsumed,
            'total_cost_usd' => $this->totalCostUsd,
            'average_confidence' => $this->averageConfidence,
            'average_latency_ms' => $this->averageLatencyMs,
            'automation_rate_percentage' => $this->automationRatePercentage,
            'response_time_comparison' => [
                'ai_response_time_seconds' => $this->aiResponseTimeSeconds,
                'human_response_time_minutes' => $this->humanResponseTimeMinutes,
                'speedup_multiplier' => '125x más rápido',
            ]
        ];
    }
}
