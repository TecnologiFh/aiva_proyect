<?php

namespace App\Modules\Inventory\Application\DeductStockForOrder;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\InventoryMovementType;
use App\Core\Enums\MovementReason;
use App\Core\Enums\PerformedByType;
use App\Core\Enums\ProductStatus;
use App\Core\Events\LowStockDetected;
use App\Core\Events\OutOfStockDetected;
use App\Core\Events\StockDeducted;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Inventory\Jobs\DebouncedCompileBusinessBrainJob;
use App\Modules\Inventory\Repositories\InventoryMovementRepository;
use App\Modules\Inventory\Repositories\InventoryStockRepository;
use App\Modules\Orders\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class DeductStockForOrderAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InventoryStockRepository $stockRepository,
        private readonly InventoryMovementRepository $movementRepository,
        private readonly ProductRepository $productRepository,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(DeductStockForOrderInput $input): DeductStockForOrderResult
    {
        $order = $this->orderRepository->findByUuid($input->orderUuid);
        if (!$order) {
            throw new ModelNotFoundException('Pedido no encontrado.');
        }

        $deductedCount = 0;
        $shouldRecompileBrain = false;

        DB::transaction(function () use ($order, &$deductedCount, &$shouldRecompileBrain) {
            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $stock = $this->stockRepository->findOrCreateForProduct($order->tenant_id, $item->product_id);
                if (!$stock->track_inventory) {
                    continue;
                }

                $stockForUpdate = $this->stockRepository->findForUpdate($order->tenant_id, $item->product_id);
                if (!$stockForUpdate) {
                    continue;
                }

                $prevStock = $stockForUpdate->current_stock;
                $newReserved = max(0.00, $stockForUpdate->reserved_stock - $item->quantity);
                $newCurrent = max(0.00, $stockForUpdate->current_stock - $item->quantity);
                $isOutOfStock = ($newCurrent <= 0.00);

                $stockForUpdate->update([
                    'reserved_stock' => $newReserved,
                    'current_stock' => $newCurrent,
                    'is_out_of_stock' => $isOutOfStock,
                ]);

                // Registrar movimiento STOCK_OUT con auditoría financiera (unit_cost, total_cost)
                $this->movementRepository->create([
                    'tenant_id' => $order->tenant_id,
                    'stock_id' => $stockForUpdate->id,
                    'movement_type' => InventoryMovementType::STOCK_OUT->value,
                    'movement_reason' => MovementReason::SALE->value,
                    'quantity' => $item->quantity,
                    'previous_stock' => $prevStock,
                    'new_stock' => $newCurrent,
                    'unit_cost' => (float) $item->unit_price,
                    'total_cost' => (float) $item->subtotal,
                    'reference_type' => 'ORDER',
                    'reference_id' => $order->order_number,
                    'performed_by_type' => PerformedByType::SYSTEM->value,
                    'notes' => "Descuento por venta confirmada en pedido {$order->order_number}",
                ]);

                $deductedCount++;
                $this->eventBus->dispatch(new StockDeducted($stockForUpdate, (float) $item->quantity));

                // Alertas de Stock Mínimo y Agotado
                if ($newCurrent <= $stockForUpdate->minimum_stock) {
                    $this->eventBus->dispatch(new LowStockDetected($stockForUpdate));
                }

                if ($isOutOfStock) {
                    $product = $this->productRepository->findById($item->product_id);
                    if ($product) {
                        $product->update(['status' => ProductStatus::OUT_OF_STOCK->value]);
                    }
                    $shouldRecompileBrain = true;
                    $this->eventBus->dispatch(new OutOfStockDetected($stockForUpdate));
                }
            }
        });

        // Recompilación Debounced en Redis del Business Brain si se agoto un producto
        if ($shouldRecompileBrain) {
            DebouncedCompileBusinessBrainJob::dispatch($order->tenant_id);
        }

        return new DeductStockForOrderResult(deducted: true, deductedItemsCount: $deductedCount);
    }
}
