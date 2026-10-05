<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Enums\CancellationDecision;

class OrderCancellationPolicy
{
    /**
     * Evalúa si una orden puede ser cancelada comercialmente y qué flujo de devolución aplica.
     */
    public function evaluate(object $order): CancellationDecision
    {
        $status = strtoupper($order->status ?? 'NEW');
        $fulfillmentStatus = strtoupper($order->fulfillment_status ?? 'PENDING');
        $paymentStatus = strtoupper($order->payment_status ?? 'PENDING');

        // 1. Idempotencia: Si ya está cancelada
        if ($status === 'CANCELLED' || $fulfillmentStatus === 'CANCELLED') {
            return CancellationDecision::ALREADY_CANCELLED;
        }

        // 2. Pedidos ya entregados o completados
        if (in_array($status, ['COMPLETED', 'DELIVERED'], true) || in_array($fulfillmentStatus, ['DELIVERED', 'COMPLETED'], true)) {
            return CancellationDecision::DENIED_ORDER_FINISHED;
        }

        // 3. Caso C: Pedido Pagado (payment_status = PAID)
        if ($paymentStatus === 'PAID') {
            return CancellationDecision::CANCELLED_REFUND_REVIEW;
        }

        // 4. Caso B y A: Pedidos no pagados (PENDING / CONFIRMED / PREPARING)
        return CancellationDecision::CANCELLED_SUCCESS;
    }
}
