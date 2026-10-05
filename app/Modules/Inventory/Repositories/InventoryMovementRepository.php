<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryMovementRepository
{
    public function create(array $data): InventoryMovement
    {
        return InventoryMovement::create($data);
    }

    public function paginateByStock(int $stockId, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        return InventoryMovement::where('stock_id', $stockId)
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $limit, page: $page);
    }

    public function paginateByTenant(int $tenantId, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        return InventoryMovement::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $limit, page: $page);
    }
}
