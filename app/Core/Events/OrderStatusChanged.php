<?php

namespace App\Core\Events;

use App\Modules\Orders\Models\Order;

class OrderStatusChanged
{
    public function __construct(
        public readonly Order $order,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}
}
