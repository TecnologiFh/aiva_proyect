<?php

namespace App\Core\Events;

use App\Modules\Business\Models\Product;

class ProductCreated
{
    public function __construct(
        public readonly Product $product
    ) {}
}
