<?php

namespace App\Modules\Orders\Application\ListOrders;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListOrdersResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($o) => [
            'uuid' => $o->uuid,
            'order_number' => $o->order_number,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'order_type' => $o->order_type->value,
            'status' => $o->status->value,
            'payment_status' => $o->payment_status->value,
            'payment_method' => $o->payment_method->value,
            'total_amount' => (float) $o->total_amount,
            'items_count' => $o->items->count(),
            'created_at' => $o->created_at?->toIso8601String(),
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
