<?php

namespace App\Modules\Payments\Application\ProcessPaymentWebhook;

class ProcessPaymentWebhookInput
{
    public function __construct(
        public readonly string $gateway,
        public readonly array $payload,
        public readonly array $headers = [],
        public readonly ?string $signature = null
    ) {}
}
