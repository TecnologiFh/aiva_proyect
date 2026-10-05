<?php

namespace App\Modules\Payments\Application\ListPayments;

class ListPaymentsInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $status = null,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
