<?php

namespace App\Modules\Inventory\Application\AdjustStock;

class AdjustStockInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $productId,
        public readonly float $quantity,
        public readonly string $movementType = 'STOCK_IN',
        public readonly string $movementReason = 'PURCHASE',
        public readonly float $unitCost = 0.00,
        public readonly ?string $notes = null,
        public readonly ?int $performedById = null,
        public readonly string $performedByType = 'USER'
    ) {}
}
