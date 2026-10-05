<?php

namespace App\Modules\AI\Application\AnalyzeIntent;

class AnalyzeIntentInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $userMessage,
        public readonly ?string $preferredDriver = null
    ) {}
}
