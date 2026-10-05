<?php

namespace App\Modules\Business\Application\Categories\ListCategories;

use Illuminate\Support\Collection;

class ListCategoriesResult
{
    public function __construct(
        public readonly Collection $categories
    ) {}

    public function toArray(): array
    {
        return $this->categories->map(fn($c) => [
            'uuid' => $c->uuid,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'sort_order' => $c->sort_order,
            'status' => $c->status,
        ])->toArray();
    }
}
