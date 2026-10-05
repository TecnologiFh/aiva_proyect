<?php

namespace App\Modules\Analytics\DTOs;

class ExecutiveDashboardDTO
{
    public function __construct(
        public readonly SalesMetricsDTO $sales,
        public readonly AIPerformanceMetricsDTO $aiPerformance,
        public readonly AIRoiMetricsDTO $aiRoi,
        public readonly array $funnel
    ) {}

    public function toArray(): array
    {
        return [
            'sales' => $this->sales->toArray(),
            'ai_performance' => $this->aiPerformance->toArray(),
            'ai_roi' => $this->aiRoi->toArray(),
            'funnel' => $this->funnel,
        ];
    }
}
