<?php

namespace App\Modules\Orders\Application\ConfirmOrder;

class ConfirmOrderInput
{
    public function __construct(
        public readonly string $orderUuid,
        public readonly ?string $paymentMethod = 'CASH',
        public readonly bool $notifyWhatsApp = true
    ) {}
}
