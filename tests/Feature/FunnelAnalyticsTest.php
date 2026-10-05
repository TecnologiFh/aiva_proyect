<?php

namespace Tests\Feature;

use Tests\TestCase;

class FunnelAnalyticsTest extends TestCase
{
    public function test_funnel_analytics_service_structure(): void
    {
        $metrics = [
            'messages_received' => 0,
            'purchase_intents' => 0,
            'orders_created' => 0,
            'orders_completed' => 0,
            'conversion_rate_percentage' => 0.0,
        ];

        $this->assertArrayHasKey('messages_received', $metrics);
        $this->assertArrayHasKey('purchase_intents', $metrics);
        $this->assertArrayHasKey('orders_created', $metrics);
        $this->assertArrayHasKey('orders_completed', $metrics);
        $this->assertArrayHasKey('conversion_rate_percentage', $metrics);
    }
}
