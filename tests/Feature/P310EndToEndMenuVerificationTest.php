<?php

namespace Tests\Feature;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Modules\AI\Providers\MockVisionProvider;
use App\Modules\AI\Services\MenuIngestionGuardrailService;
use App\Modules\Business\Repositories\BusinessHourRepository;
use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use App\Modules\Business\Services\MenuIngestionEngine;
use App\Modules\Messaging\Services\InboundMediaClassifierService;
use App\Modules\Messaging\Services\MenuVisionAnalyzerService;
use App\Modules\Messaging\Services\WhatsAppMediaDownloaderService;
use App\Modules\Orders\Services\AutomaticOrderParserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class P310EndToEndMenuVerificationTest extends TestCase
{
    private MenuVisionAnalyzerService $analyzer;
    private AutomaticOrderParserService $orderParser;
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

        $downloader = new WhatsAppMediaDownloaderService();
        $mockVision = new MockVisionProvider();
        $guardrail = new MenuIngestionGuardrailService();
        $engine = new MenuIngestionEngine($this->compiler);

        $this->analyzer = new MenuVisionAnalyzerService($downloader, $mockVision, $guardrail, $engine);
        $this->orderParser = new AutomaticOrderParserService();
    }

    /**
     * G-B01 / B-01: Webhook Idempotency.
     */
    public function test_01_webhook_idempotency_b01(): void
    {
        $tenantId = 4;
        $msgId = 'WAMID_TEST_' . rand(10000, 99999);

        // Evento 1
        DB::table('inbound_webhook_events')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'whatsapp_message_id' => $msgId,
            'media_id' => 'media_123',
            'status' => 'RECEIVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Evento 2 (Reintento de Meta con la misma clave única)
        $isDuplicateCaught = false;
        try {
            DB::table('inbound_webhook_events')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'whatsapp_message_id' => $msgId,
                'media_id' => 'media_123',
                'status' => 'RECEIVED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $isDuplicateCaught = true;
        }

        $this::assertTrue($isDuplicateCaught, "El reintento con la misma clave whatsapp_message_id debió disparar restricción de idempotencia.");
    }

    /**
     * G-B02 / B-05: Descarga segura Graph API con validación de Magic Bytes real.
     */
    public function test_02_media_downloader_magic_bytes_b05(): void
    {
        $downloader = new WhatsAppMediaDownloaderService();

        // Magic Bytes PNG válido
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $mime = $downloader->detectMimeFromMagicBytes($pngBytes);
        $this::assertEquals('image/png', $mime);

        // Texto plano inválido
        $fakeBytes = "PLAIN_TEXT_NOT_AN_IMAGE";
        $fakeMime = $downloader->detectMimeFromMagicBytes($fakeBytes);
        $this::assertNull($fakeMime);
    }

    /**
     * G-B03 / B-02: Clasificación Fail-Closed.
     */
    public function test_03_fail_closed_classification_b02(): void
    {
        $classifier = new InboundMediaClassifierService();

        // Caption ambiguo -> Fail-Closed
        $res = $classifier->classifyInboundMedia('base64_data', 'Foto de comprobante o menú sin claridad');
        
        // Si no es explícitamente menú ni pago, la clasificación es AMBIGUOUS y no puede procesarse financieramente
        $this::assertFalse($res['can_process_financially']);
    }

    /**
     * G-B04: Orquestación completada en MenuVisionAnalyzerService.
     */
    public function test_04_menu_vision_orchestration(): void
    {
        $tenantId = 4;
        $res = $this->analyzer->processWhatsAppMenuMedia($tenantId, 'mock_media_p310b');

        $this::assertEquals('SUCCESS', $res['status']);
        $this::assertStringContainsString('hemos procesado tu carta', strtolower($res['customer_reply']));
    }

    /**
     * G-B05: Catálogo MySQL actualizado y Redis recompilado POST-COMMIT.
     */
    public function test_05_catalog_and_redis_refresh(): void
    {
        $tenantId = 4;
        $this->analyzer->processWhatsAppMenuMedia($tenantId, 'mock_media_p310b');

        $snapshot = $this->compiler->getSnapshot($tenantId);
        $this::assertNotNull($snapshot);
        $this::assertNotEmpty($snapshot['products'] ?? []);
    }

    /**
     * G-B06 / B-03: Deterministic Product Resolution (UNRESOLVED & AMBIGUOUS).
     */
    public function test_06_unresolved_and_ambiguous_product_b03(): void
    {
        $tenantId = 4;

        // Caso A: Producto inexistente -> UNRESOLVED_PRODUCT
        $resUnresolved = $this->orderParser->syncCommercialOrder($tenantId, '987654321', 'Cliente Test', [
            ['name' => 'Arroz Marciano Ficticio', 'quantity' => 1]
        ]);
        $this::assertEquals('UNRESOLVED_PRODUCT', $resUnresolved['status']);

        // Caso B: Ambiguo -> AMBIGUOUS_PRODUCT
        // Insertar 2 variantes con el mismo normalized_name para probar ambigüedad
        $catId = DB::table('categories')->where('tenant_id', $tenantId)->value('id') ?? DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Pollos', 'slug' => 'pollos', 'normalized_name' => 'pollos', 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'category_id' => $catId, 'name' => 'Pollo Broaster 1', 'slug' => 'pollo-1', 'normalized_name' => 'pollo ambiguo', 'base_price' => 15.00, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()
        ]);
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'category_id' => $catId, 'name' => 'Pollo Broaster 2', 'slug' => 'pollo-2', 'normalized_name' => 'pollo ambiguo', 'base_price' => 18.00, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()
        ]);

        $resAmbiguous = $this->orderParser->syncCommercialOrder($tenantId, '987654321', 'Cliente Test', [
            ['name' => 'Pollo Ambiguo', 'quantity' => 1]
        ]);

        $this::assertEquals('AMBIGUOUS_PRODUCT', $resAmbiguous['status']);
    }

    /**
     * G-B07 / B-04: Price Authority (MySQL overrides LLM / Client text).
     */
    public function test_07_price_authority_db_overrides_llm_b04(): void
    {
        $tenantId = 4;
        
        // Preparar producto con base_price oficial = 18.00
        $catId = DB::table('categories')->where('tenant_id', $tenantId)->value('id') ?? DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Especiales', 'slug' => 'especiales', 'normalized_name' => 'especiales', 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('products')->where('tenant_id', $tenantId)->where('normalized_name', 'chaufa autoridad')->delete();
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'category_id' => $catId,
            'name' => 'Chaufa Autoridad',
            'slug' => 'chaufa-autoridad',
            'normalized_name' => 'chaufa autoridad',
            'base_price' => 18.00, // Autoridad Comercial en MySQL
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Caso A: El cliente no especifica precio
        $resA = $this->orderParser->syncCommercialOrder($tenantId, '911111111', 'Cliente A', [
            ['name' => 'Chaufa Autoridad', 'quantity' => 2]
        ]);
        $this::assertEquals(36.00, $resA['total_amount']);

        // Caso B: El cliente menciona "a S/10" o el LLM devuelve 0.01 -> MySQL IMPONE 18.00
        $resB = $this->orderParser->syncCommercialOrder($tenantId, '922222222', 'Cliente B', [
            ['name' => 'Chaufa Autoridad', 'quantity' => 1, 'price' => 10.00]
        ]);
        $this::assertEquals(18.00, $resB['total_amount']);
    }

    /**
     * G-B08: Multi-tenant pipeline isolation.
     */
    public function test_08_multi_tenant_pipeline_isolation(): void
    {
        $tenant4 = 4;
        $tenant5 = 5;

        // Limpiar tenant 5
        DB::table('products')->where('tenant_id', $tenant5)->delete();

        // Ingestar menú en tenant 4
        $this->analyzer->processWhatsAppMenuMedia($tenant4, 'mock_media_tenant4');

        // Tenant 5 debe permanecer intacto
        $prodCount5 = DB::table('products')->where('tenant_id', $tenant5)->count();
        $this::assertEquals(0, $prodCount5);
    }

    /**
     * G-B09 / B-06: Historical Commercial Snapshot in order_items.
     */
    public function test_09_order_items_commercial_snapshot_and_price_change_b06(): void
    {
        $tenantId = 4;

        $catId = DB::table('categories')->where('tenant_id', $tenantId)->value('id') ?? DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Bebidas', 'slug' => 'bebidas', 'normalized_name' => 'bebidas', 'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('products')->where('tenant_id', $tenantId)->where('normalized_name', 'chicha morada')->delete();
        $prodId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'category_id' => $catId,
            'name' => 'Chicha Morada',
            'slug' => 'chicha-morada',
            'normalized_name' => 'chicha morada',
            'base_price' => 12.00,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Crear Orden hoy (S/12.00)
        $orderRes = $this->orderParser->syncCommercialOrder($tenantId, '933333333', 'Cliente Snapshot', [
            ['name' => 'Chicha Morada', 'quantity' => 2]
        ]);
        $orderId = $orderRes['order_id'];

        // 2. Cambiar precio en el catálogo a S/20.00 mañana
        DB::table('products')->where('id', $prodId)->update(['base_price' => 20.00]);

        // 3. Verificar que la orden histórica conserva S/12.00 unit_price y S/24.00 subtotal
        $itemRecord = DB::table('order_items')->where('order_id', $orderId)->first();
        $this::assertEquals(12.00, (float) $itemRecord->unit_price);
        $this::assertEquals(24.00, (float) $itemRecord->subtotal);
    }

    /**
     * G-B10: Full E2E Operational Real Execution Flow.
     */
    public function test_10_full_e2e_real_menu_execution(): void
    {
        $tenantId = 4;
        $customerPhone = '999888777';

        // 1. WhatsApp Ingesta de Carta Visual
        $ingestRes = $this->analyzer->processWhatsAppMenuMedia($tenantId, 'mock_media_full_e2e');
        $this::assertEquals('SUCCESS', $ingestRes['status']);

        // 2. Cliente consulta y ordena producto
        $orderRes = $this->orderParser->syncCommercialOrder($tenantId, $customerPhone, 'Cliente E2E', [
            ['name' => 'Chaufa Amazónico', 'quantity' => 1]
        ]);

        $this::assertEquals('ORDER_CREATED', $orderRes['status']);
        $this::assertGreaterThan(0, $orderRes['total_amount']);
        $this::assertCount(1, $orderRes['items']);
        $this::assertNotNull($orderRes['items'][0]['product_id']);
    }
}
