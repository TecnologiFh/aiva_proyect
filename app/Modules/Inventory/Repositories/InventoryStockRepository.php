<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\InventoryStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryStockRepository
{
    public function findOrCreateForProduct(int $tenantId, int $productId, int $branchId = 1): InventoryStock
    {
        return InventoryStock::firstOrCreate(
            ['tenant_id' => $tenantId, 'branch_id' => $branchId, 'product_id' => $productId],
            ['current_stock' => 0.00, 'reserved_stock' => 0.00, 'minimum_stock' => 5.00, 'track_inventory' => true, 'is_out_of_stock' => false]
        );
    }

    public function findForUpdate(int $tenantId, int $productId, int $branchId = 1): ?InventoryStock
    {
        return InventoryStock::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    public function updateStock(int $id, array $data): InventoryStock
    {
        $stock = InventoryStock::findOrFail($id);
        $stock->update($data);
        return $stock;
    }

    public function paginate(int $tenantId, ?bool $lowStockOnly = false, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = InventoryStock::with('product')->where('tenant_id', $tenantId);

        if ($lowStockOnly) {
            $query->whereRaw('current_stock <= minimum_stock OR is_out_of_stock = 1');
        }

        return $query->orderBy('created_at', 'desc')->paginate(perPage: $limit, page: $page);
    }
}
