<?php

namespace App\Core\Events;

use App\Modules\Payments\Models\Payment;

class PaymentRejected
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?string $reason = null
    ) {}
}
