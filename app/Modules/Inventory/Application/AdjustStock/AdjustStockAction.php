<?php

namespace App\Modules\Inventory\Application\AdjustStock;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\ProductStatus;
use App\Core\Events\StockAdjusted;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Inventory\Jobs\DebouncedCompileBusinessBrainJob;
use App\Modules\Inventory\Repositories\InventoryMovementRepository;
use App\Modules\Inventory\Repositories\InventoryStockRepository;
use Illuminate\Support\Facades\DB;

class AdjustStockAction
{
    public function __construct(
        private readonly InventoryStockRepository $stockRepository,
        private readonly InventoryMovementRepository $movementRepository,
        private readonly ProductRepository $productRepository,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(AdjustStockInput $input): AdjustStockResult
    {
        $stock = $this->stockRepository->findOrCreateForProduct($input->tenantId, $input->productId);

        $previousStock = $stock->current_stock;
        $newStock = $previousStock;

        if (strtoupper($input->movementType) === 'STOCK_IN') {
            $newStock = $previousStock + $input->quantity;
        } else {
            $newStock = max(0.00, $previousStock - $input->quantity);
        }

        $totalCost = round($input->unitCost * $input->quantity, 2);
        $isOutOfStock = ($newStock <= 0.00);

        DB::transaction(function () use ($stock, $input, $previousStock, $newStock, $totalCost, $isOutOfStock) {
            $stock->update([
                'current_stock' => $newStock,
                'is_out_of_stock' => $isOutOfStock,
            ]);

            $this->movementRepository->create([
                'tenant_id' => $input->tenantId,
                'stock_id' => $stock->id,
                'movement_type' => $input->movementType,
                'movement_reason' => $input->movementReason,
                'quantity' => $input->quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'unit_cost' => $input->unitCost,
                'total_cost' => $totalCost,
                'reference_type' => 'MANUAL',
                'performed_by_type' => $input->performedByType,
                'performed_by_id' => $input->performedById,
                'notes' => $input->notes,
            ]);

            // Actualizar estado de disponibilidad en catálogo
            $product = $this->productRepository->findById($input->productId);
            if ($product) {
                $product->update([
                    'status' => $isOutOfStock ? ProductStatus::OUT_OF_STOCK->value : ProductStatus::ACTIVE->value
                ]);
            }
        });

        // Evento de Dominio & Recompilación Debounced de la IA
        $this->eventBus->dispatch(new StockAdjusted($stock, $input->quantity, $input->movementReason));
        DebouncedCompileBusinessBrainJob::dispatch($input->tenantId);

        return new AdjustStockResult($stock->refresh(), $previousStock, $newStock);
    }
}
