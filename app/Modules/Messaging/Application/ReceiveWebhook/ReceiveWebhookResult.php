<?php

namespace App\Modules\Messaging\Application\ReceiveWebhook;

class ReceiveWebhookResult
{
    public function __construct(
        public readonly bool $processed,
        public readonly string $status = 'QUEUED',
        public readonly array $additionalData = []
    ) {}

    public function toArray(): array
    {
        return array_merge([
            'processed' => $this->processed,
            'status' => $this->status,
        ], $this->additionalData);
    }
}

