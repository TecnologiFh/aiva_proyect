<?php

namespace App\Modules\Automation\Application\CreateAutomationRule;

class CreateAutomationRuleInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $triggerName,
        public readonly array $logicTree,
        public readonly array $actions,
        public readonly string $triggerType = 'EVENT',
        public readonly ?string $cronExpression = null,
        public readonly int $priority = 50,
        public readonly bool $stopProcessing = false
    ) {}
}
