<?php

namespace Tests\Feature;

use App\Core\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;

use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    public function test_order_status_transitions(): void
    {
        $order = new Order([
            'tenant_id' => 1,
            'order_number' => 'ORD-2026-0002',
            'status' => OrderStatus::PENDING_CONFIRMATION->value,
        ]);

        $this->assertEquals(OrderStatus::PENDING_CONFIRMATION, $order->status);

        $order->status = OrderStatus::CONFIRMED;
        $this->assertEquals(OrderStatus::CONFIRMED, $order->status);

        $order->status = OrderStatus::DELIVERED;
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);
    }
}
