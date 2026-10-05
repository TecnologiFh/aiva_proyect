<?php

namespace App\Modules\Orders\Application\UpdateOrderStatus;

class UpdateOrderStatusInput
{
    public function __construct(
        public readonly string $orderUuid,
        public readonly string $newStatus,
        public readonly bool $notifyWhatsApp = true
    ) {}
}
