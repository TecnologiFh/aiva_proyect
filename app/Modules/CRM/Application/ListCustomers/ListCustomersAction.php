<?php

namespace App\Modules\CRM\Application\ListCustomers;

use App\Modules\CRM\Repositories\CustomerRepository;

class ListCustomersAction
{
    public function __construct(
        private readonly CustomerRepository $customerRepository
    ) {}

    public function execute(ListCustomersInput $input): ListCustomersResult
    {
        $paginator = $this->customerRepository->paginate(
            tenantId: $input->tenantId,
            stage: $input->stage,
            search: $input->search,
            page: $input->page,
            limit: $input->limit
        );

        return new ListCustomersResult($paginator);
    }
}
