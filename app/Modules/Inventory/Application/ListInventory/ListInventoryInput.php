<?php

namespace App\Modules\Inventory\Application\ListInventory;

class ListInventoryInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly bool $lowStockOnly = false,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
