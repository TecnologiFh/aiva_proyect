<?php

namespace App\Modules\Messaging\Application\SendMessage;

class SendMessageInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $recipientPhone,
        public readonly string $content,
        public readonly string $senderType = 'AGENT',
        public readonly ?int $conversationId = null
    ) {}
}
