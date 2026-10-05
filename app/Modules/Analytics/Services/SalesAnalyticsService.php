<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\DTOs\SalesMetricsDTO;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

class SalesAnalyticsService
{
    public function getSalesMetrics(int $tenantId, ?string $startDate = null, ?string $endDate = null): SalesMetricsDTO
    {
        $query = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['CONFIRMED', 'PREPARING', 'READY', 'DELIVERED']);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalSalesAmount = (float) $query->sum('total_amount');
        $totalOrdersCount = (int) $query->count();
        $averageTicket = $totalOrdersCount > 0 ? round($totalSalesAmount / $totalOrdersCount, 2) : 0.00;

        // Distribución por Método de Pago
        $salesByPaymentMethod = Order::where('tenant_id', $tenantId)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn($r) => [$r->payment_method->value => [
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ]])->toArray();

        // Distribución por Modalidad de Pedido
        $salesByOrderType = Order::where('tenant_id', $tenantId)
            ->selectRaw('order_type, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('order_type')
            ->get()
            ->mapWithKeys(fn($r) => [$r->order_type->value => [
                'count' => (int) $r->count,
                'total' => (float) $r->total,
            ]])->toArray();

        // Top 5 Productos Más Vendidos
        $topProducts = OrderItem::where('tenant_id', $tenantId)
            ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'product_name' => $p->product_name,
                'total_qty' => (int) $p->total_qty,
                'total_revenue' => (float) $p->total_revenue,
            ])->toArray();

        return new SalesMetricsDTO(
            totalSalesAmount: round($totalSalesAmount, 2),
            totalOrdersCount: $totalOrdersCount,
            averageTicket: $averageTicket,
            salesByPaymentMethod: $salesByPaymentMethod,
            salesByOrderType: $salesByOrderType,
            topProducts: $topProducts
        );
    }
}
