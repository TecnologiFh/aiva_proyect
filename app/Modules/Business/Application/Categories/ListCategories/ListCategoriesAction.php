<?php

namespace App\Modules\Business\Application\Categories\ListCategories;

use App\Modules\Business\Repositories\CategoryRepository;

class ListCategoriesAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    public function execute(ListCategoriesInput $input): ListCategoriesResult
    {
        $categories = $this->categoryRepository->allByTenant($input->tenantId);
        return new ListCategoriesResult($categories);
    }
}
