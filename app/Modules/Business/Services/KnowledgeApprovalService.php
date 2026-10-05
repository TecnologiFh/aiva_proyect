<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\KnowledgeItem;
use Illuminate\Support\Carbon;

class KnowledgeApprovalService
{
    /**
     * Aprobar un ítem borrador y publicarlo directamente en la tabla oficial products.
     */
    public function approveItem(int $tenantId, int $itemId, ?int $userId = null): KnowledgeItem
    {
        $item = KnowledgeItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $item->update([
            'status' => 'APPROVED',
            'reviewed_by' => $userId,
            'reviewed_at' => Carbon::now(),
        ]);

        $this->syncItemToProductsCatalog($tenantId, $item);

        return $item;
    }

    /**
     * Aprobar todos los ítems pendientes de un tenant y publicarlos en products.
     */
    public function approveAllItems(int $tenantId, ?int $userId = null): int
    {
        $pendingItems = KnowledgeItem::where('tenant_id', $tenantId)
            ->where('status', 'PENDING_APPROVAL')
            ->get();

        $count = 0;
        foreach ($pendingItems as $item) {
            $item->update([
                'status' => 'APPROVED',
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);
            $this->syncItemToProductsCatalog($tenantId, $item);
            $count++;
        }

        return $count;
    }

    /**
     * Inserción o actualización directa en la tabla de productos oficiales (Catálogo & Inventario).
     */
    public function syncItemToProductsCatalog(int $tenantId, KnowledgeItem $item): void
    {
        $catId = null;
        if (!empty($item->detected_category) && \Illuminate\Support\Facades\Schema::hasTable('categories')) {
            $catCols = \Illuminate\Support\Facades\Schema::getColumnListing('categories');
            $category = \Illuminate\Support\Facades\DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->where('name', $item->detected_category)
                ->first();

            if (!$category) {
                $catData = [
                    'tenant_id' => $tenantId,
                    'name' => $item->detected_category,
                    'slug' => \Illuminate\Support\Str::slug($item->detected_category) . '-' . \Illuminate\Support\Str::random(4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (in_array('uuid', $catCols)) {
                    $catData['uuid'] = (string) \Illuminate\Support\Str::uuid();
                }
                $catId = \Illuminate\Support\Facades\DB::table('categories')->insertGetId($catData);
            } else {
                $catId = $category->id;
            }
        }

        $price = (float)($item->detected_price ?? 0.0);
        $sku = 'SKU-' . strtoupper(\Illuminate\Support\Str::random(6));

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $prodCols = \Illuminate\Support\Facades\Schema::getColumnListing('products');
            $updateData = [
                'updated_at' => now(),
            ];

            if (in_array('uuid', $prodCols)) $updateData['uuid'] = (string) \Illuminate\Support\Str::uuid();
            if (in_array('slug', $prodCols)) $updateData['slug'] = \Illuminate\Support\Str::slug($item->detected_name) . '-' . \Illuminate\Support\Str::random(4);
            if (in_array('description', $prodCols)) $updateData['description'] = $item->detected_description ?? 'Producto oficial importado desde carta.';
            if (in_array('base_price', $prodCols)) $updateData['base_price'] = $price;
            if (in_array('price', $prodCols)) $updateData['price'] = $price;
            // AG-46: Invariante Constitucional de Stock (NULL cuando no hay evidencia, UNKNOWN status)
            $rawStock = $item->detected_stock ?? null;
            $invDto = \App\Modules\Business\DTOs\InventoryDTO::fromRaw($rawStock);

            if (in_array('stock', $prodCols)) $updateData['stock'] = $invDto->stock;
            if (in_array('stock_status', $prodCols)) $updateData['stock_status'] = $invDto->stockStatus;
            if (in_array('current_stock', $prodCols)) $updateData['current_stock'] = $invDto->stock;
            if (in_array('quantity', $prodCols)) $updateData['quantity'] = $invDto->stock;
            if (in_array('calculated_stock', $prodCols)) $updateData['calculated_stock'] = $invDto->stock;
            if (in_array('status', $prodCols)) $updateData['status'] = 'ACTIVE';
            if (in_array('is_available', $prodCols)) $updateData['is_available'] = 1;
            if (in_array('product_type', $prodCols)) $updateData['product_type'] = 'PRODUCT';
            if (in_array('sku', $prodCols)) $updateData['sku'] = $sku;
            if (in_array('category_id', $prodCols) && $catId !== null) $updateData['category_id'] = $catId;

            $existing = \Illuminate\Support\Facades\DB::table('products')
                ->where('tenant_id', $tenantId)
                ->where('name', $item->detected_name)
                ->first();

            if ($existing) {
                \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', $existing->id)
                    ->update($updateData);
            } else {
                $updateData['tenant_id'] = $tenantId;
                $updateData['name'] = $item->detected_name;
                $updateData['created_at'] = now();
                \Illuminate\Support\Facades\DB::table('products')->insert($updateData);
            }
        }
    }

    /**
     * Rechazar un ítem borrador.
     */
    public function rejectItem(int $tenantId, int $itemId, ?int $userId = null): KnowledgeItem
    {
        $item = KnowledgeItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $item->update([
            'status' => 'REJECTED',
            'reviewed_by' => $userId,
            'reviewed_at' => Carbon::now(),
        ]);
        return $item;
    }

    /**
     * Editar datos de un ítem borrador antes de aprobarlo.
     */
    public function editItem(int $tenantId, int $itemId, array $changes, ?int $userId = null): KnowledgeItem
    {
        $item = KnowledgeItem::where('tenant_id', $tenantId)->findOrFail($itemId);
        $updateData = array_merge($changes, [
            'status' => 'EDITED',
            'reviewed_by' => $userId,
            'reviewed_at' => Carbon::now(),
        ]);
        $item->update($updateData);
        return $item->fresh();
    }
}
