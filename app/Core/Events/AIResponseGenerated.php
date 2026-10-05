<?php

namespace App\Core\Events;

use App\Modules\AI\DTOs\AIAnalysisResult;

class AIResponseGenerated
{
    public function __construct(
        public readonly int $tenantId,
        public readonly AIAnalysisResult $result
    ) {}
}
