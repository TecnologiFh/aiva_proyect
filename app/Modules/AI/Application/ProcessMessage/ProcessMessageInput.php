<?php

namespace App\Modules\AI\Application\ProcessMessage;

class ProcessMessageInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $userMessage,
        public readonly ?string $customerPhone = null,
        public readonly ?int $conversationId = null,
        public readonly array $recentMessages = [],
        public readonly ?string $preferredDriver = null
    ) {}
}
