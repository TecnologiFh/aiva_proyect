<?php

namespace App\Modules\Orders\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Orders\Application\ConfirmOrder\ConfirmOrderAction;
use App\Modules\Orders\Application\ConfirmOrder\ConfirmOrderInput;
use App\Modules\Orders\Application\CreateOrderFromAI\CreateOrderFromAIAction;
use App\Modules\Orders\Application\CreateOrderFromAI\CreateOrderFromAIInput;
use App\Modules\Orders\Application\ListOrders\ListOrdersAction;
use App\Modules\Orders\Application\ListOrders\ListOrdersInput;
use App\Modules\Orders\Application\UpdateOrderStatus\UpdateOrderStatusAction;
use App\Modules\Orders\Application\UpdateOrderStatus\UpdateOrderStatusInput;
use App\Modules\Orders\Requests\CreateOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderStatusRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    public function index(Request $request, ListOrdersAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListOrdersInput(
            tenantId: $tenantId,
            status: $request->query('status'),
            search: $request->query('search'),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Pedidos comerciales recuperados exitosamente'
        );
    }

    public function store(CreateOrderRequest $request, CreateOrderFromAIAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new CreateOrderFromAIInput(
            tenantId: $tenantId,
            customerPhone: $request->validated('customer_phone'),
            customerName: $request->validated('customer_name'),
            entities: $request->validated('entities', []),
            conversationId: $request->validated('conversation_id'),
            deliveryAddress: $request->validated('delivery_address'),
            orderType: $request->validated('order_type', 'DELIVERY'),
            sendWhatsAppSummary: (bool) $request->validated('send_whatsapp_summary', true)
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Pedido comercial registrado correctamente'
        );
    }

    public function confirm(string $uuid, Request $request, ConfirmOrderAction $action): JsonResponse
    {
        $input = new ConfirmOrderInput(
            orderUuid: $uuid,
            paymentMethod: $request->input('payment_method', 'CASH'),
            notifyWhatsApp: (bool) $request->input('notify_whatsapp', true)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Pedido confirmado exitosamente'
        );
    }

    public function updateStatus(string $uuid, UpdateOrderStatusRequest $request, UpdateOrderStatusAction $action): JsonResponse
    {
        $input = new UpdateOrderStatusInput(
            orderUuid: $uuid,
            newStatus: $request->validated('status'),
            notifyWhatsApp: (bool) $request->validated('notify_whatsapp', true)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Estado del pedido actualizado correctamente'
        );
    }
}
