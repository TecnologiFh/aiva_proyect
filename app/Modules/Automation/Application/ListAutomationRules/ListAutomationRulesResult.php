<?php

namespace App\Modules\Automation\Application\ListAutomationRules;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAutomationRulesResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($r) => [
            'uuid' => $r->uuid,
            'name' => $r->name,
            'trigger_type' => $r->trigger_type->value,
            'trigger_name' => $r->trigger_name,
            'priority' => (int) $r->priority,
            'stop_processing' => (bool) $r->stop_processing,
            'is_active' => (bool) $r->is_active,
            'version' => (int) $r->version,
            'executions_count' => (int) $r->executions_count,
            'last_executed_at' => $r->last_executed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ]);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $this->paginator->currentPage(),
                'per_page' => $this->paginator->perPage(),
                'total' => $this->paginator->total(),
                'last_page' => $this->paginator->lastPage(),
            ],
        ];
    }
}
