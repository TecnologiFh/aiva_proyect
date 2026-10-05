<?php

namespace App\Modules\AI\Application\AnalyzeIntent;

use App\Modules\AI\Services\AIFactory;
use App\Modules\AI\Services\PromptContextBuilder;

class AnalyzeIntentAction
{
    public function __construct(
        private readonly PromptContextBuilder $contextBuilder,
        private readonly AIFactory $aiFactory
    ) {}

    public function execute(AnalyzeIntentInput $input): AnalyzeIntentResult
    {
        $context = $this->contextBuilder->build(tenantId: $input->tenantId);
        $provider = $this->aiFactory->make($input->preferredDriver);

        $result = $provider->analyzeIntent($context, $input->userMessage);

        return new AnalyzeIntentResult($result);
    }
}
