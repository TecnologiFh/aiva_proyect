<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\CancellationDecision;
use App\Modules\Orders\Policies\OrderCancellationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCancellationService
{
    protected OrderCancellationPolicy $policy;

    public function __construct(?OrderCancellationPolicy $policy = null)
    {
        $this->policy = $policy ?? new OrderCancellationPolicy();
        $this->ensureSchemaExists();
    }

    protected function ensureSchemaExists(): void
    {
        try {
            $cols = DB::select("SHOW COLUMNS FROM orders LIKE 'refund_status'");
            if (empty($cols)) {
                DB::statement("ALTER TABLE orders ADD COLUMN refund_status VARCHAR(40) DEFAULT 'NONE' AFTER payment_status");
                DB::statement("ALTER TABLE orders ADD COLUMN cancelled_at TIMESTAMP NULL AFTER updated_at");
                DB::statement("ALTER TABLE orders ADD COLUMN cancelled_by VARCHAR(50) NULL AFTER cancelled_at");
                DB::statement("ALTER TABLE orders ADD COLUMN cancellation_channel VARCHAR(50) NULL AFTER cancelled_by");
                DB::statement("ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER cancellation_channel");
            }
        } catch (\Throwable $e) {
            Log::warning("No se pudieron aprovisionar columnas de cancelación: " . $e->getMessage());
        }
    }

    /**
     * Cancelar un pedido de manera atómica, garantizando OI-01, OI-01.1, OI-02 y PI-01.
     * 
     * @param int $tenantId ID del Tenant
     * @param int|null $orderId ID del pedido (opcional si se especifica customerPhone)
     * @param string|null $customerPhone Teléfono del cliente para buscar orden abierta
     * @param string $cancelledBy Atribución ('CUSTOMER', 'OPERATOR', 'SYSTEM')
     * @param string $channel Canal ('WHATSAPP_AI', 'DASHBOARD', 'OPERATOR', 'API', 'SYSTEM')
     * @param string|null $reason Motivo explícito
     * @return array DTO Canónico del Dominio
     */
    public function cancelOrder(
        int $tenantId,
        ?int $orderId = null,
        ?string $customerPhone = null,
        string $cancelledBy = 'CUSTOMER',
        string $channel = 'WHATSAPP_AI',
        ?string $reason = 'Customer requested cancellation'
    ): array {
        $cleanPhone = $customerPhone ? preg_replace('/[^0-9]/', '', $customerPhone) : null;

        // 1. Ejecutar Transacción Atómica con Aislamiento SELECT FOR UPDATE
        $domainResult = DB::transaction(function () use ($tenantId, $orderId, $cleanPhone, $cancelledBy, $channel, $reason) {
            // Buscar la orden activa
            $query = DB::table('orders')->where('tenant_id', $tenantId);

            if ($orderId) {
                $query->where('id', $orderId);
            } elseif ($cleanPhone) {
                \App\Modules\Core\Identity\PhoneNormalizer::applyCanonicalQuery($query, $tenantId, $cleanPhone)
                    ->whereNotIn('status', ['CANCELLED', 'DELIVERED', 'COMPLETED'])
                    ->orderByDesc('id');
            } else {
                return [
                    'success' => false,
                    'decision' => CancellationDecision::ORDER_NOT_FOUND->value,
                    'order_id' => null,
                    'order_number' => null,
                    'already_cancelled' => false,
                ];
            }

            // Aislamiento FOR UPDATE
            $order = $query->lockForUpdate()->first();

            if (!$order) {
                return [
                    'success' => false,
                    'decision' => CancellationDecision::ORDER_NOT_FOUND->value,
                    'order_id' => null,
                    'order_number' => null,
                    'already_cancelled' => false,
                ];
            }

            // 2. Evaluación de Idempotencia (OI-02)
            if ($order->status === 'CANCELLED' || ($order->fulfillment_status ?? '') === 'CANCELLED') {
                return [
                    'success' => true,
                    'decision' => CancellationDecision::ALREADY_CANCELLED->value,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'refund_status' => $order->refund_status ?? 'NONE',
                    'already_cancelled' => true,
                ];
            }

            // 3. Evaluación de Política de Cancelación
            $decision = $this->policy->evaluate($order);

            if ($decision === CancellationDecision::DENIED_ORDER_FINISHED) {
                return [
                    'success' => false,
                    'decision' => CancellationDecision::DENIED_ORDER_FINISHED->value,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'already_cancelled' => false,
                ];
            }

            // 4. Determinar Estado de Reembolso preservando PI-01
            $newRefundStatus = $order->refund_status ?? 'NONE';
            if ($order->payment_status === 'PAID') {
                $newRefundStatus = 'REVIEW_REQUIRED';
            }

            // 5. Mutación Persistente de la Orden
            $updateData = [
                'status' => 'CANCELLED',
                'fulfillment_status' => 'CANCELLED',
                'refund_status' => $newRefundStatus,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancellation_channel' => $channel,
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ];

            DB::table('orders')->where('id', $order->id)->update($updateData);

            // 6. Registro en Event Store Append-Only
            DB::table('order_events')->insert([
                'order_id' => $order->id,
                'tenant_id' => $tenantId,
                'event_type' => 'ORDER_CANCELLED',
                'previous_state' => $order->status,
                'new_state' => 'CANCELLED',
                'actor_type' => $cancelledBy,
                'created_at' => now(),
            ]);

            // 7. Liberación de Reserva de Inventario (si existe reserva activa)
            try {
                if (class_exists('\App\Modules\Inventory\Services\DailyStockManager')) {
                    $stockMgr = new \App\Modules\Inventory\Services\DailyStockManager();
                    $reservation = DB::table('stock_reservations')->where('tenant_id', $tenantId)->where('order_id', $order->id)->where('status', 'RESERVED')->first();
                    if ($reservation) {
                        $stockMgr->releaseReservation($tenantId, $reservation->id, 'CANCELLED');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("No se pudo liberar reserva de stock para orden #{$order->id}: " . $e->getMessage());
            }

            return [
                'success' => true,
                'decision' => $decision->value,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'refund_status' => $newRefundStatus,
                'already_cancelled' => false,
            ];
        });

        // 8. Efectos Secundarios POST-COMMIT (Asíncronos, fuera de la transacción)
        if ($domainResult['success'] && !$domainResult['already_cancelled']) {
            try {
                if (class_exists('\App\Modules\CRM\Services\Customer360Service') && $cleanPhone) {
                    $crmService = new \App\Modules\CRM\Services\Customer360Service();
                    $customer = $crmService->getOrCreateCustomer($tenantId, $cleanPhone, 'Cliente');
                    $crmService->recalculateCustomerMetrics($tenantId, $customer->id);
                }
            } catch (\Throwable $e) {
                Log::warning("Error recalculando métricas CRM post-commit: " . $e->getMessage());
            }
        }

        return $domainResult;
    }
}
