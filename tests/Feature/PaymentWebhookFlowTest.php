<?php

namespace Tests\Feature;

use App\Core\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Models\Payment;
use Tests\TestCase;

class PaymentWebhookFlowTest extends TestCase
{
    public function test_payment_model_attributes_and_status_casts(): void
    {
        $payment = new Payment([
            'tenant_id' => 1,
            'gateway' => 'STRIPE',
            'payment_method' => 'CARD',
            'amount' => 120.00,
            'status' => PaymentTransactionStatus::PENDING->value,
        ]);

        $this->assertEquals(1, $payment->tenant_id);
        $this->assertEquals('STRIPE', $payment->gateway);
        $this->assertEquals(PaymentTransactionStatus::PENDING, $payment->status);

        $payment->status = PaymentTransactionStatus::APPROVED;
        $this->assertEquals(PaymentTransactionStatus::APPROVED, $payment->status);
    }
}
