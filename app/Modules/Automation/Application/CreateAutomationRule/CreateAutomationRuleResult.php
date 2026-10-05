<?php

namespace App\Modules\Automation\Application\CreateAutomationRule;

use App\Modules\Automation\Models\AutomationRule;

class CreateAutomationRuleResult
{
    public function __construct(
        public readonly AutomationRule $rule
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->rule->uuid,
            'name' => $this->rule->name,
            'trigger_type' => $this->rule->trigger_type->value,
            'trigger_name' => $this->rule->trigger_name,
            'priority' => $this->rule->priority,
            'stop_processing' => $this->rule->stop_processing,
            'is_active' => $this->rule->is_active,
            'version' => $this->rule->version,
            'created_at' => $this->rule->created_at?->toIso8601String(),
        ];
    }
}
