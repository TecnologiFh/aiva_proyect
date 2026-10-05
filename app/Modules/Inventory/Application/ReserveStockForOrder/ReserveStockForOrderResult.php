<?php

namespace App\Modules\Inventory\Application\ReserveStockForOrder;

class ReserveStockForOrderResult
{
    public function __construct(
        public readonly bool $reserved,
        public readonly int $reservedItemsCount = 0
    ) {}

    public function toArray(): array
    {
        return [
            'reserved' => $this->reserved,
            'reserved_items_count' => $this->reservedItemsCount,
        ];
    }
}
