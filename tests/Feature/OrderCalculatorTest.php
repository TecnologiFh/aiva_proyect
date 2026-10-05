<?php

namespace Tests\Feature;

use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Orders\Services\OrderCalculator;

use Tests\TestCase;

class OrderCalculatorTest extends TestCase
{
    public function test_order_calculator_calculates_subtotal_and_options(): void
    {
        $settingRepo = $this->createMock(BusinessSettingRepository::class);
        $settingRepo->method('getByTenantId')->willReturn(null);

        $calculator = new OrderCalculator($settingRepo);

        $items = [
            [
                'product_id' => 1,
                'product_uuid' => 'prod-uuid-1',
                'product_name' => 'Pizza Familiar',
                'base_price' => 35.00,
                'quantity' => 2,
                'selected_options' => [
                    ['option_name' => 'Salsa', 'value_name' => 'BBQ', 'price_modifier' => 3.00]
                ],
            ]
        ];

        $calculated = $calculator->calculate(
            tenantId: 1,
            items: $items,
            orderType: 'PICKUP'
        );

        // Subtotal = 2 * (35 + 3) = 76.00
        $this->assertEquals(76.00, $calculated['subtotal']);
        $this->assertEquals(0.00, $calculated['delivery_fee']);
        $this->assertEquals(76.00, $calculated['total_amount']);

        $optionsSnapshot = $calculated['items'][0]['options_snapshot'];
        $this->assertEquals(35.00, $optionsSnapshot['base_price']);
        $this->assertEquals(38.00, $optionsSnapshot['calculated_unit_price']);
    }
}
