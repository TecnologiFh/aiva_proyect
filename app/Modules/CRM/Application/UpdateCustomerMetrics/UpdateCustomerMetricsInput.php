<?php

namespace App\Modules\CRM\Application\UpdateCustomerMetrics;

class UpdateCustomerMetricsInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly float $orderAmount,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $deliveryType = null
    ) {}
}
