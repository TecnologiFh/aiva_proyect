<?php

namespace App\Modules\Orders\Mappers;

use App\Modules\Orders\Enums\CancellationDecision;

class CancellationResponseMapper
{
    /**
     * Mapea un resultado de cancelación de dominio hacia una respuesta determinística para WhatsApp (OI-03).
     */
    public static function mapToWhatsAppMessage(array $result): string
    {
        $orderNumber = $result['order_number'] ?? 'tu pedido';
        $decision = isset($result['decision']) ? CancellationDecision::from($result['decision']) : CancellationDecision::ORDER_NOT_FOUND;

        return match ($decision) {
            CancellationDecision::CANCELLED_SUCCESS => "✅ Tu pedido {$orderNumber} ha sido anulado correctamente. Si necesitas realizar un nuevo pedido, escríbeme.",
            CancellationDecision::ALREADY_CANCELLED => "ℹ️ El pedido {$orderNumber} ya se encontraba anulado previamente.",
            CancellationDecision::CANCELLED_REFUND_REVIEW => "✅ Tu pedido {$orderNumber} ha sido anulado. Como tu pago ya fue verificado, hemos registrado tu caso para la gestión y devolución del reembolso.",
            CancellationDecision::HUMAN_REVIEW_REQUIRED => "🟡 Tu solicitud de anulación del pedido {$orderNumber} ha sido derivada a revisión humana por nuestro equipo.",
            CancellationDecision::DENIED_ORDER_FINISHED => "❌ No es posible anular el pedido {$orderNumber} porque ya ha sido finalizado y entregado.",
            CancellationDecision::DENIED_POLICY => "❌ No fue posible procesar la anulación del pedido {$orderNumber} bajo la política actual.",
            CancellationDecision::ORDER_NOT_FOUND => "⚠️ No encontramos ningún pedido activo pendiente de anulación asociado a tu número.",
            CancellationDecision::CONCURRENT_OPERATION_RETRY => "⏳ Operación en curso. Por favor reintenta en un instante.",
        };
    }

    /**
     * Mapea un resultado de cancelación hacia una respuesta para el Dashboard / API.
     */
    public static function mapToDashboardMessage(array $result): string
    {
        $orderNumber = $result['order_number'] ?? 'el pedido';
        $decision = isset($result['decision']) ? CancellationDecision::from($result['decision']) : CancellationDecision::ORDER_NOT_FOUND;

        return match ($decision) {
            CancellationDecision::CANCELLED_SUCCESS => "Pedido #{$orderNumber} cancelado exitosamente.",
            CancellationDecision::ALREADY_CANCELLED => "El pedido #{$orderNumber} ya estaba cancelado (Acción Idempotente).",
            CancellationDecision::CANCELLED_REFUND_REVIEW => "Pedido #{$orderNumber} cancelado. Pago verificado (PAID) -> Caso de reembolso en revisión (REVIEW_REQUIRED).",
            CancellationDecision::HUMAN_REVIEW_REQUIRED => "Pedido #{$orderNumber} derivado a revisión humana de anulación.",
            CancellationDecision::DENIED_ORDER_FINISHED => "Imposible cancelar pedido #{$orderNumber}: Ya está finalizado/entregado.",
            CancellationDecision::DENIED_POLICY => "Cancelación denegada por política del tenant.",
            CancellationDecision::ORDER_NOT_FOUND => "Pedido no encontrado.",
            CancellationDecision::CONCURRENT_OPERATION_RETRY => "Operación concurrente detectada.",
        };
    }
}
