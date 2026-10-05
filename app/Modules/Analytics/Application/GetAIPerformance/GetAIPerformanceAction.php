<?php

namespace App\Modules\Analytics\Application\GetAIPerformance;

use App\Modules\Analytics\Services\AIPerformanceAnalyticsService;
use App\Modules\Analytics\Services\AIRoiAnalyticsService;

class GetAIPerformanceAction
{
    public function __construct(
        private readonly AIPerformanceAnalyticsService $performanceService,
        private readonly AIRoiAnalyticsService $roiService
    ) {}

    public function execute(GetAIPerformanceInput $input): GetAIPerformanceResult
    {
        $performanceDTO = $this->performanceService->getAIPerformanceMetrics($input->tenantId);
        $roiDTO = $this->roiService->getAIRoiMetrics($input->tenantId);

        return new GetAIPerformanceResult($performanceDTO, $roiDTO);
    }
}
