<?php

namespace App\Modules\Orders\Controllers;

use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class OperationsCenterController extends Controller
{
    protected UserTenantAccessService $tenantAccessService;

    public function __construct()
    {
        $this->tenantAccessService = new UserTenantAccessService();
    }

    /**
     * Obtener los datos del Centro de Operaciones (Alertas, Pedidos y agrupamiento por Zonas de Delivery).
     */
    public function getOperationsData(Request $request)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);

        // Auto-sincronización en vivo de pedidos desde conversaciones activas
        try {
            $recentConvs = DB::table('conversations')->where('tenant_id', $tenantId)->get();
            $orderParser = new \App\Modules\Orders\Services\AutomaticOrderParserService();
            foreach ($recentConvs as $conv) {
                $allMsgs = DB::table('messages')->where('conversation_id', $conv->id)->orderBy('id')->get();
                if ($allMsgs->isNotEmpty()) {
                    $customerMsgs = $allMsgs->whereIn('sender_type', ['CUSTOMER', 'USER'])->map(fn($m) => $m->content)->join(" ");
                    $aiMsgs = $allMsgs->where('sender_type', 'AI')->map(fn($m) => $m->content)->join(" ");
                    $orderParser->syncOrderFromConversation($tenantId, $conv->customer_phone, $conv->customer_name, $aiMsgs, $customerMsgs);
                }
            }
        } catch (\Throwable $e) {}

        // 1. Alertas Operativas en tiempo real
        $unansweredConversations = DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->where('mode', 'HUMAN')
            ->count();

        $pendingPayments = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED')
            ->where('payment_status', 'PENDING')
            ->count();

        $readyDeliveries = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED')
            ->where('order_type', 'DELIVERY')
            ->whereIn('fulfillment_status', ['READY', 'OUT_FOR_DELIVERY'])
            ->count();

        // 2. Filtro inteligente por Fecha (Si no hay pedidos HOY, muestra TODOS los pedidos del negocio)
        $dateFilter = $request->input('date_filter');
        if (empty($dateFilter)) {
            $todayCount = DB::table('orders')->where('tenant_id', $tenantId)->whereDate('created_at', now()->toDateString())->count();
            $dateFilter = ($todayCount > 0) ? 'TODAY' : 'ALL';
        }

        $query = DB::table('orders')->where('tenant_id', $tenantId);

        if ($dateFilter === 'TODAY') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($dateFilter !== 'ALL' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            $query->whereDate('created_at', $dateFilter);
        }

        // Pedidos agrupados por Zonas Geográficas de Delivery (excluyendo cancelados)
        $deliveryOrders = (clone $query)
            ->where('order_type', 'DELIVERY')
            ->where('status', '!=', 'CANCELLED')
            ->where('fulfillment_status', '!=', 'CANCELLED')
            ->whereNotNull('delivery_zone')
            ->orderByDesc('id')
            ->get();

        $ordersByZone = $deliveryOrders->groupBy('delivery_zone');

        // Todos los pedidos filtrados por la fecha seleccionada
        $allOrders = (clone $query)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'tenant_id' => $tenantId,
            'date_filter' => $dateFilter,
            'alerts' => [
                'attention_required' => $unansweredConversations,
                'pending_payments' => $pendingPayments,
                'ready_deliveries' => $readyDeliveries,
            ],
            'delivery_board' => $ordersByZone,
            'orders' => $allOrders,
        ]);
    }

    /**
     * API JSON: Actualizar Estado de un Pedido con Mutation Scope Enforcement (P2.9.3-05, UX11)
     */
    public function updateOrderStatus(Request $request, int $orderId)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();

        // 1. Backend Authorization Check
        if (!\App\Services\Authorization\AccessResolver::can('tenant.orders.update', 'operations_center')) {
            return response()->json(['status' => 'ERROR', 'code' => 'PERMISSION_DENIED', 'message' => 'Tu rol no posee permisos para modificar el estado de pedidos.'], 403);
        }

        // 2. Mutation Scope Enforcement (Verificar que el pedido pertenece al Data Scope del usuario)
        $orderScope = \App\Services\Authorization\AccessResolver::scope(auth()->user(), 'orders');
        $order = DB::table('orders')->where('id', $orderId)->where($orderScope)->first();

        if (!$order) {
            return response()->json(['status' => 'ERROR', 'code' => 'RESOURCE_FORBIDDEN', 'message' => 'No tienes acceso a este pedido o no pertenece a tu alcance.'], 403);
        }

        $newStatus = strtoupper(trim($request->input('fulfillment_status', 'PREPARING')));

        if ($newStatus === 'CANCELLED') {
            $cancellationService = new \App\Modules\Orders\Services\OrderCancellationService();
            $cancellationResult = $cancellationService->cancelOrder(
                tenantId: $tenantId,
                orderId: $orderId,
                cancelledBy: 'OPERATOR',
                channel: 'DASHBOARD',
                reason: 'Anulación manual desde el Centro de Operaciones'
            );

            return response()->json([
                'status' => 'SUCCESS',
                'message' => \App\Modules\Orders\Mappers\CancellationResponseMapper::mapToDashboardMessage($cancellationResult),
                'order_id' => $orderId,
                'fulfillment_status' => 'CANCELLED',
                'cancellation_result' => $cancellationResult,
            ]);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'fulfillment_status' => $newStatus,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Estado del pedido #{$orderId} actualizado a {$newStatus}.",
            'order_id' => $orderId,
            'fulfillment_status' => $newStatus
        ]);
    }
}
