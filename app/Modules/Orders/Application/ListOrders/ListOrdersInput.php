<?php

namespace App\Modules\Orders\Application\ListOrders;

class ListOrdersInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $status = null,
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
