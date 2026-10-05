<?php

namespace Tests\Feature;

use App\Modules\Inventory\Models\InventoryStock;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    public function test_inventory_stock_calculates_available_stock(): void
    {
        $stock = new InventoryStock([
            'tenant_id' => 1,
            'branch_id' => 1,
            'product_id' => 5,
            'current_stock' => 10.00,
            'reserved_stock' => 3.00,
            'minimum_stock' => 2.00,
        ]);

        $this->assertEquals(10.00, $stock->current_stock);
        $this->assertEquals(3.00, $stock->reserved_stock);
        $this->assertEquals(7.00, $stock->available_stock);
    }
}
