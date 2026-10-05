<?php

namespace App\Modules\Messaging\Tools;

use App\Modules\Inventory\Services\DailyStockManager;
use Illuminate\Support\Facades\DB;

class GetProductAvailabilityTool
{
    protected DailyStockManager $stockManager;

    public function __construct(DailyStockManager $stockManager)
    {
        $this->stockManager = $stockManager;
    }

    /**
     * Consultar la disponibilidad transaccional de un producto para el Tenant (Sin que la IA calcule stock).
     */
    public function execute(int $tenantId, string $productQuery): array
    {
        $product = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($productQuery) {
                $q->where('name', 'LIKE', '%' . $productQuery . '%')
                  ->orWhere('sku', $productQuery);
            })
            ->first();

        if (!$product) {
            return [
                'found' => false,
                'status' => 'NOT_FOUND',
                'message' => "Producto '{$productQuery}' no encontrado en el catálogo oficial de la empresa."
            ];
        }

        // Si el control de stock está desactivado, el producto es ILIMITADO
        if (isset($product->stock_control_enabled) && !$product->stock_control_enabled) {
            return [
                'found' => true,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => (float) ($product->base_price ?? $product->price ?? 0),
                'stock_control' => 'UNLIMITED',
                'available_stock' => 9999,
                'status' => 'AVAILABLE',
                'message' => "Producto disponible sin límite de stock."
            ];
        }

        // Consultar stock diario real del Core
        $dailyStock = $this->stockManager->getOrInitializeDailyStock($tenantId, $product->id);

        $available = (int) $dailyStock->available_stock;
        $status = $available > 0 ? 'AVAILABLE' : 'SOLD_OUT';

        return [
            'found' => true,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => (float) ($product->base_price ?? $product->price ?? 0),
            'stock_control' => 'DAILY_STOCK',
            'initial_stock' => (int) $dailyStock->initial_stock,
            'available_stock' => $available,
            'reserved_stock' => (int) $dailyStock->reserved_stock,
            'sold_stock' => (int) $dailyStock->sold_stock,
            'status' => $status,
            'message' => $status === 'AVAILABLE'
                ? "Disponible: Quedan {$available} unidades para el día de hoy."
                : "Agotado: El producto '{$product->name}' se encuentra agotado por el día de hoy."
        ];
    }
}
