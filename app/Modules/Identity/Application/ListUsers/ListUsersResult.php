<?php

namespace App\Modules\Identity\Application\ListUsers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsersResult
{
    public function __construct(
        public readonly LengthAwarePaginator $paginator,
        public readonly array $activeFilters = []
    ) {}

    public function toArray(): array
    {
        $items = collect($this->paginator->items())->map(fn($user) => [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'roles' => $user->roles->pluck('name')->toArray(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'created_at' => $user->created_at?->toIso8601String(),
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
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
