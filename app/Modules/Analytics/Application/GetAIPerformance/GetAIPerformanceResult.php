<?php

namespace App\Modules\Analytics\Application\GetAIPerformance;

use App\Modules\Analytics\DTOs\AIPerformanceMetricsDTO;
use App\Modules\Analytics\DTOs\AIRoiMetricsDTO;

class GetAIPerformanceResult
{
    public function __construct(
        public readonly AIPerformanceMetricsDTO $performanceDTO,
        public readonly AIRoiMetricsDTO $roiDTO
    ) {}

    public function toArray(): array
    {
        return [
            'performance' => $this->performanceDTO->toArray(),
            'roi' => $this->roiDTO->toArray(),
        ];
    }
}
