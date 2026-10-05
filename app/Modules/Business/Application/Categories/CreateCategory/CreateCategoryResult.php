<?php

namespace App\Modules\Business\Application\Categories\CreateCategory;

use App\Modules\Business\Models\Category;

class CreateCategoryResult
{
    public function __construct(
        public readonly Category $category
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->category->uuid,
            'name' => $this->category->name,
            'slug' => $this->category->slug,
            'description' => $this->category->description,
            'sort_order' => $this->category->sort_order,
            'status' => $this->category->status,
            'created_at' => $this->category->created_at?->toIso8601String(),
        ];
    }
}
