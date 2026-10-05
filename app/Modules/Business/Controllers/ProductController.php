<?php

namespace App\Modules\Business\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Business\Application\Products\CreateProduct\CreateProductAction;
use App\Modules\Business\Application\Products\CreateProduct\CreateProductInput;
use App\Modules\Business\Application\Products\ListProducts\ListProductsAction;
use App\Modules\Business\Application\Products\ListProducts\ListProductsInput;
use App\Modules\Business\Application\Products\UpdateProductStatus\UpdateProductStatusAction;
use App\Modules\Business\Application\Products\UpdateProductStatus\UpdateProductStatusInput;
use App\Modules\Business\Requests\CreateProductRequest;
use App\Modules\Business\Requests\UpdateProductStatusRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index(Request $request, ListProductsAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListProductsInput(
            tenantId: $tenantId,
            search: $request->query('search'),
            categoryUuid: $request->query('category_uuid'),
            status: $request->query('status'),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Catálogo de productos recuperado exitosamente'
        );
    }

    public function store(CreateProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new CreateProductInput(
            tenantId: $tenantId,
            categoryUuid: $request->validated('category_uuid'),
            name: $request->validated('name'),
            basePrice: (float) $request->validated('base_price'),
            description: $request->validated('description'),
            searchKeywords: $request->validated('search_keywords'),
            productType: $request->validated('product_type', 'PRODUCT'),
            sku: $request->validated('sku'),
            imagePath: $request->validated('image_path'),
            options: $request->validated('options', []),
            createdById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Producto registrado exitosamente en el catálogo'
        );
    }

    public function updateStatus(string $uuid, UpdateProductStatusRequest $request, UpdateProductStatusAction $action): JsonResponse
    {
        $input = new UpdateProductStatusInput(
            productUuid: $uuid,
            newStatus: $request->validated('status'),
            isAvailable: $request->has('is_available') ? (bool) $request->validated('is_available') : null,
            updatedById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Estado comercial del producto actualizado correctamente'
        );
    }
}
