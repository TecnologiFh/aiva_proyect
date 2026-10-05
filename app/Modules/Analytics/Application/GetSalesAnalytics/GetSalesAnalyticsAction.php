<?php

namespace App\Modules\Analytics\Application\GetSalesAnalytics;

use App\Modules\Analytics\Services\SalesAnalyticsService;

class GetSalesAnalyticsAction
{
    public function __construct(
        private readonly SalesAnalyticsService $salesService
    ) {}

    public function execute(GetSalesAnalyticsInput $input): GetSalesAnalyticsResult
    {
        $salesMetrics = $this->salesService->getSalesMetrics(
            tenantId: $input->tenantId,
            startDate: $input->startDate,
            endDate: $input->endDate
        );

        return new GetSalesAnalyticsResult($salesMetrics);
    }
}
