<?php

namespace App\Modules\Business\Application\Products\CreateProduct;

class CreateProductInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $categoryUuid,
        public readonly string $name,
        public readonly float $basePrice,
        public readonly ?string $description = null,
        public readonly ?string $searchKeywords = null,
        public readonly string $productType = 'PRODUCT',
        public readonly ?string $sku = null,
        public readonly ?string $imagePath = null,
        public readonly array $options = [],
        public readonly int $createdById = 0
    ) {}
}
