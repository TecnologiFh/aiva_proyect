<?php

namespace App\Modules\Messaging\DTOs;

class IncomingWhatsAppMessageDTO
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $customerPhone,
        public readonly ?string $customerName,
        public readonly string $content,
        public readonly string $messageType = 'TEXT',
        public readonly ?string $wamid = null,
        public readonly ?string $mediaUrl = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $fileSize = null,
        public readonly ?int $tenantId = null,
        public readonly array $rawPayload = []
    ) {}
}
