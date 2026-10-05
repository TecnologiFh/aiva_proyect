<?php

namespace App\Modules\Messaging\DTOs;

class OutgoingWhatsAppMessageDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $recipientPhone,
        public readonly string $content,
        public readonly string $messageType = 'TEXT',
        public readonly ?string $mediaUrl = null,
        public readonly ?int $conversationId = null,
        public readonly ?string $correlationToken = null
    ) {}
}
