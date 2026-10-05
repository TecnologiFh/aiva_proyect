<?php

namespace App\Core\Events;

use App\Modules\Payments\Models\Payment;

class PaymentExpired
{
    public function __construct(
        public readonly Payment $payment
    ) {}
}
