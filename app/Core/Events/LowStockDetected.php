<?php

namespace App\Core\Events;

use App\Modules\Inventory\Models\InventoryStock;

class LowStockDetected
{
    public function __construct(
        public readonly InventoryStock $stock
    ) {}
}
