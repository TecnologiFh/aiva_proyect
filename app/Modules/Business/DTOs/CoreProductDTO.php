<?php

namespace App\Modules\Business\DTOs;

class CoreProductDTO
{
    public const PRICE_CONFIRMED = 'CONFIRMED';
    public const PRICE_REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    public function __construct(
        public readonly string $name,
        public readonly ?string $sku = null,
        public readonly string $category = 'General',
        public readonly ?string $description = null,
        public readonly ?string $basePrice = null,
        public readonly string $priceStatus = self::PRICE_CONFIRMED,
        public readonly string $status = 'ACTIVE'
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $this->category,
            'description' => $this->description,
            'base_price' => $this->basePrice,
            'price_status' => $this->priceStatus,
            'status' => $this->status,
        ];
    }
}
