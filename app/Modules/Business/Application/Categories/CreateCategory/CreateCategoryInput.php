<?php

namespace App\Modules\Business\Application\Categories\CreateCategory;

class CreateCategoryInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $searchKeywords = null,
        public readonly int $sortOrder = 0,
        public readonly int $createdById = 0
    ) {}
}
