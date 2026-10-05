<?php

namespace App\Modules\Analytics\DTOs;

class SalesMetricsDTO
{
    public function __construct(
        public readonly float $totalSalesAmount,
        public readonly int $totalOrdersCount,
        public readonly float $averageTicket,
        public readonly array $salesByPaymentMethod,
        public readonly array $salesByOrderType,
        public readonly array $topProducts
    ) {}

    public function toArray(): array
    {
        return [
            'total_sales_amount' => $this->totalSalesAmount,
            'total_orders_count' => $this->totalOrdersCount,
            'average_ticket' => $this->averageTicket,
            'sales_by_payment_method' => $this->salesByPaymentMethod,
            'sales_by_order_type' => $this->salesByOrderType,
            'top_products' => $this->topProducts,
        ];
    }
}
