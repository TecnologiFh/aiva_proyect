<?php

namespace App\Modules\Inventory\Listeners;

use App\Core\Events\OrderConfirmed;
use App\Modules\Inventory\Application\DeductStockForOrder\DeductStockForOrderAction;
use App\Modules\Inventory\Application\DeductStockForOrder\DeductStockForOrderInput;

class DeductStockOnOrderConfirmed
{
    public function __construct(
        private readonly DeductStockForOrderAction $deductStockAction
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $this->deductStockAction->execute(new DeductStockForOrderInput(
            orderUuid: $event->order->uuid
        ));
    }
}
