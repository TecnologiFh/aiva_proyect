<?php

namespace App\Modules\Analytics\Application\GetSalesAnalytics;

class GetSalesAnalyticsInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null
    ) {}
}
