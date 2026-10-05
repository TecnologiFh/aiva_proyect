<?php

namespace App\Modules\AI\Services\OperationalTools;

use Illuminate\Support\Facades\DB;

class GetOrderStatusTool
{
    /**
     * Consultar el estado detallado en 4 dimensiones de un pedido y su trazabilidad inmutable.
     */
    public function execute(int $tenantId, ?string $orderNumber = null, ?string $customerPhone = null): array
    {
        $query = DB::table('orders')->where('tenant_id', $tenantId);

        if (!empty($orderNumber)) {
            $query->where('order_number', trim($orderNumber));
        } elseif (!empty($customerPhone)) {
            $query->where('customer_phone', trim($customerPhone))->orderByDesc('id');
        } else {
            return [
                'tenant_id' => $tenantId,
                'found' => false,
                'error' => 'Se requiere order_number o customer_phone para consultar el estado del pedido.',
            ];
        }

        $order = $query->first();

        if (!$order) {
            return [
                'tenant_id' => $tenantId,
                'found' => false,
                'message' => 'No se encontró ningún pedido registrado con los datos proporcionados.',
            ];
        }

        // Trazabilidad desde order_events
        $events = DB::table('order_events')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $timeline = [];
        foreach ($events as $ev) {
            $timeline[] = [
                'event_type' => $ev->event_type,
                'previous_state' => $ev->previous_state,
                'new_state' => $ev->new_state,
                'actor' => $ev->actor_type,
                'timestamp' => $ev->created_at,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'found' => true,
            'order' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_status' => $order->fulfillment_status ?? 'PENDING',
                'delivery_zone' => $order->delivery_zone ?? 'General',
                'delivery_address' => $order->delivery_address,
                'total_amount' => (float)$order->total_amount,
            ],
            'timeline' => $timeline,
        ];
    }
}
