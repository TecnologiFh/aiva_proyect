<?php

namespace App\Modules\AI\Application\ProcessMessage;

use App\Modules\AI\DTOs\AIAnalysisResult;

class ProcessMessageResult
{
    public function __construct(
        public readonly AIAnalysisResult $analysisResult
    ) {}

    public function toArray(): array
    {
        return $this->analysisResult->toArray();
    }
}
