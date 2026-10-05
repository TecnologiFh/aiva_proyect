<?php

namespace App\Modules\Business\Application\Products\ListProducts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProductsResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator,
        public readonly array $activeFilters = []
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($p) => [
            'uuid' => $p->uuid,
            'name' => $p->name,
            'category' => $p->category?->name,
            'product_type' => $p->product_type->value,
            'sku' => $p->sku,
            'base_price' => (float) $p->base_price,
            'image_path' => $p->image_path,
            'status' => $p->status->value,
            'is_available' => $p->is_available,
            'options_count' => $p->options->count(),
            'created_at' => $p->created_at?->toIso8601String(),
        ]);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $this->paginator->currentPage(),
                'per_page' => $this->paginator->perPage(),
                'total' => $this->paginator->total(),
                'last_page' => $this->paginator->lastPage(),
            ],
            'filters' => $this->activeFilters,
        ];
    }
}
