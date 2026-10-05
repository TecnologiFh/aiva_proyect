<?php

namespace App\Modules\Inventory\Application\ReserveStockForOrder;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\InventoryMovementType;
use App\Core\Enums\MovementReason;
use App\Core\Enums\PerformedByType;
use App\Core\Events\StockReserved;
use App\Modules\Inventory\Repositories\InventoryMovementRepository;
use App\Modules\Inventory\Repositories\InventoryStockRepository;
use App\Modules\Orders\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ReserveStockForOrderAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InventoryStockRepository $stockRepository,
        private readonly InventoryMovementRepository $movementRepository,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(ReserveStockForOrderInput $input): ReserveStockForOrderResult
    {
        $order = $this->orderRepository->findByUuid($input->orderUuid);
        if (!$order) {
            throw new ModelNotFoundException('Pedido no encontrado.');
        }

        $reservedCount = 0;

        DB::transaction(function () use ($order, &$reservedCount) {
            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $stock = $this->stockRepository->findOrCreateForProduct($order->tenant_id, $item->product_id);
                if (!$stock->track_inventory) {
                    continue;
                }

                // Bloqueo transaccional
                $stockForUpdate = $this->stockRepository->findForUpdate($order->tenant_id, $item->product_id);
                if (!$stockForUpdate) {
                    continue;
                }

                $prevStock = $stockForUpdate->current_stock;
                $newReserved = $stockForUpdate->reserved_stock + $item->quantity;

                $stockForUpdate->update([
                    'reserved_stock' => $newReserved,
                ]);

                $this->movementRepository->create([
                    'tenant_id' => $order->tenant_id,
                    'stock_id' => $stockForUpdate->id,
                    'movement_type' => InventoryMovementType::RESERVATION->value,
                    'movement_reason' => MovementReason::RESERVATION->value,
                    'quantity' => $item->quantity,
                    'previous_stock' => $prevStock,
                    'new_stock' => $prevStock,
                    'unit_cost' => (float) $item->unit_price,
                    'total_cost' => (float) $item->subtotal,
                    'reference_type' => 'ORDER',
                    'reference_id' => $order->order_number,
                    'performed_by_type' => PerformedByType::AI->value,
                    'notes' => 'Reserva automática por pedido de IA',
                ]);

                $reservedCount++;
                $this->eventBus->dispatch(new StockReserved($stockForUpdate, (float) $item->quantity));
            }
        });

        return new ReserveStockForOrderResult(reserved: true, reservedItemsCount: $reservedCount);
    }
}
