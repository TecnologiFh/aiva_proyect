<?php

namespace App\Modules\Business\Application\Products\UpdateProductStatus;

class UpdateProductStatusInput
{
    public function __construct(
        public readonly string $productUuid,
        public readonly string $newStatus,
        public readonly ?bool $isAvailable = null,
        public readonly int $updatedById = 0
    ) {}
}
