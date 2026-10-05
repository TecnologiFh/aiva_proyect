<?php

namespace Tests\Feature;

use App\Modules\Analytics\DTOs\SalesMetricsDTO;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
{
    public function test_sales_metrics_dto_structure(): void
    {
        $dto = new SalesMetricsDTO(
            totalSalesAmount: 12500.50,
            totalOrdersCount: 150,
            averageTicket: 83.34,
            salesByPaymentMethod: ['YAPE_PLIN' => ['count' => 90, 'total' => 7500.00]],
            salesByOrderType: ['DELIVERY' => ['count' => 120, 'total' => 10000.00]],
            topProducts: [['product_name' => 'Pizza Familiar', 'total_qty' => 80, 'total_revenue' => 4000.00]]
        );

        $array = $dto->toArray();

        $this->assertEquals(12500.50, $array['total_sales_amount']);
        $this->assertEquals(150, $array['total_orders_count']);
        $this->assertEquals(83.34, $array['average_ticket']);
        $this->assertArrayHasKey('YAPE_PLIN', $array['sales_by_payment_method']);
    }
}
