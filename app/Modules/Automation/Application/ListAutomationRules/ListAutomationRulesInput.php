<?php

namespace App\Modules\Automation\Application\ListAutomationRules;

class ListAutomationRulesInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
