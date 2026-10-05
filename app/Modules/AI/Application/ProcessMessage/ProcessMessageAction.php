<?php

namespace App\Modules\AI\Application\ProcessMessage;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Events\AIResponseGenerated;
use App\Core\Events\AIUsageRecorded;
use App\Modules\AI\Repositories\AIRequestRepository;
use App\Modules\AI\Services\AIFactory;
use App\Modules\AI\Services\PromptContextBuilder;

class ProcessMessageAction
{
    public function __construct(
        private readonly PromptContextBuilder $contextBuilder,
        private readonly AIFactory $aiFactory,
        private readonly AIRequestRepository $requestRepository,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(ProcessMessageInput $input): ProcessMessageResult
    {
        // 1. Construir el contexto desacoplado de la IA
        $context = $this->contextBuilder->build(
            tenantId: $input->tenantId,
            customerPhone: $input->customerPhone,
            recentMessages: $input->recentMessages
        );

        // 2. Obtener el proveedor activo del AI Gateway (OpenAI, Gemini, Mock)
        $provider = $this->aiFactory->make($input->preferredDriver);

        // 3. Generar respuesta estructurada
        $result = $provider->generateResponse($context, $input->userMessage);

        // 4. Auditoría de tokens y costo consumido en ai_requests
        $aiRequest = $this->requestRepository->create([
            'tenant_id' => $input->tenantId,
            'conversation_id' => $input->conversationId,
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'intent_detected' => $result->intent,
            'confidence' => $result->confidence,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'estimated_cost' => $result->estimatedCost,
            'latency_ms' => $result->latencyMs,
            'status' => 'SUCCESS',
        ]);

        // 5. Disparar Eventos de Dominio
        $this->eventBus->dispatch(new AIResponseGenerated($input->tenantId, $result));
        $this->eventBus->dispatch(new AIUsageRecorded($aiRequest));

        return new ProcessMessageResult($result);
    }
}
