<?php

namespace App\Modules\Business\Services;

use App\Modules\AI\DTOs\MenuGuardrailResultDTO;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MenuIngestionEngine
{
    public function __construct(
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler
    ) {}

    /**
     * Ingesta atómica e idempotente del resultado del guardrail en la BD MySQL del Tenant
     * y recompilación automática de Redis estrictamente POST-COMMIT.
     */
    public function ingestValidatedMenu(int $tenantId, MenuGuardrailResultDTO $guardrailResult): array
    {
        // 1. Invariante: Bloquear si el guardrail no fue aceptado o no hay ítems aprobados
        if (!$guardrailResult->isIngestable()) {
            return [
                'status' => 'INGESTION_BLOCKED',
                'reason' => $guardrailResult->reasons[0] ?? 'Menú bloqueado por validación de guardrail.',
                'persisted_categories' => 0,
                'created_products' => 0,
                'updated_products' => 0,
                'rejected_items_count' => count($guardrailResult->rejectedItems),
            ];
        }

        $createdCategories = 0;
        $updatedCategories = 0;
        $createdProducts = 0;
        $updatedProducts = 0;

        try {
            DB::transaction(function () use ($tenantId, $guardrailResult, &$createdCategories, &$updatedCategories, &$createdProducts, &$updatedProducts) {
                
                foreach ($guardrailResult->acceptedCategories as $catDTO) {
                    $normCatName = Str::of($catDTO->name)->lower()->ascii()->trim()->squish()->toString();

                    // Categoría Identity: tenant_id + normalized_name
                    $catRecord = DB::table('categories')
                        ->where('tenant_id', $tenantId)
                        ->where('normalized_name', $normCatName)
                        ->first();

                    if (!$catRecord) {
                        $catId = DB::table('categories')->insertGetId([
                            'uuid' => (string) Str::uuid(),
                            'tenant_id' => $tenantId,
                            'name' => $catDTO->name,
                            'slug' => Str::slug($catDTO->name),
                            'normalized_name' => $normCatName,
                            'description' => $catDTO->description,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $createdCategories++;
                    } else {
                        $catId = $catRecord->id;
                        DB::table('categories')->where('id', $catId)->update([
                            'name' => $catDTO->name,
                            'slug' => Str::slug($catDTO->name),
                            'description' => $catDTO->description ?? $catRecord->description,
                            'updated_at' => now(),
                        ]);
                        $updatedCategories++;
                    }

                    // Productos Identity: tenant_id + category_id + normalized_name
                    foreach ($catDTO->items as $itemDTO) {
                        $normProdName = Str::of($itemDTO->name)->lower()->ascii()->trim()->squish()->toString();

                        $prodRecord = DB::table('products')
                            ->where('tenant_id', $tenantId)
                            ->where('category_id', $catId)
                            ->where('normalized_name', $normProdName)
                            ->first();

                        if (!$prodRecord) {
                            DB::table('products')->insert([
                                'uuid' => (string) Str::uuid(),
                                'tenant_id' => $tenantId,
                                'category_id' => $catId,
                                'name' => $itemDTO->name,
                                'slug' => Str::slug($itemDTO->name),
                                'normalized_name' => $normProdName,
                                'description' => $itemDTO->description,
                                'base_price' => $itemDTO->basePrice,
                                'is_available' => $itemDTO->isAvailable ? 1 : 0,
                                'status' => 'ACTIVE',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $createdProducts++;
                        } else {
                            DB::table('products')->where('id', $prodRecord->id)->update([
                                'name' => $itemDTO->name,
                                'slug' => Str::slug($itemDTO->name),
                                'description' => $itemDTO->description ?? $prodRecord->description,
                                'base_price' => $itemDTO->basePrice,
                                'is_available' => $itemDTO->isAvailable ? 1 : 0,
                                'updated_at' => now(),
                            ]);
                            $updatedProducts++;
                        }
                    }
                }

                // 2. Invariante Constitucional: Recompilación de Caché en Redis ÚNICAMENTE POST-COMMIT
                DB::afterCommit(function () use ($tenantId) {
                    try {
                        $this->knowledgeCompiler->compile($tenantId);
                    } catch (\Throwable $e) {
                        Log::error("Failed to compile Business Knowledge post-commit for tenant {$tenantId}: " . $e->getMessage());
                    }
                });
            });

            return [
                'status' => 'INGESTION_SUCCESS',
                'tenant_id' => $tenantId,
                'created_categories' => $createdCategories,
                'updated_categories' => $updatedCategories,
                'created_products' => $createdProducts,
                'updated_products' => $updatedProducts,
                'rejected_items_count' => count($guardrailResult->rejectedItems),
            ];

        } catch (\Throwable $e) {
            Log::error("MenuIngestionEngine Transaction Exception: " . $e->getMessage());
            throw $e;
        }
    }
}
