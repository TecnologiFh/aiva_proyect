<?php

namespace App\Modules\CRM\Application\ResolveCustomer;

use App\Modules\CRM\DTOs\CustomerBrainSnapshotDTO;
use App\Modules\CRM\Models\Customer;

class ResolveCustomerResult
{
    public function __construct(
        public readonly Customer $customer,
        public readonly CustomerBrainSnapshotDTO $brainSnapshot
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->customer->uuid,
            'name' => $this->customer->name,
            'customer_stage' => $this->customer->customer_stage->value,
            'brain_snapshot' => $this->brainSnapshot->toArray(),
        ];
    }
}
