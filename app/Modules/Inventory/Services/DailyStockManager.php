<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DailyStockManager
{
    /**
     * Verificar y garantizar la invariante matemática del stock:
     * initial_stock === available_stock + reserved_stock + sold_stock
     */
    public function assertStockInvariant($stock): void
    {
        $initial = (int) $stock->initial_stock;
        $available = (int) $stock->available_stock;
        $reserved = (int) $stock->reserved_stock;
        $sold = (int) $stock->sold_stock;

        if ($initial !== ($available + $reserved + $sold)) {
            throw new RuntimeException("Invariante de Stock Rota para producto ID {$stock->product_id}: Initial ({$initial}) != Available ({$available}) + Reserved ({$reserved}) + Sold ({$sold})");
        }
    }

    /**
     * Obtener o inicializar el stock diario de un producto para una fecha determinada.
     */
    public function getOrInitializeDailyStock(int $tenantId, int $productId, ?string $date = null, int $defaultInitialStock = 20)
    {
        $targetDate = $date ?? date('Y-m-d');

        return DB::transaction(function () use ($tenantId, $productId, $targetDate, $defaultInitialStock) {
            $stock = DB::table('daily_stocks')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('date', $targetDate)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                // Verificar si el producto tiene stock_control_enabled
                $prod = DB::table('products')->where('tenant_id', $tenantId)->where('id', $productId)->first();
                $initial = (int) ($prod->stock ?? $defaultInitialStock);

                $id = DB::table('daily_stocks')->insertGetId([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'date' => $targetDate,
                    'initial_stock' => $initial,
                    'available_stock' => $initial,
                    'reserved_stock' => 0,
                    'sold_stock' => 0,
                    'status' => $initial > 0 ? 'ACTIVE' : 'SOLD_OUT',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stock = DB::table('daily_stocks')->where('id', $id)->first();
            }

            $this->assertStockInvariant($stock);
            return $stock;
        });
    }

    /**
     * Reservar unidades de stock con Candado Transaccional FOR UPDATE.
     * Retorna array ['success' => bool, 'reservation_id' => int|null, 'message' => string]
     */
    public function reserveStock(int $tenantId, int $productId, int $quantity, ?int $orderId = null, int $ttlMinutes = 15): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("La cantidad a reservar debe ser mayor a 0.");
        }

        $date = date('Y-m-d');

        return DB::transaction(function () use ($tenantId, $productId, $quantity, $orderId, $ttlMinutes, $date) {
            $stock = DB::table('daily_stocks')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = $this->getOrInitializeDailyStock($tenantId, $productId, $date);
            }

            if ($stock->available_stock < $quantity) {
                return [
                    'success' => false,
                    'reservation_id' => null,
                    'available' => (int) $stock->available_stock,
                    'message' => "Stock insuficiente. Disponibles hoy: {$stock->available_stock} unidades."
                ];
            }

            $newAvailable = $stock->available_stock - $quantity;
            $newReserved = $stock->reserved_stock + $quantity;

            DB::table('daily_stocks')->where('id', $stock->id)->update([
                'available_stock' => $newAvailable,
                'reserved_stock' => $newReserved,
                'status' => $newAvailable === 0 ? 'SOLD_OUT' : 'ACTIVE',
                'updated_at' => now(),
            ]);

            // Crear registro de reserva individual
            $reservationId = DB::table('stock_reservations')->insertGetId([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => 'RESERVED',
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Verificar invariante post-mutación
            $updatedStock = DB::table('daily_stocks')->where('id', $stock->id)->first();
            $this->assertStockInvariant($updatedStock);

            return [
                'success' => true,
                'reservation_id' => $reservationId,
                'available' => $newAvailable,
                'message' => "Reserva exitosa de {$quantity} unidades."
            ];
        });
    }

    /**
     * Confirmar Venta de una reserva (RESERVED -> CONFIRMED -> SOLD).
     */
    public function confirmReservation(int $tenantId, int $reservationId): bool
    {
        $date = date('Y-m-d');

        return DB::transaction(function () use ($tenantId, $reservationId, $date) {
            $res = DB::table('stock_reservations')
                ->where('tenant_id', $tenantId)
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$res || $res->status !== 'RESERVED') {
                return false;
            }

            $stock = DB::table('daily_stocks')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $res->product_id)
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if (!$stock) return false;

            $newReserved = max(0, $stock->reserved_stock - $res->quantity);
            $newSold = $stock->sold_stock + $res->quantity;

            DB::table('daily_stocks')->where('id', $stock->id)->update([
                'reserved_stock' => $newReserved,
                'sold_stock' => $newSold,
                'updated_at' => now(),
            ]);

            DB::table('stock_reservations')->where('id', $reservationId)->update([
                'status' => 'CONFIRMED',
                'updated_at' => now(),
            ]);

            $updatedStock = DB::table('daily_stocks')->where('id', $stock->id)->first();
            $this->assertStockInvariant($updatedStock);

            return true;
        });
    }

    /**
     * Liberar Reserva por Expiración o Cancelación (RESERVED -> EXPIRED/CANCELLED -> RELEASED).
     */
    public function releaseReservation(int $tenantId, int $reservationId, string $targetStatus = 'CANCELLED'): bool
    {
        $date = date('Y-m-d');

        return DB::transaction(function () use ($tenantId, $reservationId, $targetStatus, $date) {
            $res = DB::table('stock_reservations')
                ->where('tenant_id', $tenantId)
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$res || $res->status !== 'RESERVED') {
                return false;
            }

            $stock = DB::table('daily_stocks')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $res->product_id)
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $newReserved = max(0, $stock->reserved_stock - $res->quantity);
                $newAvailable = $stock->available_stock + $res->quantity;

                DB::table('daily_stocks')->where('id', $stock->id)->update([
                    'reserved_stock' => $newReserved,
                    'available_stock' => $newAvailable,
                    'status' => 'ACTIVE',
                    'updated_at' => now(),
                ]);

                $updatedStock = DB::table('daily_stocks')->where('id', $stock->id)->first();
                $this->assertStockInvariant($updatedStock);
            }

            DB::table('stock_reservations')->where('id', $reservationId)->update([
                'status' => $targetStatus,
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
