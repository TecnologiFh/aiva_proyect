<?php

namespace App\Modules\Inventory\Application\ListInventory;

use App\Modules\Inventory\Repositories\InventoryStockRepository;

class ListInventoryAction
{
    public function __construct(
        private readonly InventoryStockRepository $stockRepository
    ) {}

    public function execute(ListInventoryInput $input): ListInventoryResult
    {
        $paginator = $this->stockRepository->paginate(
            tenantId: $input->tenantId,
            lowStockOnly: $input->lowStockOnly,
            page: $input->page,
            limit: $input->limit
        );

        return new ListInventoryResult($paginator);
    }
}
