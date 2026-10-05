<?php

namespace App\Core\Events;

use App\Modules\Business\Models\Product;

class ProductStatusChanged
{
    public function __construct(
        public readonly Product $product,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}
}
