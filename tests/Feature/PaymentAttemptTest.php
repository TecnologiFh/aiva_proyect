<?php

namespace Tests\Feature;

use App\Modules\Payments\Models\PaymentAttempt;

use Tests\TestCase;

class PaymentAttemptTest extends TestCase
{
    public function test_payment_attempt_model_casts(): void
    {
        $attempt = new PaymentAttempt([
            'tenant_id' => 1,
            'payment_id' => 10,
            'attempt_number' => 1,
            'status' => 'APPROVED',
            'raw_response' => ['code' => '200_OK'],
        ]);

        $this->assertEquals(1, $attempt->tenant_id);
        $this->assertEquals(10, $attempt->payment_id);
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertEquals('APPROVED', $attempt->status);
        $this->assertEquals('200_OK', $attempt->raw_response['code']);
    }
}
