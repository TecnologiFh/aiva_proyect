<?php

namespace App\Modules\CRM\Application\ListCustomers;

class ListCustomersInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $stage = null,
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
