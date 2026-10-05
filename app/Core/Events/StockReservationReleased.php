<?php

namespace App\Core\Events;

use App\Modules\Inventory\Models\InventoryStock;

class StockReservationReleased
{
    public function __construct(
        public readonly InventoryStock $stock,
        public readonly float $quantity
    ) {}
}
