<?php

namespace App\Modules\Automation\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Automation\Application\CreateAutomationRule\CreateAutomationRuleAction;
use App\Modules\Automation\Application\CreateAutomationRule\CreateAutomationRuleInput;
use App\Modules\Automation\Application\ListAutomationRules\ListAutomationRulesAction;
use App\Modules\Automation\Application\ListAutomationRules\ListAutomationRulesInput;
use App\Modules\Automation\Models\AutomationRule;
use App\Modules\Automation\Repositories\AutomationRuleRepository;
use App\Modules\Automation\Requests\CreateAutomationRuleRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AutomationController extends Controller
{
    public function index(Request $request, ListAutomationRulesAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListAutomationRulesInput(
            tenantId: $tenantId,
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Reglas de automatización comercial recuperadas exitosamente'
        );
    }

    public function store(CreateAutomationRuleRequest $request, CreateAutomationRuleAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new CreateAutomationRuleInput(
            tenantId: $tenantId,
            name: $request->validated('name'),
            triggerName: $request->validated('trigger_name'),
            logicTree: $request->validated('logic_tree'),
            actions: $request->validated('actions'),
            triggerType: $request->validated('trigger_type', 'EVENT'),
            cronExpression: $request->validated('cron_expression'),
            priority: (int) $request->validated('priority', 50),
            stopProcessing: (bool) $request->validated('stop_processing', false)
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Regla de automatización comercial creada correctamente'
        );
    }

    public function toggle(string $uuid, AutomationRuleRepository $repository): JsonResponse
    {
        $rule = $repository->findByUuid($uuid);
        if (!$rule) {
            return ApiResponse::notFound('Regla de automatización no encontrada');
        }

        $updated = $repository->toggleActive($rule->id);

        return ApiResponse::success(
            data: [
                'uuid' => $updated->uuid,
                'is_active' => $updated->is_active,
            ],
            message: 'Estado de la regla de automatización actualizado correctamente'
        );
    }
}
