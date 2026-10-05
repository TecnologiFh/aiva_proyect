<?php

namespace Tests\Feature;

use App\Modules\Tenant\Database\Seeders\MultiTenantTestSeeder;
use App\Modules\Orders\Models\OrderEvent;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderFourDimensionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MultiTenantTestSeeder())->run();
    }

    /** @test */
    public function test_order_creation_and_four_dimension_state_transitions_log_order_events()
    {
        $tenantId = 4; // Pastelería Donuts Fs

        // 1. Crear una Zona de Delivery Estructurada
        DeliveryZone::create([
            'tenant_id' => $tenantId,
            'zone_name' => 'Miraflores',
            'delivery_fee' => 10.00,
            'estimated_time_mins' => 45,
            'min_order_amount' => 20.00,
            'is_active' => true,
        ]);

        // 2. Crear un pedido unificado con canal WHATSAPP y zona de delivery
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-TEST-8888',
            'customer_name' => 'Cliente Pruebas M8',
            'customer_phone' => '51999888777',
            'delivery_address' => 'Av. Larco 123, Miraflores',
            'delivery_zone' => 'Miraflores',
            'order_type' => 'DELIVERY',
            'channel' => 'WHATSAPP',
            'status' => 'NEW',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'subtotal' => 45.00,
            'delivery_fee' => 10.00,
            'total_amount' => 55.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $orderId);

        // 3. Transicionar Order Status a PREPARING con actor AI
        $stateEngine = new OrderStateMachineEngine();
        $stateEngine->transitionOrderStatus($tenantId, $orderId, 'PREPARING', 'AI', null, ['ai_provider' => 'groq']);

        // 4. Transicionar Fulfillment Status a OUT_FOR_DELIVERY con actor DELIVERY
        $stateEngine->transitionFulfillmentStatus($tenantId, $orderId, 'OUT_FOR_DELIVERY', 'DELIVERY', 101);

        // ASERCIÓN 1: El pedido debe reflejar los nuevos estados
        $order = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('PREPARING', $order->status);
        $this->assertEquals('OUT_FOR_DELIVERY', $order->fulfillment_status);
        $this->assertEquals('Miraflores', $order->delivery_zone);

        // ASERCIÓN 2: La tabla order_events debe contener los 2 eventos registrados
        $events = OrderEvent::where('tenant_id', $tenantId)->where('order_id', $orderId)->get();
        $this->assertCount(2, $events);

        $orderEvent = $events->where('event_type', 'ORDER_STATUS_CHANGED')->first();
        $this->assertEquals('NEW', $orderEvent->previous_state);
        $this->assertEquals('PREPARING', $orderEvent->new_state);
        $this->assertEquals('AI', $orderEvent->actor_type);

        $fulfillmentEvent = $events->where('event_type', 'FULFILLMENT_STATUS_CHANGED')->first();
        $this->assertEquals('PENDING', $fulfillmentEvent->previous_state);
        $this->assertEquals('OUT_FOR_DELIVERY', $fulfillmentEvent->new_state);
        $this->assertEquals('DELIVERY', $fulfillmentEvent->actor_type);
    }
}
