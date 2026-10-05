<?php

namespace App\Modules\Payments\Domain\ValueObjects;

class TenantPaymentPolicy
{
    public function __construct(
        public readonly int $tenantId,
        public readonly bool $autoAuthorizationEnabled = true,
        public readonly array $allowedProviders = ['YAPE', 'PLIN'],
        public readonly float $maxAutoPaymentAmount = 500.00,
        public readonly bool $allowPartialPayment = false,
        public readonly bool $allowOverpayment = false,
        public readonly float $requiredConfidenceAmount = 0.85,
        public readonly float $requiredConfidenceOperationId = 0.85,
        public readonly int $evidenceExpirationMinutes = 1440
    ) {}

    public static function defaultForTenant(int $tenantId): self
    {
        return new self(tenantId: $tenantId);
    }
}
