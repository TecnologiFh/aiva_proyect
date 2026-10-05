<?php

namespace App\Modules\Payments\DTOs;

class WebhookProcessingDTO
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $eventId,
        public readonly string $gateway,
        public readonly ?string $orderUuid = null,
        public readonly ?string $paymentUuid = null,
        public readonly ?string $gatewayTransactionId = null,
        public readonly ?string $status = null,
        public readonly float $amount = 0.00
    ) {}
}
