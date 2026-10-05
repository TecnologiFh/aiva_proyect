<?php

namespace App\Modules\Automation\Application\ListAutomationRules;

use App\Modules\Automation\Repositories\AutomationRuleRepository;

class ListAutomationRulesAction
{
    public function __construct(
        private readonly AutomationRuleRepository $ruleRepository
    ) {}

    public function execute(ListAutomationRulesInput $input): ListAutomationRulesResult
    {
        $paginator = $this->ruleRepository->paginate(
            tenantId: $input->tenantId,
            page: $input->page,
            limit: $input->limit
        );

        return new ListAutomationRulesResult($paginator);
    }
}
