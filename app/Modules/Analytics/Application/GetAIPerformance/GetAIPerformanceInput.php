<?php

namespace App\Modules\Analytics\Application\GetAIPerformance;

class GetAIPerformanceInput
{
    public function __construct(
        public readonly int $tenantId
    ) {}
}
