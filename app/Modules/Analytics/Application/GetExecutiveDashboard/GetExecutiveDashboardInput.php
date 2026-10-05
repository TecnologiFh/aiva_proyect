<?php

namespace App\Modules\Analytics\Application\GetExecutiveDashboard;

class GetExecutiveDashboardInput
{
    public function __construct(
        public readonly int $tenantId
    ) {}
}
