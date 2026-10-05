<?php

namespace App\Modules\AI\DTOs;

final class VisionMenuItemDTO
{
    /**
     * @param float[] $detectedPrices
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $normalizedName = null,
        public readonly ?string $description = null,
        public readonly ?float $basePrice = null,
        public readonly string $currency = 'PEN',
        public readonly float $confidence = 1.0,
        public readonly bool $isAvailable = true,
        public readonly bool $isPriceAmbiguous = false,
        public readonly ?string $priceRaw = null,
        public readonly array $detectedPrices = []
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'description' => $this->description,
            'base_price' => $this->basePrice,
            'currency' => $this->currency,
            'confidence' => $this->confidence,
            'is_available' => $this->isAvailable,
            'is_price_ambiguous' => $this->isPriceAmbiguous,
            'price_raw' => $this->priceRaw,
            'detected_prices' => $this->detectedPrices,
        ];
    }
}
