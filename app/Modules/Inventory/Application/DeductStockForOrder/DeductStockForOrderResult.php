<?php

namespace App\Modules\Inventory\Application\DeductStockForOrder;

class DeductStockForOrderResult
{
    public function __construct(
        public readonly bool $deducted,
        public readonly int $deductedItemsCount = 0
    ) {}

    public function toArray(): array
    {
        return [
            'deducted' => $this->deducted,
            'deducted_items_count' => $this->deductedItemsCount,
        ];
    }
}
