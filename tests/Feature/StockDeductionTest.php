<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\InventoryStock;
use Tests\TestCase;

class StockDeductionTest extends TestCase
{
    public function test_stock_depletion_detects_out_of_stock_state(): void
    {
        $stock = new InventoryStock([
            'tenant_id' => 1,
            'product_id' => 8,
            'current_stock' => 0.00,
            'reserved_stock' => 0.00,
            'is_out_of_stock' => true,
        ]);

        $this->assertEquals(0.00, $stock->current_stock);
        $this->assertTrue($stock->is_out_of_stock);
        $this->assertEquals(0.00, $stock->available_stock);
    }
}
