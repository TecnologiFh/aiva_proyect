<?php

namespace App\Modules\AI\Repositories;

use App\Modules\AI\Models\AIRequest;

class AIRequestRepository
{
    public function create(array $data): AIRequest
    {
        return AIRequest::create($data);
    }

    public function getUsageStatsByTenant(int $tenantId): array
    {
        $query = AIRequest::where('tenant_id', $tenantId);

        return [
            'total_requests' => (int) $query->count(),
            'total_input_tokens' => (int) $query->sum('input_tokens'),
            'total_output_tokens' => (int) $query->sum('output_tokens'),
            'total_cost' => (float) $query->sum('estimated_cost'),
            'avg_latency_ms' => (float) $query->avg('latency_ms'),
        ];
    }
}
