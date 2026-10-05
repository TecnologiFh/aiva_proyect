<?php

namespace App\Modules\Orders\Application\ListOrders;

use App\Modules\Orders\Repositories\OrderRepository;

class ListOrdersAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository
    ) {}

    public function execute(ListOrdersInput $input): ListOrdersResult
    {
        $paginator = $this->orderRepository->paginate(
            tenantId: $input->tenantId,
            status: $input->status,
            search: $input->search,
            page: $input->page,
            limit: $input->limit
        );

        return new ListOrdersResult($paginator);
    }
}
