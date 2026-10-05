<?php

namespace App\Modules\Business\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Business\Application\Categories\CreateCategory\CreateCategoryAction;
use App\Modules\Business\Application\Categories\CreateCategory\CreateCategoryInput;
use App\Modules\Business\Application\Categories\ListCategories\ListCategoriesAction;
use App\Modules\Business\Application\Categories\ListCategories\ListCategoriesInput;
use App\Modules\Business\Requests\CreateCategoryRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    public function index(Request $request, ListCategoriesAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $input = new ListCategoriesInput(tenantId: $tenantId);
        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Categorías recuperadas exitosamente'
        );
    }

    public function store(CreateCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new CreateCategoryInput(
            tenantId: $tenantId,
            name: $request->validated('name'),
            description: $request->validated('description'),
            searchKeywords: $request->validated('search_keywords'),
            sortOrder: (int) $request->validated('sort_order', 0),
            createdById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Categoría creada correctamente'
        );
    }
}
