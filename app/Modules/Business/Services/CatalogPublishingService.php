<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\KnowledgeItem;
use App\Modules\Business\Models\CatalogVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogPublishingService
{
    /**
     * Publicar todos los ítems aprobados desde Knowledge hacia la Verdad Transaccional (products).
     */
    public function publishApprovedCatalog(int $tenantId, ?int $userId = null): CatalogVersion
    {
        return DB::transaction(function () use ($tenantId, $userId) {
            $approvedItems = KnowledgeItem::where('tenant_id', $tenantId)
                ->whereIn('status', ['APPROVED', 'EDITED'])
                ->get();

            $publishedCount = 0;
            $publishedProducts = [];

            foreach ($approvedItems as $item) {
                $sku = 'SKU-' . strtoupper(Str::random(6));
                
                DB::table('products')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $item->detected_name,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'description' => $item->detected_description ?? 'Producto extraído y publicado desde AIVA Knowledge Hub.',
                        'base_price' => $item->detected_price ?? 0.00,
                        'price' => $item->detected_price ?? 0.00,
                        'calculated_stock' => 10,
                        'stock' => 10,
                        'status' => 'ACTIVE',
                        'sku' => $sku,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $publishedCount++;
                $publishedProducts[] = [
                    'name' => $item->detected_name,
                    'price' => $item->detected_price,
                    'category' => $item->detected_category,
                ];
            }

            // Incrementar número de versión
            $lastVersion = CatalogVersion::where('tenant_id', $tenantId)->max('version_number') ?? 0;
            $nextVersionNumber = $lastVersion + 1;

            $versionRecord = CatalogVersion::create([
                'tenant_id' => $tenantId,
                'version_number' => $nextVersionNumber,
                'changelog_summary' => "Publicación v{$nextVersionNumber}: {$publishedCount} productos publicados desde Knowledge Hub.",
                'published_items_count' => $publishedCount,
                'published_by' => $userId,
                'version_snapshot' => $publishedProducts,
            ]);

            return $versionRecord;
        });
    }
}
