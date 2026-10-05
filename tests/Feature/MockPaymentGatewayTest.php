<?php

namespace Tests\Feature;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Providers\MockPaymentGateway;
use Tests\TestCase;

class MockPaymentGatewayTest extends TestCase
{
    public function test_mock_payment_gateway_creates_intent_with_expiration(): void
    {
        $gateway = new MockPaymentGateway();
        $order = new Order([
            'order_number' => 'ORD-2026-0001',
            'total_amount' => 85.50,
        ]);

        $intent = $gateway->createPaymentIntent($order, 'CARD');

        $this->assertEquals('MOCK', $intent->gateway);
        $this->assertEquals(85.50, $intent->amount);
        $this->assertEquals('CARD', $intent->paymentMethod);
        $this->assertNotNull($intent->gatewayTransactionId);
        $this->assertNotNull($intent->expiresAt);
    }
}
