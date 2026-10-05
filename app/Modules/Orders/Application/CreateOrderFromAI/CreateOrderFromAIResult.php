<?php

namespace App\Modules\Orders\Application\CreateOrderFromAI;

use App\Modules\Orders\Models\Order;

class CreateOrderFromAIResult
{
    public function __construct(
        public readonly Order $order,
        public readonly string $formattedSummary
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer_name,
            'customer_phone' => $this->order->customer_phone,
            'order_type' => $this->order->order_type->value,
            'status' => $this->order->status->value,
            'subtotal' => (float) $this->order->subtotal,
            'delivery_fee' => (float) $this->order->delivery_fee,
            'total_amount' => (float) $this->order->total_amount,
            'formatted_summary' => $this->formattedSummary,
            'items' => $this->order->items->map(fn($item) => [
                'product_name' => $item->product_name,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'options_snapshot' => $item->options_snapshot,
            ])->toArray(),
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}
