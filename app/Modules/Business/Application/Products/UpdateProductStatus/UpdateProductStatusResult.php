<?php

namespace App\Modules\Business\Application\Products\UpdateProductStatus;

use App\Modules\Business\Models\Product;

class UpdateProductStatusResult
{
    public function __construct(
        public readonly Product $product
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->product->uuid,
            'name' => $this->product->name,
            'status' => $this->product->status->value,
            'is_available' => $this->product->is_available,
            'updated_at' => $this->product->updated_at?->toIso8601String(),
        ];
    }
}
