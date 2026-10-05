<?php

namespace App\Modules\CRM\Application\ListCustomers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCustomersResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($c) => [
            'uuid' => $c->uuid,
            'name' => $c->name,
            'email' => $c->email,
            'customer_stage' => $c->customer_stage->value,
            'total_orders_count' => (int) ($c->profile?->total_orders_count ?? 0),
            'total_spent' => (float) ($c->profile?->total_spent ?? 0.00),
            'average_ticket' => (float) ($c->profile?->average_ticket ?? 0.00),
            'customer_score' => (int) ($c->profile?->customer_score ?? 50),
            'tags' => $c->tags->pluck('name')->toArray(),
            'created_at' => $c->created_at?->toIso8601String(),
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
