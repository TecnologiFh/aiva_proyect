<?php

namespace App\Modules\Analytics\Application\GetExecutiveDashboard;

use App\Modules\Analytics\DTOs\ExecutiveDashboardDTO;
use App\Modules\Analytics\Services\AIPerformanceAnalyticsService;
use App\Modules\Analytics\Services\AIRoiAnalyticsService;
use App\Modules\Analytics\Services\FunnelAnalyticsService;
use App\Modules\Analytics\Services\SalesAnalyticsService;

class GetExecutiveDashboardAction
{
    public function __construct(
        private readonly SalesAnalyticsService $salesService,
        private readonly AIPerformanceAnalyticsService $aiPerformanceService,
        private readonly AIRoiAnalyticsService $aiRoiService,
        private readonly FunnelAnalyticsService $funnelService
    ) {}

    public function execute(GetExecutiveDashboardInput $input): GetExecutiveDashboardResult
    {
        $salesMetrics = $this->salesService->getSalesMetrics($input->tenantId);
        $aiPerformanceMetrics = $this->aiPerformanceService->getAIPerformanceMetrics($input->tenantId);
        $aiRoiMetrics = $this->aiRoiService->getAIRoiMetrics($input->tenantId);
        $funnelMetrics = $this->funnelService->getFunnelMetrics($input->tenantId);

        $dto = new ExecutiveDashboardDTO(
            sales: $salesMetrics,
            aiPerformance: $aiPerformanceMetrics,
            aiRoi: $aiRoiMetrics,
            funnel: $funnelMetrics
        );

        return new GetExecutiveDashboardResult($dto);
    }
}
