<?php

namespace App\Modules\Business\Application\Products\ListProducts;

use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Repositories\ProductRepository;

class ListProductsAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository
    ) {}

    public function execute(ListProductsInput $input): ListProductsResult
    {
        $categoryId = null;
        if ($input->categoryUuid) {
            $category = $this->categoryRepository->findByUuid($input->categoryUuid);
            $categoryId = $category?->id;
        }

        $paginator = $this->productRepository->paginate(
            tenantId: $input->tenantId,
            search: $input->search,
            categoryId: $categoryId,
            status: $input->status,
            page: $input->page,
            limit: $input->limit
        );

        return new ListProductsResult(
            paginator: $paginator,
            activeFilters: array_filter([
                'search' => $input->search,
                'category_uuid' => $input->categoryUuid,
                'status' => $input->status,
            ])
        );
    }
}
