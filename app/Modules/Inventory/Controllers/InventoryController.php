<?php

namespace App\Modules\Inventory\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Inventory\Application\AdjustStock\AdjustStockAction;
use App\Modules\Inventory\Application\AdjustStock\AdjustStockInput;
use App\Modules\Inventory\Application\ListInventory\ListInventoryAction;
use App\Modules\Inventory\Application\ListInventory\ListInventoryInput;
use App\Modules\Inventory\Repositories\InventoryMovementRepository;
use App\Modules\Inventory\Requests\AdjustStockRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InventoryController extends Controller
{
    public function index(Request $request, ListInventoryAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListInventoryInput(
            tenantId: $tenantId,
            lowStockOnly: $request->boolean('low_stock_only', false),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Existencias de inventario recuperadas exitosamente'
        );
    }

    public function adjust(AdjustStockRequest $request, AdjustStockAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $user = $request->user();

        $input = new AdjustStockInput(
            tenantId: $tenantId,
            productId: (int) $request->validated('product_id'),
            quantity: (float) $request->validated('quantity'),
            movementType: $request->validated('movement_type', 'STOCK_IN'),
            movementReason: $request->validated('movement_reason', 'PURCHASE'),
            unitCost: (float) $request->validated('unit_cost', 0.00),
            notes: $request->validated('notes'),
            performedById: $user?->id,
            performedByType: 'USER'
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Ajuste de inventario registrado correctamente'
        );
    }

    public function movements(Request $request, InventoryMovementRepository $repository): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 15);

        $paginator = $repository->paginateByTenant($tenantId, $page, $limit);

        return ApiResponse::success(
            data: [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ]
            ],
            message: 'Movimientos Kardex recuperados exitosamente'
        );
    }
}
