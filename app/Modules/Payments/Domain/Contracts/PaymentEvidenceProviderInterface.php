<?php

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;

interface PaymentEvidenceProviderInterface
{
    public function identify(array $documentData): bool;

    public function extract(array $documentData, int $tenantId): CanonicalPaymentEvidence;

    public function validate(CanonicalPaymentEvidence $evidence, array $context): array;
}
