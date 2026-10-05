<?php

namespace App\Modules\Payments\Application\ListPayments;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPaymentsResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($p) => [
            'uuid' => $p->uuid,
            'gateway' => $p->gateway,
            'payment_method' => $p->payment_method->value,
            'gateway_transaction_id' => $p->gateway_transaction_id,
            'amount' => (float) $p->amount,
            'currency' => $p->currency->value,
            'status' => $p->status->value,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'attempts_count' => $p->attempts->count(),
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
        ];
    }
}
