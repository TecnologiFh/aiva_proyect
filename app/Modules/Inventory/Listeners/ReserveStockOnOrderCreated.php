<?php

namespace App\Modules\Inventory\Listeners;

use App\Core\Events\OrderCreated;
use App\Modules\Inventory\Application\ReserveStockForOrder\ReserveStockForOrderAction;
use App\Modules\Inventory\Application\ReserveStockForOrder\ReserveStockForOrderInput;

class ReserveStockOnOrderCreated
{
    public function __construct(
        private readonly ReserveStockForOrderAction $reserveStockAction
    ) {}

    public function handle(OrderCreated $event): void
    {
        $this->reserveStockAction->execute(new ReserveStockForOrderInput(
            orderUuid: $event->order->uuid
        ));
    }
}
