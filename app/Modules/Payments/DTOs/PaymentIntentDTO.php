<?php

namespace App\Modules\Payments\DTOs;

class PaymentIntentDTO
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $paymentMethod,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $gatewayTransactionId = null,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $merchantReference = null,
        public readonly ?string $qrCodeUrl = null,
        public readonly ?string $paymentLink = null,
        public readonly ?string $expiresAt = null,
        public readonly array $metadata = []
    ) {}
}
