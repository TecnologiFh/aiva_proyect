<?php

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;

interface PaymentSourceVerifierInterface
{
    public function verify(CanonicalPaymentEvidence $evidence): array;
}
