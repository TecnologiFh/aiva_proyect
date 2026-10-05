<?php

namespace App\Modules\CRM\Application\ResolveCustomer;

class ResolveCustomerInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $channel,
        public readonly string $identifier,
        public readonly ?string $name = null
    ) {}
}
