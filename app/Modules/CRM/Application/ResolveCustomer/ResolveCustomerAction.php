<?php

namespace App\Modules\CRM\Application\ResolveCustomer;

use App\Modules\CRM\Repositories\CustomerRepository;
use App\Modules\CRM\Services\CustomerBrainCompiler;

class ResolveCustomerAction
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerBrainCompiler $brainCompiler
    ) {}

    public function execute(ResolveCustomerInput $input): ResolveCustomerResult
    {
        $customer = $this->customerRepository->findOrCreateByIdentity(
            tenantId: $input->tenantId,
            channel: $input->channel,
            identifier: $input->identifier,
            name: $input->name
        );

        $brainSnapshot = $this->brainCompiler->compile($customer);

        return new ResolveCustomerResult($customer, $brainSnapshot);
    }
}
