<?php

namespace App\Modules\Business\DTOs;

class ProductAttributesDTO
{
    public function __construct(
        public readonly ?string $side = null,
        public readonly ?string $color = null,
        public readonly ?string $material = null,
        public readonly ?string $presentation = null,
        public readonly array $customSpecs = []
    ) {}

    public function toArray(): array
    {
        return [
            'side' => $this->side,
            'color' => $this->color,
            'material' => $this->material,
            'presentation' => $this->presentation,
            'custom_specs' => $this->customSpecs,
        ];
    }
}
