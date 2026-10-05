<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Customer;
use Illuminate\Support\Facades\DB;

class Customer360Service
{
    /**
     * Obtener o registrar automáticamente un cliente asegurando aislamiento por Tenant.
     */
    public function getOrCreateCustomer(int $tenantId, string $phone, string $name = 'Cliente WhatsApp'): Customer
    {
        $phone = trim($phone);

        $customer = Customer::where('tenant_id', $tenantId)
            ->where('phone', $phone)
            ->first();

        if (!$customer) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'phone' => $phone,
                'name' => $name,
                'preferred_channel' => 'WHATSAPP',
                'tag' => 'NUEVO',
            ]);
        }

        return $customer;
    }

    /**
     * Recalcular las métricas consolidadas del cliente (total pedidos, valor acumulado LTV, tag VIP).
     */
    public function recalculateCustomerMetrics(int $tenantId, int $customerId): Customer
    {
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $orders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('customer_phone', $customer->phone)
            ->get();

        $totalOrders = $orders->count();
        $totalSpent = (float) $orders->sum('total_amount');
        $lastOrder = $orders->sortByDesc('id')->first();

        // Determinar canal preferido
        $whatsappCount = $orders->where('channel', 'WHATSAPP')->count();
        $posCount = $orders->where('channel', 'POS')->count();
        $preferredChannel = $posCount > $whatsappCount ? 'POS' : 'WHATSAPP';

        // Determinar Tag de Fidelidad
        $tag = 'NUEVO';
        if ($totalOrders >= 5 || $totalSpent >= 200.00) {
            $tag = 'VIP';
        } elseif ($totalOrders >= 2) {
            $tag = 'FRECUENTE';
        }

        $customer->update([
            'total_orders_count' => $totalOrders,
            'total_spent_amount' => $totalSpent,
            'preferred_channel' => $preferredChannel,
            'tag' => $tag,
            'last_order_at' => $lastOrder ? $lastOrder->created_at : null,
        ]);

        return $customer;
    }

    /**
     * Obtener el perfil consolidado Customer 360 con historial de compras e interacción.
     */
    public function getCustomer360Profile(int $tenantId, int $customerId): array
    {
        $customer = $this->recalculateCustomerMetrics($tenantId, $customerId);

        $orderHistory = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('customer_phone', $customer->phone)
            ->orderByDesc('id')
            ->get();

        return [
            'customer' => $customer,
            'order_history' => $orderHistory,
            'ltv_metrics' => [
                'total_orders' => $customer->total_orders_count,
                'total_spent' => (float)$customer->total_spent_amount,
                'average_order_value' => $customer->total_orders_count > 0 ? (float)($customer->total_spent_amount / $customer->total_orders_count) : 0.00,
                'preferred_channel' => $customer->preferred_channel,
                'loyalty_tag' => $customer->tag,
            ]
        ];
    }
}
