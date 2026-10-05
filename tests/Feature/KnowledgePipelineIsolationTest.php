<?php

namespace Tests\Feature;

use App\Modules\Tenant\Database\Seeders\MultiTenantTestSeeder;
use App\Modules\Business\Models\BusinessSource;
use App\Modules\Business\Models\KnowledgeItem;
use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Services\KnowledgeApprovalService;
use App\Modules\Business\Services\CatalogPublishingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgePipelineIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MultiTenantTestSeeder())->run();
    }

    /** @test */
    public function test_unapproved_knowledge_items_do_not_bleed_into_transactional_products()
    {
        $tenantId = 4; // Pastelería Donuts Fs

        // 1. Crear una fuente (Capa A: SOURCE)
        $source = BusinessSource::create([
            'tenant_id' => $tenantId,
            'source_type' => 'DOCUMENT_PDF',
            'title' => 'Menu_Especial_Donuts_2026.pdf',
            'status' => 'EXTRACTED',
        ]);

        // 2. Extracción almacenada en borrador (Capa B: KNOWLEDGE)
        $knowledgeItem = KnowledgeItem::create([
            'tenant_id' => $tenantId,
            'source_id' => $source->id,
            'item_type' => 'PRODUCT_CANDIDATE',
            'detected_name' => 'Dona Nutella Suprema Extra',
            'detected_category' => 'Donas Gourmet',
            'detected_price' => 7.50,
            'detected_description' => 'Dona rellena de crema de avellanas nutella.',
            'confidence_score' => 0.980,
            'status' => 'PENDING_APPROVAL',
        ]);

        // ASERCIÓN 1: El producto borrador NO debe existir en la verdad transaccional (products)
        $productInDb = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('name', 'Dona Nutella Suprema Extra')
            ->first();

        $this->assertNull($productInDb, 'REGLA 1 VIOLADA: El producto borrador ingresó a la verdad transaccional sin aprobación humana.');

        // 3. Flujo de Aprobación Humana (KnowledgeApprovalService)
        $approvalService = new KnowledgeApprovalService();
        $approvedItem = $approvalService->approveItem($tenantId, $knowledgeItem->id);

        $this->assertEquals('APPROVED', $approvedItem->status);

        // 4. Publicación al Catálogo Transaccional (CatalogPublishingService)
        $publishingService = new CatalogPublishingService();
        $version = $publishingService->publishApprovedCatalog($tenantId);

        // ASERCIÓN 2: El producto APROBADO ahora SÍ existe en la verdad transaccional
        $publishedProduct = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('name', 'Dona Nutella Suprema Extra')
            ->first();

        $this->assertNotNull($publishedProduct);
        $this->assertEquals(7.50, $publishedProduct->price);

        // ASERCIÓN 3: La versión del catálogo debe haberse incrementado a 1
        $this->assertEquals(1, $version->version_number);
        $this->assertEquals(1, $version->published_items_count);
    }
}
