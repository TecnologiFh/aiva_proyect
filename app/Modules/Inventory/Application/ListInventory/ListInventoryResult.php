<?php

namespace App\Modules\Inventory\Application\ListInventory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInventoryResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($s) => [
            'uuid' => $s->uuid,
            'branch_id' => $s->branch_id,
            'product_id' => $s->product_id,
            'product_name' => $s->product?->name ?? 'Producto',
            'current_stock' => (float) $s->current_stock,
            'reserved_stock' => (float) $s->reserved_stock,
            'available_stock' => (float) $s->available_stock,
            'minimum_stock' => (float) $s->minimum_stock,
            'track_inventory' => (bool) $s->track_inventory,
            'is_out_of_stock' => (bool) $s->is_out_of_stock,
            'is_low_stock' => ($s->current_stock <= $s->minimum_stock),
            'updated_at' => $s->updated_at?->toIso8601String(),
        ]);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $this->paginator->currentPage(),
                'per_page' => $this->paginator->perPage(),
                'total' => $this->paginator->total(),
                'last_page' => $this->paginator->lastPage(),
            ],
        ];
    }
}
