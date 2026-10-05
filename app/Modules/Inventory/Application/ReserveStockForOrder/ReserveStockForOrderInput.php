<?php

namespace App\Modules\Inventory\Application\ReserveStockForOrder;

class ReserveStockForOrderInput
{
    public function __construct(
        public readonly string $orderUuid
    ) {}
}
