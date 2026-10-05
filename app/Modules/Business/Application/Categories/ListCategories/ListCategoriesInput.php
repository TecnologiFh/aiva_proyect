<?php

namespace App\Modules\Business\Application\Categories\ListCategories;

class ListCategoriesInput
{
    public function __construct(
        public readonly int $tenantId
    ) {}
}
