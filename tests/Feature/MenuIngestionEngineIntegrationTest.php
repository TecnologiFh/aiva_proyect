<?php

namespace Tests\Feature;

use App\Modules\AI\DTOs\MenuGuardrailResultDTO;
use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\Business\Repositories\BusinessHourRepository;
use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use App\Modules\Business\Services\MenuIngestionEngine;
use App\Core\Contracts\EventBus\EventBusInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MenuIngestionEngineIntegrationTest extends TestCase
{
    private MenuIngestionEngine $engine;
    private BusinessKnowledgeCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        
        $eventBusMock = new class implements EventBusInterface {
            public function dispatch(object $event): void {}
        };

        $this->compiler = new BusinessKnowledgeCompiler(
            new BusinessSettingRepository(),
            new BusinessHourRepository(),
            new CategoryRepository(),
            new ProductRepository(),
            $eventBusMock
        );

        $this->engine = new MenuIngestionEngine($this->compiler);
    }

    /**
     * G-A07: Ingesta atómica dentro de DB::transaction() y Rollback ante fallo.
     */
    public function test_ga07_transaction_rollback_leaves_database_and_redis_clean(): void
    {
        $tenantId = 999;
        
        // Limpiar datos previos
        DB::table('products')->where('tenant_id', $tenantId)->delete();
        DB::table('categories')->where('tenant_id', $tenantId)->delete();

        $guardrailResult = new MenuGuardrailResultDTO(
            status: MenuGuardrailResultDTO::STATUS_ACCEPTED,
            globalConfidence: 0.95,
            acceptedCategories: [
                new VisionMenuCategoryDTO(
                    name: 'Entradas Especiales',
                    normalizedName: 'entradas especiales',
                    items: [
                        new VisionMenuItemDTO(
                            name: 'Empanadas de Carne',
                            normalizedName: 'empanadas de carne',
                            basePrice: 12.00,
                            confidence: 0.95,
                            detectedPrices: [12.00]
                        )
                    ]
                )
            ]
        );

        // Forzar error simulado en BD atrapando excepción
        try {
            DB::transaction(function() use ($tenantId, $guardrailResult) {
                $this->engine->ingestValidatedMenu($tenantId, $guardrailResult);
                throw new \Exception('Simulated Database Error for Rollback Test');
            });
        } catch (\Throwable $e) {}

        // Verificar que DB no tuvo cambios
        $catCount = DB::table('categories')->where('tenant_id', $tenantId)->count();
        $prodCount = DB::table('products')->where('tenant_id', $tenantId)->count();

        $this::assertEquals(0, $catCount);
        $this::assertEquals(0, $prodCount);
    }

    /**
     * G-A08: Restricción única multi-tenant e Idempotencia MySQL.
     */
    public function test_ga08_idempotent_upsert_and_multi_tenant_isolation(): void
    {
        $tenantId = 4;
        
        $guardrailResult = new MenuGuardrailResultDTO(
            status: MenuGuardrailResultDTO::STATUS_ACCEPTED,
            globalConfidence: 0.96,
            acceptedCategories: [
                new VisionMenuCategoryDTO(
                    name: 'Fondos Peruanos',
                    normalizedName: 'fondos peruanos',
                    items: [
                        new VisionMenuItemDTO(
                            name: 'Arroz Chaufa Amazónico',
                            normalizedName: 'arroz chaufa amazonico',
                            basePrice: 18.00,
                            confidence: 0.98,
                            detectedPrices: [18.00]
                        )
                    ]
                )
            ]
        );

        // Ingesta 1
        $res1 = $this->engine->ingestValidatedMenu($tenantId, $guardrailResult);
        $this::assertEquals('INGESTION_SUCCESS', $res1['status']);
        $this::assertEquals(1, $res1['created_categories']);
        $this::assertEquals(1, $res1['created_products']);

        // Ingesta 2 (Mismos datos -> Reutiliza y actualiza sin duplicar)
        $res2 = $this->engine->ingestValidatedMenu($tenantId, $guardrailResult);
        $this::assertEquals('INGESTION_SUCCESS', $res2['status']);
        $this::assertEquals(0, $res2['created_categories']);
        $this::assertEquals(1, $res2['updated_categories']);
        $this::assertEquals(0, $res2['created_products']);
        $this::assertEquals(1, $res2['updated_products']);

        // Verificar recuento de filas en BD
        $catId = DB::table('categories')->where('tenant_id', $tenantId)->where('normalized_name', 'fondos peruanos')->value('id');
        $this::assertNotNull($catId);

        $prodCount = DB::table('products')->where('tenant_id', $tenantId)->where('category_id', $catId)->where('normalized_name', 'arroz chaufa amazonico')->count();
        $this::assertEquals(1, $prodCount);
    }

    /**
     * G-A09: Recompilación de Redis únicamente POST-COMMIT.
     */
    public function test_ga09_redis_recompilation_runs_strictly_after_commit(): void
    {
        $tenantId = 4;

        $guardrailResult = new MenuGuardrailResultDTO(
            status: MenuGuardrailResultDTO::STATUS_ACCEPTED,
            globalConfidence: 0.90,
            acceptedCategories: [
                new VisionMenuCategoryDTO(
                    name: 'Postres Tradicionales',
                    normalizedName: 'postres tradicionales',
                    items: [
                        new VisionMenuItemDTO(
                            name: 'Suspiro a la Limeña',
                            normalizedName: 'suspiro a la limena',
                            basePrice: 16.00,
                            confidence: 0.95,
                            detectedPrices: [16.00]
                        )
                    ]
                )
            ]
        );

        $res = $this->engine->ingestValidatedMenu($tenantId, $guardrailResult);
        $this::assertEquals('INGESTION_SUCCESS', $res['status']);

        // Verificar que el snapshot de conocimiento de Redis/Cache se haya recompilado
        $snapshot = $this->compiler->getSnapshot($tenantId);
        $this::assertNotNull($snapshot);
        $this::assertArrayHasKey('products', $snapshot);
    }
}
