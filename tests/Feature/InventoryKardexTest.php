<?php

namespace Tests\Feature;

use App\Core\Enums\InventoryMovementType;
use App\Core\Enums\MovementReason;
use App\Core\Enums\PerformedByType;
use App\Modules\Inventory\Models\InventoryMovement;

use Tests\TestCase;

class InventoryKardexTest extends TestCase
{
    public function test_inventory_movement_kardex_casts_and_costs(): void
    {
        $movement = new InventoryMovement([
            'tenant_id' => 1,
            'stock_id' => 2,
            'movement_type' => InventoryMovementType::STOCK_OUT->value,
            'movement_reason' => MovementReason::SALE->value,
            'quantity' => 2.00,
            'previous_stock' => 10.00,
            'new_stock' => 8.00,
            'unit_cost' => 15.50,
            'total_cost' => 31.00,
            'performed_by_type' => PerformedByType::AI->value,
        ]);

        $this->assertEquals(InventoryMovementType::STOCK_OUT, $movement->movement_type);
        $this->assertEquals(MovementReason::SALE, $movement->movement_reason);
        $this->assertEquals(PerformedByType::AI, $movement->performed_by_type);
        $this->assertEquals(31.00, $movement->total_cost);
    }
}
