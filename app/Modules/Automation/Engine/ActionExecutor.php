<?php

namespace App\Modules\Automation\Engine;

use App\Modules\Automation\Actions\AutomationActionInterface;

class ActionExecutor
{
    /** @var array<string, AutomationActionInterface> */
    private array $actions = [];

    public function __construct(
        private readonly VariableResolver $variableResolver,
        iterable $actionImplementations = []
    ) {
        foreach ($actionImplementations as $action) {
            if ($action instanceof AutomationActionInterface) {
                $this->actions[$action->getType()] = $action;
            }
        }
    }

    public function registerAction(AutomationActionInterface $action): void
    {
        $this->actions[$action->getType()] = $action;
    }

    public function executePipeline(array $actionList, array $context): bool
    {
        foreach ($actionList as $actionConfig) {
            $type = $actionConfig['action_type'] ?? null;
            $params = $actionConfig['params'] ?? [];
            $continueOnError = (bool) ($actionConfig['continue_on_error'] ?? true);

            if (!$type || !isset($this->actions[$type])) {
                if (!$continueOnError) return false;
                continue;
            }

            // Resolver variables dinámicas en los parámetros
            $resolvedParams = [];
            foreach ($params as $k => $v) {
                if (is_string($v)) {
                    $resolvedParams[$k] = $this->variableResolver->resolve($v, $context);
                } else {
                    $resolvedParams[$k] = $v;
                }
            }

            if (isset($resolvedParams['message'])) {
                $resolvedParams['resolved_message'] = $this->variableResolver->resolve($resolvedParams['message'], $context);
            }

            $success = $this->actions[$type]->execute($resolvedParams, $context);

            if (!$success && !$continueOnError) {
                return false;
            }
        }

        return true;
    }
}
