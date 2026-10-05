<?php

namespace App\Modules\Business\DTOs;

class CanonicalProductDTO
{
    public function __construct(
        public readonly CoreProductDTO $core,
        public readonly CompatibilityDTO $compatibility,
        public readonly ProductAttributesDTO $attributes,
        public readonly InventoryDTO $inventory,
        public readonly ProvenanceDTO $provenance
    ) {}

    public function toArray(): array
    {
        return [
            'core' => $this->core->toArray(),
            'compatibility' => $this->compatibility->toArray(),
            'attributes' => $this->attributes->toArray(),
            'inventory' => $this->inventory->toArray(),
            'provenance' => $this->provenance->toArray(),
        ];
    }

    /**
     * Retorna una representación aplanada para proyección comercial en la tabla products
     */
    public function toProductProjectionArray(): array
    {
        return [
            'name' => $this->core->name,
            'sku' => $this->core->sku,
            'description' => $this->core->description,
            'base_price' => $this->core->basePrice,
            'price_status' => $this->core->priceStatus,
            'status' => $this->core->status,
            'stock' => $this->inventory->stock,
            'stock_status' => $this->inventory->stockStatus,
            'compatibility' => $this->compatibility->toArray(),
            'attributes' => $this->attributes->toArray(),
        ];
    }
}
