<?php

namespace App\Modules\Analytics\DTOs;

class AIRoiMetricsDTO
{
    public function __construct(
        public readonly float $aiCostUsd,
        public readonly float $aiSalesGeneratedPen,
        public readonly float $roiPercentage
    ) {}

    public function toArray(): array
    {
        return [
            'ai_cost_usd' => $this->aiCostUsd,
            'ai_sales_generated_pen' => $this->aiSalesGeneratedPen,
            'roi_percentage' => $this->roiPercentage,
            'formatted_roi' => number_format($this->roiPercentage, 1) . '%',
        ];
    }
}
