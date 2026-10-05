<?php

namespace App\Modules\Payments\DTOs;

class PaymentVerificationDTO
{
    public function __construct(
        public readonly bool $isApproved,
        public readonly string $status,
        public readonly ?string $gatewayTransactionId = null,
        public readonly ?string $authorizationCode = null,
        public readonly ?string $paidAt = null,
        public readonly array $rawResponse = []
    ) {}
}
