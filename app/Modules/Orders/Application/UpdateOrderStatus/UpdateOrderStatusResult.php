<?php

namespace App\Modules\Orders\Application\UpdateOrderStatus;

use App\Modules\Orders\Models\Order;

class UpdateOrderStatusResult
{
    public function __construct(
        public readonly Order $order
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}
