<?php

namespace App\Modules\Analytics\Application\GetExecutiveDashboard;

use App\Modules\Analytics\DTOs\ExecutiveDashboardDTO;

class GetExecutiveDashboardResult
{
    public function __construct(
        public readonly ExecutiveDashboardDTO $dashboardDTO
    ) {}

    public function toArray(): array
    {
        return $this->dashboardDTO->toArray();
    }
}
