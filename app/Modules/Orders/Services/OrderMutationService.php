<?php

namespace App\Modules\Orders\Services;

use Illuminate\Support\Facades\DB;

class OrderMutationService
{
    /**
     * Mutación determinística y recalculada del total de una orden activa desde el backend.
     */
    public function updateOrderTotal(int $tenantId, int $orderId, float $newTotalAmount, string $reason = 'CUSTOMER_REQUEST'): array
    {
        return DB::transaction(function() use ($tenantId, $orderId, $newTotalAmount, $reason) {
            $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                return ['status' => 'ERROR', 'message' => 'Orden no encontrada.'];
            }

            if ($order->payment_status === 'PAID') {
                return ['status' => 'BLOCKED', 'message' => 'No se puede modificar el total de una orden ya pagada.'];
            }

            $oldTotal = (float)$order->total_amount;
            $newTotal = round(max(0.00, $newTotalAmount), 2);

            $affectsFinancials = (abs($oldTotal - $newTotal) > 0.001);
            $currentRev = isset($order->payment_revision) ? (int)$order->payment_revision : 1;
            $newRev = $affectsFinancials ? ($currentRev + 1) : $currentRev;

            DB::table('orders')->where('id', $orderId)->update([
                'total_amount' => $newTotal,
                'payment_revision' => $newRev,
                'updated_at' => now(),
            ]);

            return [
                'status' => 'SUCCESS',
                'order_id' => $orderId,
                'order_number' => $order->order_number,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'reason' => $reason,
            ];
        });
    }
}
