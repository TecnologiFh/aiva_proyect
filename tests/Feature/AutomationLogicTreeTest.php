<?php

namespace Tests\Feature;

use App\Modules\Automation\Engine\ConditionEvaluator;
use Tests\TestCase;

class AutomationLogicTreeTest extends TestCase
{
    public function test_condition_evaluator_evaluates_and_or_logic_trees(): void
    {
        $evaluator = new ConditionEvaluator();

        $tree = [
            'operator' => 'AND',
            'rules' => [
                ['field' => 'order.total', 'operator' => '>', 'value' => 100],
                ['field' => 'customer.score', 'operator' => '>=', 'value' => 50],
            ]
        ];

        $matchingContext = [
            'order' => ['total' => 150.00],
            'customer' => ['score' => 80],
        ];

        $failingContext = [
            'order' => ['total' => 50.00],
            'customer' => ['score' => 80],
        ];

        $this->assertTrue($evaluator->evaluate($tree, $matchingContext));
        $this->assertFalse($evaluator->evaluate($tree, $failingContext));
    }
}
