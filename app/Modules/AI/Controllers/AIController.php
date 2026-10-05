<?php

namespace App\Modules\AI\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\AI\Application\AnalyzeIntent\AnalyzeIntentAction;
use App\Modules\AI\Application\AnalyzeIntent\AnalyzeIntentInput;
use App\Modules\AI\Application\ProcessMessage\ProcessMessageAction;
use App\Modules\AI\Application\ProcessMessage\ProcessMessageInput;
use App\Modules\AI\Repositories\AIRequestRepository;
use App\Modules\AI\Requests\AnalyzeIntentRequest;
use App\Modules\AI\Requests\ProcessAIMessageRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AIController extends Controller
{
    public function __construct(
        private readonly AIRequestRepository $requestRepository
    ) {}

    public function process(ProcessAIMessageRequest $request, ProcessMessageAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ProcessMessageInput(
            tenantId: $tenantId,
            userMessage: $request->validated('message'),
            customerPhone: $request->validated('customer_phone'),
            conversationId: $request->validated('conversation_id'),
            recentMessages: $request->validated('recent_messages', []),
            preferredDriver: $request->validated('driver')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Mensaje procesado exitosamente por el Motor de IA'
        );
    }

    public function analyzeIntent(AnalyzeIntentRequest $request, AnalyzeIntentAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new AnalyzeIntentInput(
            tenantId: $tenantId,
            userMessage: $request->validated('message'),
            preferredDriver: $request->validated('driver')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Análisis de intención comercial completado'
        );
    }

    public function usageStats(): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $stats = $this->requestRepository->getUsageStatsByTenant($tenantId);

        return ApiResponse::success(
            data: $stats,
            message: 'Estadísticas de consumo de IA e historial de tokens recuperadas'
        );
    }
}
