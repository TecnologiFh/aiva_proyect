<?php

namespace App\Modules\Automation\Triggers;

use App\Core\Events\PaymentApproved;

class PaymentApprovedTrigger implements AutomationTriggerInterface
{
    public function matches(string $eventName): bool
    {
        return $eventName === PaymentApproved::class;
    }

    public function extractPayload(object $event): array
    {
        /** @var PaymentApproved $event */
        $payment = $event->payment;

        return [
            'tenant_id' => $payment->tenant_id,
            'payment' => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'gateway' => $payment->gateway,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method->value,
                'status' => $payment->status->value,
            ],
        ];
    }

    public function getTriggerName(): string
    {
        return 'PAYMENT_APPROVED';
    }
}
