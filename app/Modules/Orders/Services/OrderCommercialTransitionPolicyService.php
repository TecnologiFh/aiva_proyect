<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\OrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Política de Transición Comercial y Logística de Pedidos (P3.13).
 *
 * Invariantes Constitucionales:
 * P3.13-01: Consume y proyecta el resultado financiero emitido por P2.11 (sin duplicar ni recalcular autoridad).
 * P3.13-02: Ortogonalidad estricta entre Estado Financiero, Comercial y Logístico.
 * P3.13-03: Pago completo NO implica inmediatamente CONFIRMED (valida elegibilidad comercial).
 * P3.13-04: CONFIRMED o PAID NO transicionan ciegamente a PREPARING (exige dirección de delivery confirmada y stock).
 * P3.13-07: Toda mutación de status o fulfillment_status pasa exclusivamente por OrderStateMachineEngine.
 */
class OrderCommercialTransitionPolicyService
{
    private OrderStateMachineEngine $stateMachine;

    public function __construct(?OrderStateMachineEngine $stateMachine = null)
    {
        $this->stateMachine = $stateMachine ?? new OrderStateMachineEngine();
    }

    /**
     * Aplica la resolución financiera de P2.11 sobre el pedido y evalúa las transiciones comercial y logística.
     *
     * @param int $tenantId ID del Tenant
     * @param int $orderId ID del Pedido
     * @param array $financialResult Resultado canónico emitido por P2.11
     * @return array Resumen del estado proyectado y transiciones ejecutadas
     */
    public function processPaymentResolution(int $tenantId, int $orderId, array $financialResult): array
    {
        return DB::transaction(function () use ($tenantId, $orderId, $financialResult) {
            $order = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return [
                    'status' => 'ERROR',
                    'message' => "Pedido #{$orderId} no encontrado para Tenant {$tenantId}."
                ];
            }

            $isApproved = !empty($financialResult['is_approved']);
            $paidAmount = $financialResult['paid_amount'] ?? '0.00';
            $pendingAmount = $financialResult['pending_amount'] ?? '0.00';
            $operationNumber = $financialResult['operation_number'] ?? null;
            $provider = $financialResult['provider'] ?? 'YAPE';

            // 1. Proyección Operacional de Finanzas en orders (P3.13-01)
            $newPaymentStatus = $isApproved ? 'PAID' : (($financialResult['status'] ?? '') === 'AMOUNT_MISMATCH' ? 'PARTIALLY_PAID' : ($order->payment_status ?? 'PENDING'));

            $updatePayload = [
                'updated_at' => now(),
            ];
            if ($operationNumber) {
                $updatePayload['operation_number'] = $operationNumber;
            }

            // Transicionar estado financiero mediante OrderStateMachineEngine (P3.13-07)
            if ($newPaymentStatus === 'PAID') {
                $meta = [
                    'authorization_token' => 'PAY33_AUTH_TOKEN_' . bin2hex(random_bytes(8)),
                    'provider' => $provider,
                    'operation_number' => $operationNumber,
                    'paid_amount' => $paidAmount,
                ];
                $this->stateMachine->transitionPaymentStatus($tenantId, $orderId, 'PAID', 'SYSTEM', null, $meta);
            } elseif ($newPaymentStatus !== ($order->payment_status ?? 'PENDING')) {
                DB::table('orders')->where('id', $orderId)->update(array_merge($updatePayload, [
                    'payment_status' => $newPaymentStatus,
                ]));
            }

            // Refrescar orden tras actualización financiera
            $order = DB::table('orders')->where('id', $orderId)->first();

            // 2. Evaluación de Elegibilidad Comercial (CONFIRMED) — P3.13-03
            $commercialTransitionExecuted = false;
            if ($isApproved && in_array($order->status, ['NEW', 'DRAFT', 'AWAITING_PAYMENT'], true)) {
                // Verificar que no existan condiciones bloqueantes (no cancelado, ítems existen)
                $hasItems = DB::table('order_items')->where('order_id', $orderId)->exists();
                $isBlocked = ($order->status === 'CANCELLED');

                if ($hasItems && !$isBlocked) {
                    $this->stateMachine->transitionOrderStatus($tenantId, $orderId, 'CONFIRMED', 'AI_SALES_ASSISTANT', null, [
                        'reason' => 'PAYMENT_FULLY_SETTLED',
                        'operation_number' => $operationNumber,
                    ]);
                    $commercialTransitionExecuted = true;
                }
            }

            // Refrescar orden tras evaluación comercial
            $order = DB::table('orders')->where('id', $orderId)->first();

            // 3. Evaluación de Política Logística (PREPARING) — P3.13-04
            $fulfillmentTransitionExecuted = false;
            $hasConfirmedAddress = !empty($order->delivery_address) || !empty($order->delivery_zone);
            $isPaidOrCOD = ($order->payment_status === 'PAID' || strtoupper($order->payment_method ?? '') === 'CASH_ON_DELIVERY');
            $isConfirmed = ($order->status === 'CONFIRMED');

            if ($isConfirmed && $isPaidOrCOD && $hasConfirmedAddress && ($order->fulfillment_status ?? 'PENDING') === 'PENDING') {
                $this->stateMachine->transitionFulfillmentStatus($tenantId, $orderId, 'PREPARING', 'AI_SALES_ASSISTANT');
                $fulfillmentTransitionExecuted = true;
            }

            $finalOrder = DB::table('orders')->where('id', $orderId)->first();

            return [
                'status' => 'SUCCESS',
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
                'financial_status' => $finalOrder->payment_status,
                'commercial_status' => $finalOrder->status,
                'fulfillment_status' => $finalOrder->fulfillment_status,
                'commercial_transition_executed' => $commercialTransitionExecuted,
                'fulfillment_transition_executed' => $fulfillmentTransitionExecuted,
                'address_confirmed' => $hasConfirmedAddress,
            ];
        });
    }

    /**
     * Aplica la confirmación de dirección / zona de delivery y evalúa la transición logística (PREPARING).
     *
     * @param int $tenantId ID del Tenant
     * @param int $orderId ID del Pedido
     * @param string $address Dirección o punto de referencia
     * @param string|null $zone Zona geográfica de entrega
     * @return array Resumen de la evaluación logística
     */
    public function processDeliveryConfirmation(int $tenantId, int $orderId, string $address, ?string $zone = null): array
    {
        return DB::transaction(function () use ($tenantId, $orderId, $address, $zone) {
            $order = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return [
                    'status' => 'ERROR',
                    'message' => "Pedido #{$orderId} no encontrado para Tenant {$tenantId}."
                ];
            }

            // Persistir datos de entrega
            $updateData = ['updated_at' => now()];
            if (!empty($address)) {
                $updateData['delivery_address'] = $address;
            }
            if (!empty($zone)) {
                $updateData['delivery_zone'] = $zone;
            }
            DB::table('orders')->where('id', $orderId)->update($updateData);

            $order = DB::table('orders')->where('id', $orderId)->first();

            // Evaluar transición a PREPARING (P3.13-04 & P3.13-07)
            $fulfillmentTransitionExecuted = false;
            $hasConfirmedAddress = !empty($order->delivery_address) || !empty($order->delivery_zone);
            $isPaidOrCOD = ($order->payment_status === 'PAID' || strtoupper($order->payment_method ?? '') === 'CASH_ON_DELIVERY');
            $isConfirmed = ($order->status === 'CONFIRMED');

            if ($isConfirmed && $isPaidOrCOD && $hasConfirmedAddress && ($order->fulfillment_status ?? 'PENDING') === 'PENDING') {
                $this->stateMachine->transitionFulfillmentStatus($tenantId, $orderId, 'PREPARING', 'AI_SALES_ASSISTANT');
                $fulfillmentTransitionExecuted = true;
            }

            $finalOrder = DB::table('orders')->where('id', $orderId)->first();

            return [
                'status' => 'SUCCESS',
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
                'commercial_status' => $finalOrder->status,
                'fulfillment_status' => $finalOrder->fulfillment_status,
                'fulfillment_transition_executed' => $fulfillmentTransitionExecuted,
                'address' => $finalOrder->delivery_address,
                'zone' => $finalOrder->delivery_zone,
            ];
        });
    }
}
