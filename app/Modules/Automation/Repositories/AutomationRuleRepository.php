<?php

namespace App\Modules\Automation\Repositories;

use App\Modules\Automation\Models\AutomationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutomationRuleRepository
{
    public function create(array $data): AutomationRule
    {
        return AutomationRule::create($data);
    }

    public function findByUuid(string $uuid): ?AutomationRule
    {
        return AutomationRule::with('logs')->whereUuid($uuid)->first();
    }

    public function toggleActive(int $id): AutomationRule
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);
        return $rule;
    }

    public function paginate(int $tenantId, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        return AutomationRule::where('tenant_id', $tenantId)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $limit, page: $page);
    }
}
