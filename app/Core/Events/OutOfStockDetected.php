<?php

namespace App\Core\Events;

use App\Modules\Inventory\Models\InventoryStock;

class OutOfStockDetected
{
    public function __construct(
        public readonly InventoryStock $stock
    ) {}
}
