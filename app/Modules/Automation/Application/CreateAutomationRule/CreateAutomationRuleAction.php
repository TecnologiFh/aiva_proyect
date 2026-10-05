<?php

namespace App\Modules\Automation\Application\CreateAutomationRule;

use App\Modules\Automation\Repositories\AutomationRuleRepository;

class CreateAutomationRuleAction
{
    public function __construct(
        private readonly AutomationRuleRepository $ruleRepository
    ) {}

    public function execute(CreateAutomationRuleInput $input): CreateAutomationRuleResult
    {
        $rule = $this->ruleRepository->create([
            'tenant_id' => $input->tenantId,
            'name' => $input->name,
            'trigger_type' => $input->triggerType,
            'trigger_name' => $input->triggerName,
            'cron_expression' => $input->cronExpression,
            'logic_tree' => $input->logicTree,
            'actions' => $input->actions,
            'priority' => $input->priority,
            'stop_processing' => $input->stopProcessing,
            'is_active' => true,
            'version' => 1,
        ]);

        return new CreateAutomationRuleResult($rule);
    }
}
