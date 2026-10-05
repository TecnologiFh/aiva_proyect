<?php

namespace App\Modules\Analytics\Application\GetSalesAnalytics;

use App\Modules\Analytics\DTOs\SalesMetricsDTO;

class GetSalesAnalyticsResult
{
    public function __construct(
        public readonly SalesMetricsDTO $salesMetrics
    ) {}

    public function toArray(): array
    {
        return $this->salesMetrics->toArray();
    }
}
