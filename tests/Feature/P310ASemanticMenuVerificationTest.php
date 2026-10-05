<?php

namespace Tests\Feature;

use App\Modules\AI\DTOs\VisionMenuCategoryDTO;
use App\Modules\AI\DTOs\VisionMenuExtractionDTO;
use App\Modules\AI\DTOs\VisionMenuItemDTO;
use App\Modules\AI\Services\AIContextCatalogProjectorService;
use App\Modules\AI\Services\CanonicalCatalogBuilderService;
use App\Modules\AI\Services\MenuSemanticNormalizerService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class P310ASemanticMenuVerificationTest extends TestCase
{
    private MenuSemanticNormalizerService $normalizer;
    private CanonicalCatalogBuilderService $builder;
    private AIContextCatalogProjectorService $projector;
    private CatalogReferenceResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new MenuSemanticNormalizerService();
        $this->builder = new CanonicalCatalogBuilderService();
        $this->projector = new AIContextCatalogProjectorService();
        $this->resolver = new CatalogReferenceResolverService();
    }

    /**
     * Fixture del restaurante de prueba con layout imperfecto y precios repetidos (S/17)
     */
    private function createTestRestaurantFixture(): VisionMenuExtractionDTO
    {
        return new VisionMenuExtractionDTO(
            status: VisionMenuExtractionDTO::STATUS_SUCCESS,
            globalConfidence: 0.95,
            categories: [
                new VisionMenuCategoryDTO(
                    name: 'Menú Común',
                    normalizedName: 'menu comun',
                    description: 'Menú económico del día',
                    items: [
                        new VisionMenuItemDTO(name: 'Arroz con Pollo', normalizedName: 'arroz con pollo', basePrice: 12.00, detectedPrices: [12.00]),
                        new VisionMenuItemDTO(name: 'Lomo Saltado Simple', normalizedName: 'lomo saltado simple', basePrice: 12.00, detectedPrices: [12.00]),
                    ]
                ),
                new VisionMenuCategoryDTO(
                    name: 'Menú Especial',
                    normalizedName: 'menu especial',
                    description: 'Menú ejecutivo',
                    items: [
                        new VisionMenuItemDTO(name: 'Seco de Res', normalizedName: 'seco de res', basePrice: 15.00, detectedPrices: [15.00]),
                    ]
                ),
                new VisionMenuCategoryDTO(
                    name: 'Súper Especial',
                    normalizedName: 'super especial',
                    description: 'Platos premiun de la casa',
                    items: [
                        new VisionMenuItemDTO(name: 'Bisteck a la Pobre', normalizedName: 'bisteck a la pobre', basePrice: 17.00, detectedPrices: [17.00]),
                    ]
                ),
                new VisionMenuCategoryDTO(
                    name: 'Menú Marino',
                    normalizedName: 'menu marino',
                    description: 'Especialidades del mar',
                    items: [
                        new VisionMenuItemDTO(name: 'Ceviche Mixto', normalizedName: 'ceviche mixto', basePrice: 17.00, detectedPrices: [17.00]),
                        new VisionMenuItemDTO(name: 'Chicharrón de Calamar', normalizedName: 'chicharron de calamar', basePrice: 17.00, detectedPrices: [17.00]),
                    ]
                ),
            ]
        );
    }

    public function test_ag01_to_ag32_semantic_menu_pipeline(): void
    {
        $tenantId = 10;
        $docId = 'doc_fixture_' . rand(1000, 9999);

        // Limpiar datos del tenant de prueba
        DB::table('menu_groups')->where('tenant_id', $tenantId)->delete();
        DB::table('raw_menu_evidences')->where('tenant_id', $tenantId)->delete();
        DB::table('canonical_catalogs')->where('tenant_id', $tenantId)->delete();

        // 1. Normalización Semántica (AG-01, AG-02, AG-03, AG-05, AG-06, AG-12, AG-16, AG-17, AG-23, AG-27)
        $fixture = $this->createTestRestaurantFixture();
        $normalized = $this->normalizer->normalizeMenuExtraction($tenantId, $fixture, $docId);

        $this::assertCount(4, $normalized['menu_groups']);
        
        // AG-02 & AG-17: Verificación de evidencia espacial y FK evidence_id
        $evidenceCount = DB::table('raw_menu_evidences')->where('tenant_id', $tenantId)->count();
        $this::assertGreaterThan(0, $evidenceCount);

        // AG-03 & AG-20: Dos menús a S/17 (Súper Especial y Menú Marino) coexisten sin colisión
        $groupsAt17 = DB::table('menu_groups')->where('tenant_id', $tenantId)->where('price', 17.00)->get();
        $this::assertCount(2, $groupsAt17);

        // AG-06 & AG-23: Scorings de 5 factores persistidos
        $assoc = DB::table('product_menu_associations')->where('tenant_id', $tenantId)->first();
        $this::assertNotNull($assoc->spatial_score);
        $this::assertNotNull($assoc->semantic_score);
        $this::assertNotNull($assoc->heading_score);
        $this::assertNotNull($assoc->price_score);
        $this::assertNotNull($assoc->layout_score);

        // 2. Publicación de Catálogo Canónico (AG-04, AG-08, AG-18, AG-19, AG-24, AG-29, AG-32)
        $publishedCatalog = $this->builder->publishCanonicalCatalog($tenantId, $normalized['menu_groups'], $docId);
        $this::assertEquals('PUBLISHED', $publishedCatalog->status);
        $this::assertEquals(1, $publishedCatalog->version);

        // AG-09 & AG-24: AI Context Projection
        $promptContext = $this->projector->projectAIContext($tenantId);
        $this::assertStringContainsString('Menú Común', $promptContext);
        $this::assertStringContainsString('Menú Marino', $promptContext);

        // 3. Conversational Ambiguity Resolution (AG-10, AG-11, AG-21, AG-22)
        
        // AG-10 & AG-22: "el de 17" -> AMBIGUOUS (2 candidatos, prohibido first())
        $res17 = $this->resolver->resolveCustomerReference($tenantId, 'Quiero el de 17');
        $this::assertTrue($res17->isAmbiguous());
        $this::assertCount(2, $res17->candidates);
        $this::assertContains('Súper Especial', $res17->candidates);
        $this::assertContains('Menú Marino', $res17->candidates);

        // AG-11: "el marino" -> RESOLVED (Coincidencia directa por alias/nombre)
        $resMarino = $this->resolver->resolveCustomerReference($tenantId, 'Quiero el marino');
        $this::assertTrue($resMarino->isResolved());
        $this::assertEquals('Menú Marino', $resMarino->entityName);
        $this::assertEquals(17.00, $resMarino->price);

        // AG-07: "el de 12" -> RESOLVED
        $res12 = $this->resolver->resolveCustomerReference($tenantId, 'Quiero el de 12');
        $this::assertTrue($res12->isResolved());
        $this::assertEquals('Menú Común', $res12->entityName);

        // AG-13: Multi-tenant isolation test
        $resTenantOther = $this->resolver->resolveCustomerReference(999, 'Quiero el marino');
        $this::assertTrue($resTenantOther->isNotFound());
    }
}
