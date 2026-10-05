<?php

namespace App\Modules\Payments\Application\CreatePaymentIntent;

use App\Modules\Payments\Models\Payment;

class CreatePaymentIntentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $formattedInstructions
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->payment->uuid,
            'gateway' => $this->payment->gateway,
            'payment_method' => $this->payment->payment_method->value,
            'gateway_transaction_id' => $this->payment->gateway_transaction_id,
            'amount' => (float) $this->payment->amount,
            'currency' => $this->payment->currency->value,
            'status' => $this->payment->status->value,
            'qr_code_url' => $this->payment->qr_code_url,
            'payment_link' => $this->payment->payment_link,
            'expires_at' => $this->payment->expires_at?->toIso8601String(),
            'formatted_instructions' => $this->formattedInstructions,
            'created_at' => $this->payment->created_at?->toIso8601String(),
        ];
    }
}
