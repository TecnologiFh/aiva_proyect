<?php

namespace App\Modules\Business\Application\Products\ListProducts;

class ListProductsInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $search = null,
        public readonly ?string $categoryUuid = null,
        public readonly ?string $status = null,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
