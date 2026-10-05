<?php

namespace Tests\Feature;

use App\Core\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderNumberGenerator;
use Tests\TestCase;

class CreateOrderFromAITest extends TestCase
{
    public function test_order_model_casts_and_attributes(): void
    {
        $order = new Order([
            'tenant_id' => 1,
            'order_number' => 'ORD-2026-0001',
            'customer_name' => 'Luis Fiestas',
            'customer_phone' => '+51999888777',
            'subtotal' => 70.00,
            'delivery_fee' => 5.00,
            'total_amount' => 75.00,
            'status' => OrderStatus::PENDING_CONFIRMATION->value,
        ]);

        $this->assertEquals(1, $order->tenant_id);
        $this->assertEquals('ORD-2026-0001', $order->order_number);
        $this->assertEquals(75.00, $order->total_amount);
        $this->assertEquals(OrderStatus::PENDING_CONFIRMATION, $order->status);
    }
}
