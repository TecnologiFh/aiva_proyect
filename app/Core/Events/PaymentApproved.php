<?php

namespace App\Core\Events;

use App\Modules\Payments\Models\Payment;

class PaymentApproved
{
    public function __construct(
        public readonly Payment $payment
    ) {}
}
