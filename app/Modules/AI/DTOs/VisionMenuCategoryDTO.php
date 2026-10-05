<?php

namespace App\Modules\AI\DTOs;

final class VisionMenuCategoryDTO
{
    /**
     * @param VisionMenuItemDTO[] $items
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $normalizedName = null,
        public readonly ?string $description = null,
        public readonly array $items = []
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'description' => $this->description,
            'items' => array_map(fn(VisionMenuItemDTO $item) => $item->toArray(), $this->items),
        ];
    }
}
