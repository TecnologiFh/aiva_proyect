<?php

namespace App\Modules\Payments\Application\CreatePaymentIntent;

class CreatePaymentIntentInput
{
    public function __construct(
        public readonly string $orderUuid,
        public readonly ?string $gateway = null,
        public readonly ?string $paymentMethod = 'CASH',
        public readonly bool $sendWhatsAppInstructions = true
    ) {}
}
