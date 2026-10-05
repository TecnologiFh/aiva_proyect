<?php

namespace App\Core\Events;

use App\Modules\Business\Models\Category;

class CategoryCreated
{
    public function __construct(
        public readonly Category $category
    ) {}
}
