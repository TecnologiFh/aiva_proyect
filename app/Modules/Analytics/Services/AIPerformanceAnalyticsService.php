<?php

namespace App\Modules\Analytics\Services;

use App\Modules\AI\Models\AIRequest;
use App\Modules\Analytics\DTOs\AIPerformanceMetricsDTO;
use App\Modules\Messaging\Models\Conversation;

class AIPerformanceAnalyticsService
{
    public function getAIPerformanceMetrics(int $tenantId): AIPerformanceMetricsDTO
    {
        $query = AIRequest::where('tenant_id', $tenantId);

        $totalAiRequests = (int) $query->count();
        $totalTokensConsumed = (int) $query->sum('total_tokens');
        $totalCostUsd = (float) $query->sum('estimated_cost');
        $averageConfidence = $totalAiRequests > 0 ? round((float) $query->avg('confidence'), 2) : 0.00;
        $averageLatencyMs = $totalAiRequests > 0 ? round((float) $query->avg('response_time_ms'), 2) : 0.00;

        // Tasa de automatización de conversaciones
        $totalConversations = Conversation::where('tenant_id', $tenantId)->count();
        $humanTakeoverConversations = Conversation::where('tenant_id', $tenantId)->where('mode', 'HUMAN')->count();

        $automationRate = $totalConversations > 0
            ? round((($totalConversations - $humanTakeoverConversations) / $totalConversations) * 100, 1)
            : 100.0;

        return new AIPerformanceMetricsDTO(
            totalAiRequests: $totalAiRequests,
            totalTokensConsumed: $totalTokensConsumed,
            totalCostUsd: round($totalCostUsd, 4),
            averageConfidence: $averageConfidence,
            averageLatencyMs: $averageLatencyMs,
            automationRatePercentage: $automationRate,
            humanResponseTimeMinutes: 5.0,
            aiResponseTimeSeconds: 2.4
        );
    }
}
