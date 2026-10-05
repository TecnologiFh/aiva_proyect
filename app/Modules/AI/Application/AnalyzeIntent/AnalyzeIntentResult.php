<?php

namespace App\Modules\AI\Application\AnalyzeIntent;

use App\Modules\AI\DTOs\AIAnalysisResult;

class AnalyzeIntentResult
{
    public function __construct(
        public readonly AIAnalysisResult $analysisResult
    ) {}

    public function toArray(): array
    {
        return [
            'intent' => $this->analysisResult->intent,
            'confidence' => $this->analysisResult->confidence,
            'entities' => $this->analysisResult->entities,
            'needs_human' => $this->analysisResult->needsHuman,
        ];
    }
}
