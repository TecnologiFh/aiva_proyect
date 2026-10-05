<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\OrderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OrderStateMachineEngine
{
    // MÁQUINAS DE ESTADO DEFINITIVAS DE LA ARQUITECTURA 4D (CORE CONTRACT CONGELADO)
    public const ALLOWED_ORDER_STATUSES = ['NEW', 'CONFIRMED', 'COMPLETED', 'CANCELLED'];
    public const ALLOWED_FULFILLMENT_STATUSES = ['PENDING', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERED'];
    public const ALLOWED_PAYMENT_STATUSES = ['PENDING', 'PAID', 'FAILED', 'REFUNDED'];
    public const ALLOWED_ATTENTION_STATUSES = ['AI_ACTIVE', 'HUMAN_HANDOVER', 'WAITING_CUSTOMER', 'WAITING_BUSINESS', 'RESOLVED'];

    /**
     * Transicionar el estado comercial del pedido (ORDER_STATUS).
     */
    public function transitionOrderStatus(int $tenantId, int $orderId, string $newStatus, string $actorType = 'SYSTEM', ?int $actorId = null, array $meta = []): bool
    {
        if (!in_array($newStatus, self::ALLOWED_ORDER_STATUSES, true)) {
            throw new \InvalidArgumentException("Estado de pedido inválido: '{$newStatus}'. Estados permitidos: " . implode(', ', self::ALLOWED_ORDER_STATUSES));
        }

        return DB::transaction(function () use ($tenantId, $orderId, $newStatus, $actorType, $actorId, $meta) {
            $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->first();
            if (!$order) {
                return false;
            }

            $previousState = $order->status;
            if ($previousState === $newStatus) {
                return true;
            }

            DB::table('orders')->where('id', $orderId)->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

            OrderEvent::create([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'event_type' => 'ORDER_STATUS_CHANGED',
                'previous_state' => $previousState,
                'new_state' => $newStatus,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata' => $meta,
                'created_at' => Carbon::now(),
            ]);

            return true;
        });
    }

    /**
     * Transicionar el estado de ejecución física (FULFILLMENT_STATUS).
     */
    public function transitionFulfillmentStatus(int $tenantId, int $orderId, string $newFulfillmentStatus, string $actorType = 'SYSTEM', ?int $actorId = null): bool
    {
        if (!in_array($newFulfillmentStatus, self::ALLOWED_FULFILLMENT_STATUSES, true)) {
            throw new \InvalidArgumentException("Estado de fulfillment inválido: '{$newFulfillmentStatus}'. Estados permitidos: " . implode(', ', self::ALLOWED_FULFILLMENT_STATUSES));
        }

        return DB::transaction(function () use ($tenantId, $orderId, $newFulfillmentStatus, $actorType, $actorId) {
            $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->first();
            if (!$order) {
                return false;
            }

            $previousState = $order->fulfillment_status ?? 'PENDING';
            if ($previousState === $newFulfillmentStatus) {
                return true;
            }

            DB::table('orders')->where('id', $orderId)->update([
                'fulfillment_status' => $newFulfillmentStatus,
                'updated_at' => now(),
            ]);

            OrderEvent::create([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'event_type' => 'FULFILLMENT_STATUS_CHANGED',
                'previous_state' => $previousState,
                'new_state' => $newFulfillmentStatus,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'created_at' => Carbon::now(),
            ]);

            return true;
        });
    }

    /**
     * Transicionar el estado financiero del pedido (PAYMENT_STATUS).
     */
    public function transitionPaymentStatus(int $tenantId, int $orderId, string $newPaymentStatus, string $actorType = 'SYSTEM', ?int $actorId = null, array $meta = []): bool
    {
        if (!in_array($newPaymentStatus, self::ALLOWED_PAYMENT_STATUSES, true)) {
            throw new \InvalidArgumentException("Estado de pago inválido: '{$newPaymentStatus}'. Estados permitidos: " . implode(', ', self::ALLOWED_PAYMENT_STATUSES));
        }

        // PAY33 Security Boundary Gate: Impedir cambios directos a PAID sin PaymentAuthorizationService
        if ($newPaymentStatus === 'PAID') {
            $authToken = $meta['authorization_token'] ?? null;
            if (empty($authToken) || !str_starts_with($authToken, 'PAY33_AUTH_TOKEN_')) {
                throw new \RuntimeException("SECURITY BOUNDARY VIOLATION (PAY33): Transición directa a 'PAID' rechazada. Los cambios de estado financiero solo pueden ser ejecutados a través de PaymentAuthorizationService.");
            }
        }

        return DB::transaction(function () use ($tenantId, $orderId, $newPaymentStatus, $actorType, $actorId, $meta) {
            $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->first();
            if (!$order) {
                return false;
            }

            $previousState = $order->payment_status ?? 'PENDING';
            if ($previousState === $newPaymentStatus) {
                return true;
            }

            DB::table('orders')->where('id', $orderId)->update([
                'payment_status' => $newPaymentStatus,
                'updated_at' => now(),
            ]);

            OrderEvent::create([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'event_type' => 'PAYMENT_STATUS_CHANGED',
                'previous_state' => $previousState,
                'new_state' => $newPaymentStatus,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata' => $meta,
                'created_at' => Carbon::now(),
            ]);

            return true;
        });
    }
}
