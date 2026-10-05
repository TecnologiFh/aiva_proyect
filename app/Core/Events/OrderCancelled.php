<?php

namespace App\Core\Events;

use App\Modules\Orders\Models\Order;

class OrderCancelled
{
    public function __construct(
        public readonly Order $order,
        public readonly ?string $reason = null
    ) {}
}
