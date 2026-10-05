<?php

namespace App\Modules\Automation\Engine;

use App\Modules\Automation\Models\AutomationLog;
use App\Modules\Automation\Models\AutomationRule;

class RuleEvaluator
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
        private readonly ActionExecutor $actionExecutor
    ) {}

    public function evaluateRulesForTrigger(int $tenantId, string $triggerName, array $context): void
    {
        $rules = AutomationRule::where('tenant_id', $tenantId)
            ->where('trigger_name', $triggerName)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            $logicTree = $rule->logic_tree;
            $matches = $this->conditionEvaluator->evaluate($logicTree, $context);

            if (!$matches) {
                AutomationLog::create([
                    'tenant_id' => $tenantId,
                    'rule_id' => $rule->id,
                    'rule_version' => $rule->version,
                    'trigger_name' => $triggerName,
                    'status' => 'SKIPPED',
                    'execution_details' => ['reason' => 'Conditions not met'],
                ]);
                continue;
            }

            // Ejecutar Pipeline de Acciones
            $success = $this->actionExecutor->executePipeline($rule->actions, $context);

            $rule->increment('executions_count');
            $rule->update(['last_executed_at' => now()]);

            AutomationLog::create([
                'tenant_id' => $tenantId,
                'rule_id' => $rule->id,
                'rule_version' => $rule->version,
                'trigger_name' => $triggerName,
                'status' => $success ? 'SUCCESS' : 'FAILED',
                'execution_details' => ['context' => $context, 'actions' => $rule->actions],
            ]);

            // Detener procesamiento de reglas de menor prioridad si está configurado
            if ($rule->stop_processing) {
                break;
            }
        }
    }
}
