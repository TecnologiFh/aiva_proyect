<?php

namespace App\Modules\Business\Repositories;

use App\Modules\Business\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    public function findByUuid(string $uuid): ?Category
    {
        return Category::whereUuid($uuid)->first();
    }

    public function findBySlug(string $slug, int $tenantId): ?Category
    {
        return Category::where('tenant_id', $tenantId)->where('slug', $slug)->first();
    }

    public function allByTenant(int $tenantId): Collection
    {
        return Category::where('tenant_id', $tenantId)->orderBy('sort_order', 'asc')->get();
    }

    public function update(int $id, array $data): Category
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        return $category;
    }
}
