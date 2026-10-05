<?php

namespace App\Modules\Automation\Triggers;

use App\Core\Events\OrderCreated;

class OrderCreatedTrigger implements AutomationTriggerInterface
{
    public function matches(string $eventName): bool
    {
        return $eventName === OrderCreated::class;
    }

    public function extractPayload(object $event): array
    {
        /** @var OrderCreated $event */
        $order = $event->order;

        return [
            'tenant_id' => $order->tenant_id,
            'order' => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'number' => $order->order_number,
                'total' => (float) $order->total_amount,
                'subtotal' => (float) $order->subtotal,
                'type' => $order->order_type->value,
                'status' => $order->status->value,
            ],
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
            ],
        ];
    }

    public function getTriggerName(): string
    {
        return 'ORDER_CREATED';
    }
}
