<?php

namespace App\Modules\Business\Repositories;

use App\Modules\Business\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function findById(int $id): ?Product
    {
        return Product::with(['category', 'options.values'])->find($id);
    }

    public function findByUuid(string $uuid): ?Product
    {
        return Product::with(['category', 'options.values'])->whereUuid($uuid)->first();
    }

    public function findBySku(string $sku, int $tenantId): ?Product
    {
        return Product::where('tenant_id', $tenantId)->where('sku', $sku)->first();
    }

    public function update(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product;
    }

    public function paginate(int $tenantId, ?string $search = null, ?int $categoryId = null, ?string $status = null, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = Product::with(['category', 'options.values'])
            ->where('tenant_id', $tenantId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('search_keywords', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('name', 'asc')->paginate(perPage: $limit, page: $page);
    }
}
