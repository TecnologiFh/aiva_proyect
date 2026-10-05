<?php

namespace App\Modules\Messaging\Application\ReceiveWebhook;

class ReceiveWebhookInput
{
    public function __construct(
        public readonly array $payload,
        public readonly int $tenantId = 1
    ) {}
}
