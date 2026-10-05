<?php

namespace App\Core\Events;

use App\Modules\Orders\Models\Order;

class OrderCreated
{
    public function __construct(
        public readonly Order $order
    ) {}
}
