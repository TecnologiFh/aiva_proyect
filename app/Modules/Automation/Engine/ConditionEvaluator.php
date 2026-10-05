<?php

namespace App\Modules\Automation\Engine;

class ConditionEvaluator
{
    public function evaluate(array $logicTree, array $context): bool
    {
        $operator = strtoupper($logicTree['operator'] ?? 'AND');
        $rules = $logicTree['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        if ($operator === 'AND') {
            foreach ($rules as $rule) {
                if (!$this->evaluateItem($rule, $context)) {
                    return false;
                }
            }
            return true;
        }

        if ($operator === 'OR') {
            foreach ($rules as $rule) {
                if ($this->evaluateItem($rule, $context)) {
                    return true;
                }
            }
            return false;
        }

        if ($operator === 'NOT') {
            $firstRule = $rules[0] ?? null;
            return $firstRule ? !$this->evaluateItem($firstRule, $context) : true;
        }

        return true;
    }

    private function evaluateItem(array $ruleItem, array $context): bool
    {
        if (isset($ruleItem['operator']) && isset($ruleItem['rules'])) {
            return $this->evaluate($ruleItem, $context);
        }

        $field = $ruleItem['field'] ?? null;
        $op = $ruleItem['operator'] ?? '==';
        $targetValue = $ruleItem['value'] ?? null;

        if (!$field) {
            return true;
        }

        $actualValue = $this->getValueFromPath($context, $field);

        return match ($op) {
            '==', '=' => $actualValue == $targetValue,
            '!=', '<>' => $actualValue != $targetValue,
            '>' => $actualValue > $targetValue,
            '>=' => $actualValue >= $targetValue,
            '<' => $actualValue < $targetValue,
            '<=' => $actualValue <= $targetValue,
            'CONTAINS', 'contains' => is_string($actualValue) && str_contains(strtolower($actualValue), strtolower($targetValue)),
            default => false,
        };
    }

    private function getValueFromPath(array $context, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }
}
