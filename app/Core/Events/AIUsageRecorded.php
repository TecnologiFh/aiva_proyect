<?php

namespace App\Core\Events;

use App\Modules\AI\Models\AIRequest;

class AIUsageRecorded
{
    public function __construct(
        public readonly AIRequest $aiRequest
    ) {}
}
