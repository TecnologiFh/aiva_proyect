<?php

namespace App\Modules\Messaging\Application\SendMessage;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\MessageStatus;
use App\Core\Enums\MessageType;
use App\Core\Enums\SenderType;
use App\Core\Events\MessageSent;
use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use App\Modules\Messaging\Repositories\ConversationRepository;
use App\Modules\Messaging\Repositories\MessageRepository;
use App\Modules\Messaging\Services\WhatsAppClientFactory;

class SendMessageAction
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly WhatsAppClientFactory $clientFactory,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(SendMessageInput $input): SendMessageResult
    {
        $conversation = $this->conversationRepository->findOrCreate(
            tenantId: $input->tenantId,
            customerPhone: $input->recipientPhone
        );

        $whatsappClient = $this->clientFactory->make();
        $outgoingDTO = new OutgoingWhatsAppMessageDTO(
            tenantId: $input->tenantId,
            recipientPhone: $input->recipientPhone,
            content: $input->content,
            conversationId: $conversation->id
        );

        $success = $whatsappClient->sendMessage($outgoingDTO);

        $message = $this->messageRepository->create([
            'tenant_id' => $input->tenantId,
            'conversation_id' => $conversation->id,
            'sender_type' => $input->senderType,
            'message_type' => MessageType::TEXT->value,
            'content' => $input->content,
            'status' => $success ? MessageStatus::SENT->value : MessageStatus::FAILED->value,
        ]);

        $this->conversationRepository->touchLastMessage($conversation->id);
        $this->eventBus->dispatch(new MessageSent($message));

        return new SendMessageResult($message, $success);
    }
}
