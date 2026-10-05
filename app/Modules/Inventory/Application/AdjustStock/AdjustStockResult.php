<?php

namespace App\Modules\Inventory\Application\AdjustStock;

use App\Modules\Inventory\Models\InventoryStock;

class AdjustStockResult
{
    public function __construct(
        public readonly InventoryStock $stock,
        public readonly float $previousStock,
        public readonly float $newStock
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->stock->uuid,
            'product_id' => $this->stock->product_id,
            'previous_stock' => $this->previousStock,
            'new_stock' => $this->newStock,
            'available_stock' => $this->stock->available_stock,
            'is_out_of_stock' => $this->stock->is_out_of_stock,
            'updated_at' => $this->stock->updated_at?->toIso8601String(),
        ];
    }
}
