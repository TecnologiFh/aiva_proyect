<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\CustomerTimeline;

use Tests\TestCase;

class CustomerTimelineTest extends TestCase
{
    public function test_customer_timeline_model(): void
    {
        $timeline = new CustomerTimeline([
            'tenant_id' => 1,
            'customer_id' => 5,
            'event_type' => 'ORDER_CREATED',
            'description' => 'Pedido ORD-2026-0001 creado por la IA',
            'metadata' => ['order_number' => 'ORD-2026-0001'],
        ]);

        $this->assertEquals('ORDER_CREATED', $timeline->event_type);
        $this->assertEquals('ORD-2026-0001', $timeline->metadata['order_number']);
    }
}
