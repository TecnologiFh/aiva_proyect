<?php

namespace App\Modules\Orders\Application\ConfirmOrder;

use App\Modules\Orders\Models\Order;

class ConfirmOrderResult
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
            'payment_method' => $this->order->payment_method->value,
            'total_amount' => (float) $this->order->total_amount,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}
