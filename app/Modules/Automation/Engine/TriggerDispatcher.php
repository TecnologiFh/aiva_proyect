<?php

namespace App\Modules\Automation\Engine;

use App\Modules\Automation\Triggers\AutomationTriggerInterface;

class TriggerDispatcher
{
    /** @var array<string, AutomationTriggerInterface> */
    private array $triggers = [];

    public function __construct(
        private readonly RuleEvaluator $ruleEvaluator,
        iterable $triggerImplementations = []
    ) {
        foreach ($triggerImplementations as $trigger) {
            if ($trigger instanceof AutomationTriggerInterface) {
                $this->triggers[] = $trigger;
            }
        }
    }

    public function registerTrigger(AutomationTriggerInterface $trigger): void
    {
        $this->triggers[] = $trigger;
    }

    public function dispatchEvent(object $event): void
    {
        $eventName = get_class($event);

        foreach ($this->triggers as $trigger) {
            if ($trigger->matches($eventName)) {
                $payload = $trigger->extractPayload($event);
                $tenantId = $payload['tenant_id'] ?? null;

                if ($tenantId) {
                    $this->ruleEvaluator->evaluateRulesForTrigger(
                        tenantId: $tenantId,
                        triggerName: $trigger->getTriggerName(),
                        context: $payload
                    );
                }
            }
        }
    }
}
