<?php

namespace App\Core\Events;

use App\Modules\Payments\Models\Payment;

class PaymentIntentCreated
{
    public function __construct(
        public readonly Payment $payment
    ) {}
}
