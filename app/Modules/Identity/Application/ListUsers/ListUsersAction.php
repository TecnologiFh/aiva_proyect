<?php

namespace App\Modules\Identity\Application\ListUsers;

use App\Modules\Identity\Models\User;

class ListUsersAction
{
    public function execute(ListUsersInput $input): ListUsersResult
    {
        $query = User::with('roles')
            ->where('tenant_id', $input->tenantId);

        if ($input->search) {
            $query->where(function ($q) use ($input) {
                $q->where('name', 'like', "%{$input->search}%")
                  ->orWhere('email', 'like', "%{$input->search}%");
            });
        }

        if ($input->status) {
            $query->where('status', $input->status);
        }

        if ($input->role) {
            $query->whereHas('roles', fn($q) => $q->where('name', $input->role));
        }

        $paginator = $query->orderBy('name', 'asc')
            ->paginate(perPage: $input->limit, page: $input->page);

        return new ListUsersResult(
            paginator: $paginator,
            activeFilters: array_filter([
                'search' => $input->search,
                'status' => $input->status,
                'role' => $input->role,
            ])
        );
    }
}
