<?php

namespace App\Modules\Messaging\Services;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\ConversationMode;
use App\Core\Enums\MessageStatus;
use App\Core\Enums\MessageType;
use App\Core\Enums\SenderType;
use App\Core\Events\ConversationStarted;
use App\Core\Events\HumanTakeoverRequested;
use App\Core\Events\MessageReceived;
use App\Core\Events\MessageSent;
use App\Modules\AI\Application\ProcessMessage\ProcessMessageAction;
use App\Modules\AI\Application\ProcessMessage\ProcessMessageInput;
use App\Modules\Messaging\DTOs\IncomingWhatsAppMessageDTO;
use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use App\Modules\Messaging\Repositories\ConversationRepository;
use App\Modules\Messaging\Repositories\MessageRepository;
use Illuminate\Support\Facades\Redis;

class ConversationOrchestrator
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ProcessMessageAction $processAIMessageAction,
        private readonly WhatsAppClientFactory $clientFactory,
        private readonly EventBusInterface $eventBus
    ) {}

    public function handleIncomingMessage(IncomingWhatsAppMessageDTO $incoming, int $tenantId): void
    {
        $lockKey = "conversation:lock:{$tenantId}:{$incoming->customerPhone}";

        // Bloqueo Conversacional en Redis por 5 segundos para mantener orden estricto de mensajes
        $acquired = false;
        try {
            $acquired = (bool) Redis::connection('cache')->set($lockKey, 'locked', 'EX', 5, 'NX');
        } catch (\Throwable $e) {
            $acquired = true; // Permisivo si Redis no responde
        }

        try {
            // 1. Encontrar o Crear Conversación
            $isNew = false;
            $conversation = $this->conversationRepository->findOrCreate(
                tenantId: $tenantId,
                customerPhone: $incoming->customerPhone,
                customerName: $incoming->customerName
            );

            if ($conversation->wasRecentlyCreated) {
                $isNew = true;
                $this->eventBus->dispatch(new ConversationStarted($conversation));
            }

            // 2. Registrar Mensaje Entrante del Cliente
            $customerMsg = $this->messageRepository->create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'sender_type' => SenderType::CUSTOMER->value,
                'message_type' => $incoming->messageType,
                'content' => $incoming->content,
                'media_url' => $incoming->mediaUrl,
                'mime_type' => $incoming->mimeType,
                'file_size' => $incoming->fileSize,
                'external_id' => $incoming->wamid,
                'status' => MessageStatus::RECEIVED->value,
                'metadata' => $incoming->rawPayload,
            ]);

            $this->conversationRepository->touchLastMessage($conversation->id);
            $this->eventBus->dispatch(new MessageReceived($customerMsg));

            // 3. Orquestar Modo de Atención (AI vs HUMAN)
            if ($conversation->mode === ConversationMode::AI) {
                // Obtener contexto de mensajes recientes
                $recentMsgs = $this->messageRepository->getRecentMessages($conversation->id, 5)
                    ->map(fn($m) => ['sender' => $m->sender_type->value, 'text' => $m->content])
                    ->toArray();

                // Procesar mensaje con el Motor de IA (Sprint 4)
                $aiInput = new ProcessMessageInput(
                    tenantId: $tenantId,
                    userMessage: $incoming->content,
                    customerPhone: $incoming->customerPhone,
                    conversationId: $conversation->id,
                    recentMessages: $recentMsgs
                );

                $aiResult = $this->processAIMessageAction->execute($aiInput)->analysisResult;

                // Evaluar si requiere derivación a operador humano
                if ($aiResult->needsHuman) {
                    $this->conversationRepository->updateMode($conversation->id, ConversationMode::HUMAN);
                    $this->eventBus->dispatch(new HumanTakeoverRequested($conversation, 'IA detectó necesidad de atención humana.'));
                    return;
                }

                // 4. Enviar Respuesta de la IA vía WhatsApp
                $whatsappClient = $this->clientFactory->make();
                $outgoingDTO = new OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $incoming->customerPhone,
                    content: $aiResult->replyText,
                    conversationId: $conversation->id
                );

                $sentSuccess = $whatsappClient->sendMessage($outgoingDTO);

                // 5. Registrar Mensaje Saliente de la IA en DB
                $aiMsg = $this->messageRepository->create([
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'sender_type' => SenderType::AI->value,
                    'message_type' => MessageType::TEXT->value,
                    'content' => $aiResult->replyText,
                    'status' => $sentSuccess ? MessageStatus::SENT->value : MessageStatus::FAILED->value,
                    'metadata' => [
                        'intent' => $aiResult->intent,
                        'confidence' => $aiResult->confidence,
                        'entities' => $aiResult->entities,
                    ],
                ]);

                $this->conversationRepository->touchLastMessage($conversation->id);
                $this->eventBus->dispatch(new MessageSent($aiMsg));
            }
        } finally {
            try {
                Redis::connection('cache')->del($lockKey);
            } catch (\Throwable $e) {
                // Silencioso
            }
        }
    }
}
