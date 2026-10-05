<?php

namespace App\Modules\Inventory\Application\DeductStockForOrder;

class DeductStockForOrderInput
{
    public function __construct(
        public readonly string $orderUuid
    ) {}
}
