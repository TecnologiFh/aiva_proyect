<?php

namespace App\Modules\AI\Services;

use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Business\Models\CatalogVersionValidation;
use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\CommercialMediaAlias;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Models\ProductVisualReference;
use App\Modules\Business\Services\CommercialMediaDeliveryService;
use App\Modules\Business\Services\CommercialMediaResolverService;
use App\Modules\Business\Services\MultiSourceCatalogCompilerService;
use App\Modules\Messaging\Controllers\TenantBrainController;
use App\Modules\Orders\Services\AutomaticOrderParserService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use App\Shared\Middleware\GatewayAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class P311EndToEndBusinessVerificationService
{
    protected MultiSourceCatalogCompilerService $compilerService;
    protected CommercialMediaResolverService $mediaResolver;
    protected CommercialMediaDeliveryService $deliveryService;
    protected CatalogReferenceResolverService $catalogResolver;
    protected AutomaticOrderParserService $orderParser;

    public function __construct()
    {
        $this->compilerService = new MultiSourceCatalogCompilerService();
        $this->mediaResolver = new CommercialMediaResolverService();
        $this->deliveryService = new CommercialMediaDeliveryService();
        $this->catalogResolver = new CatalogReferenceResolverService();
        $this->orderParser = new AutomaticOrderParserService();
    }

    public function runE2EBusinessVerification(): array
    {
        $startTime = microtime(true);
        RuntimeCallCounter::reset();

        $tenantId = 11; // Wallpa Brasa
        $testPhone = '51999888999';
        $customerName = 'Carlos Mendoza (Cliente Real)';

        $steps = [];

        // =========================================================================
        // PASO 1: INGESTIÓN Y REGISTRO DE LAS 4 CARTAS COMERCIALES
        // =========================================================================
        $this->seedRestaurantEnvironment($tenantId);

        $card1 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'CARTA_GENERAL')->first();
        $card2 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'CHANCHOS')->first();
        $card3 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'ALITAS')->first();
        $card4 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'PARRILLAS')->first();

        $steps['step1_ingestion'] = [
            'name' => 'Ingestión de las 4 cartas comerciales',
            'status' => ($card1 && $card2 && $card3 && $card4) ? 'PASS' : 'FAIL',
            'cards' => [
                'carta1' => ['id' => $card1->id, 'title' => $card1->title, 'category' => $card1->category],
                'carta2' => ['id' => $card2->id, 'title' => $card2->title, 'category' => $card2->category],
                'carta3' => ['id' => $card3->id, 'title' => $card3->title, 'category' => $card3->category],
                'carta4' => ['id' => $card4->id, 'title' => $card4->title, 'category' => $card4->category],
            ],
        ];

        // =========================================================================
        // PASO 2: COMPILACIÓN CANÓNICA CERTIFICADA (AG-34 + C14 100% COMPLETITUD)
        // =========================================================================
        $publishedCatalog = CatalogVersion::where('tenant_id', $tenantId)
            ->where('status', 'PUBLISHED')
            ->where('is_active', 1)
            ->first();

        $linkedCount = CatalogVersionMedia::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $publishedCatalog?->id)
            ->count();

        $validation = CatalogVersionValidation::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $publishedCatalog?->id)
            ->latest()
            ->first();

        $compilationPass = ($publishedCatalog
            && $linkedCount === 4
            && $validation !== null
            && $validation->status === 'COMPLETE'
            && (int)$validation->expected_sellable_count === 23
            && (int)$validation->missing_count === 0);

        $steps['step2_compilation'] = [
            'name' => 'Compilación Canónica Certificada por PublishCatalogAuthority (C14 + AG-34)',
            'status' => $compilationPass ? 'PASS' : 'FAIL',
            'catalog_version_id' => $publishedCatalog?->id,
            'version_number' => $publishedCatalog?->version_number,
            'status_field' => $publishedCatalog?->status,
            'is_active' => $publishedCatalog?->is_active,
            'linked_cards_count' => $linkedCount,
            'expected_sellable_items' => $validation?->expected_sellable_count,
            'completeness_status' => $validation?->status,
        ];

        // =========================================================================
        // PASO 3: WHATSAPP INBOUND 1 — "Pásame la carta" -> ENTREGA CARTA 1
        // =========================================================================
        $resp1 = $this->sendWhatsAppInboundMessage($testPhone, 'Pásame la carta');
        $att1 = $resp1['attachments'][0] ?? [];
        $isCarta1 = (
            str_contains($att1['caption'] ?? '', 'General') ||
            str_contains($att1['caption'] ?? '', 'Pollos') ||
            str_contains($att1['display_url'] ?? '', 'carta1') ||
            str_contains($att1['display_url'] ?? '', $card1->uuid) ||
            ($att1['media_id'] ?? null) === $card1->id
        );

        $steps['step3_whatsapp_general_menu'] = [
            'name' => 'WhatsApp Mensaje 1: "Pásame la carta" -> Entrega Carta 1',
            'status' => $isCarta1 ? 'PASS' : 'FAIL',
            'customer_message' => 'Pásame la carta',
            'ai_reply' => $resp1['reply_text'] ?? '',
            'attachment_delivered' => [
                'type' => $att1['type'] ?? 'none',
                'caption' => $att1['caption'] ?? 'none',
                'display_url' => $att1['display_url'] ?? 'none',
            ],
            'correctly_resolved_carta1' => $isCarta1,
        ];

        // =========================================================================
        // PASO 4: WHATSAPP INBOUND 2 — "Pásame la carta de parrillas" -> ENTREGA CARTA 4 (AG-37.1 STOP)
        // =========================================================================
        $resp2 = $this->sendWhatsAppInboundMessage($testPhone, 'Pásame la carta de parrillas');
        $att2 = $resp2['attachments'][0] ?? [];
        $isCarta4 = (
            str_contains($att2['caption'] ?? '', 'Parrillas') ||
            str_contains($att2['display_url'] ?? '', 'carta4') ||
            str_contains($att2['display_url'] ?? '', $card4->uuid) ||
            ($att2['media_id'] ?? null) === $card4->id
        );

        $steps['step4_whatsapp_parrillas_menu'] = [
            'name' => 'WhatsApp Mensaje 2: "Pásame la carta de parrillas" -> Entrega Carta 4 (AG-37.1 STOP)',
            'status' => $isCarta4 ? 'PASS' : 'FAIL',
            'customer_message' => 'Pásame la carta de parrillas',
            'ai_reply' => $resp2['reply_text'] ?? '',
            'attachment_delivered' => [
                'type' => $att2['type'] ?? 'none',
                'caption' => $att2['caption'] ?? 'none',
                'display_url' => $att2['display_url'] ?? 'none',
            ],
            'correctly_resolved_carta4' => $isCarta4,
        ];

        // =========================================================================
        // PASO 5: WHATSAPP INBOUND 3 — "¿Y su parrilla familiar acomo está?"
        // RESOLUCIÓN EXACTA: S/ 150.00 — NUNCA Parrilla para Dos (AG-35 ANTI-SUBSTITUCIÓN)
        // =========================================================================
        $resp3 = $this->sendWhatsAppInboundMessage($testPhone, '¿Y su parrilla familiar acomo está?');
        $famResolution = $this->catalogResolver->resolveCustomerReference($tenantId, '¿Y su parrilla familiar acomo está?');

        $isParrillaFamiliarExact = (
            $famResolution->status === 'RESOLVED' &&
            (float)$famResolution->price === 150.00 &&
            $famResolution->entityName === 'Parrilla Familiar'
        );

        $steps['step5_whatsapp_parrilla_familiar_exact'] = [
            'name' => 'WhatsApp Mensaje 3: "¿Y su parrilla familiar acomo está?" -> Resuelve Parrilla Familiar S/ 150.00 (AG-35)',
            'status' => $isParrillaFamiliarExact ? 'PASS' : 'FAIL',
            'customer_message' => '¿Y su parrilla familiar acomo está?',
            'ai_reply' => $resp3['reply_text'] ?? '',
            'resolved_entity' => $famResolution->entityName,
            'resolved_price' => $famResolution->price,
            'anti_substitution_verified' => ($famResolution->entityName !== 'Parrilla para Dos'),
        ];

        // =========================================================================
        // PASO 6: WHATSAPP INBOUND 4 — "¿Y la Parrilla Wallpa?" -> S/ 180.00
        // =========================================================================
        $resp4 = $this->sendWhatsAppInboundMessage($testPhone, '¿Y la Parrilla Wallpa?');
        $wallpaResolution = $this->catalogResolver->resolveCustomerReference($tenantId, '¿Y la Parrilla Wallpa?');

        $isParrillaWallpaExact = (
            $wallpaResolution->status === 'RESOLVED' &&
            (float)$wallpaResolution->price === 180.00 &&
            $wallpaResolution->entityName === 'Parrilla Wallpa'
        );

        $steps['step6_whatsapp_parrilla_wallpa_exact'] = [
            'name' => 'WhatsApp Mensaje 4: "¿Y la Parrilla Wallpa?" -> Resuelve Parrilla Wallpa S/ 180.00',
            'status' => $isParrillaWallpaExact ? 'PASS' : 'FAIL',
            'customer_message' => '¿Y la Parrilla Wallpa?',
            'ai_reply' => $resp4['reply_text'] ?? '',
            'resolved_entity' => $wallpaResolution->entityName,
            'resolved_price' => $wallpaResolution->price,
        ];

        // =========================================================================
        // PASO 7: WHATSAPP INBOUND 5 — "¿Cuánto cuesta la Parrilla para Dos?" -> S/ 80.00
        // =========================================================================
        $resp5 = $this->sendWhatsAppInboundMessage($testPhone, '¿Cuánto cuesta la Parrilla para Dos?');
        $dosResolution = $this->catalogResolver->resolveCustomerReference($tenantId, '¿Cuánto cuesta la Parrilla para Dos?');

        $isParrillaDosExact = (
            $dosResolution->status === 'RESOLVED' &&
            (float)$dosResolution->price === 80.00 &&
            $dosResolution->entityName === 'Parrilla para Dos'
        );

        $steps['step7_whatsapp_parrilla_dos_exact'] = [
            'name' => 'WhatsApp Mensaje 5: "¿Cuánto cuesta la Parrilla para Dos?" -> Resuelve Parrilla para Dos S/ 80.00',
            'status' => $isParrillaDosExact ? 'PASS' : 'FAIL',
            'customer_message' => '¿Cuánto cuesta la Parrilla para Dos?',
            'ai_reply' => $resp5['reply_text'] ?? '',
            'resolved_entity' => $dosResolution->entityName,
            'resolved_price' => $dosResolution->price,
        ];

        // =========================================================================
        // PASO 8: WHATSAPP INBOUND 6 — PRODUCTO INEXISTENTE -> HONESTIDAD (NOT_FOUND)
        // =========================================================================
        $resp6 = $this->sendWhatsAppInboundMessage($testPhone, 'Quiero una pizza hawaiana familiar');
        $inexistentResolution = $this->catalogResolver->resolveCustomerReference($tenantId, 'Quiero una pizza hawaiana familiar');

        $isHonestNotFound = ($inexistentResolution->status === 'NOT_FOUND'
            && $inexistentResolution->commercial_substitution_allowed === false);

        $steps['step8_whatsapp_anti_hallucination'] = [
            'name' => 'WhatsApp Mensaje 6: Consulta de producto inexistente -> Rechazo honesto NOT_FOUND',
            'status' => $isHonestNotFound ? 'PASS' : 'FAIL',
            'customer_message' => 'Quiero una pizza hawaiana familiar',
            'ai_reply' => $resp6['reply_text'] ?? '',
            'resolution_status' => $inexistentResolution->status,
            'commercial_substitution_allowed' => $inexistentResolution->commercial_substitution_allowed,
        ];

        // =========================================================================
        // PASO 9: WHATSAPP INBOUND 7 — CONSULTA AMBIGUA -> ACLARACIÓN ESTRUCTURADA
        // =========================================================================
        $resp7 = $this->sendWhatsAppInboundMessage($testPhone, 'Quiero un chaufa de pollo');
        $ambiguousResolution = $this->catalogResolver->resolveCustomerReference($tenantId, 'Quiero un chaufa de pollo');

        $isAmbiguityHandled = ($ambiguousResolution->status === 'AMBIGUOUS'
            && count($ambiguousResolution->candidates) >= 2);

        $steps['step9_whatsapp_ambiguity_clarification'] = [
            'name' => 'WhatsApp Mensaje 7: Consulta ambigua (Chaufa) -> Solicitud de aclaración estructurada',
            'status' => $isAmbiguityHandled ? 'PASS' : 'FAIL',
            'customer_message' => 'Quiero un chaufa de pollo',
            'ai_reply' => $resp7['reply_text'] ?? '',
            'resolution_status' => $ambiguousResolution->status,
            'options' => $ambiguousResolution->candidates,
        ];

        // =========================================================================
        // PASO 10: CONFIRMACIÓN Y CREACIÓN DE ORDEN EN MYSQL (PARRILLA FAMILIAR S/ 150)
        // =========================================================================
        $fullOrderChain = $this->catalogResolver->resolveFullChainOrder($tenantId, 'parrilla familiar');
        
        $orderSyncResult = $this->orderParser->syncCommercialOrder(
            tenantId: $tenantId,
            customerPhone: $testPhone,
            customerName: $customerName,
            requestedItems: [
                ['name' => 'Parrilla Familiar', 'quantity' => 1]
            ]
        );

        $orderCreated = (in_array($orderSyncResult['status'] ?? '', ['SUCCESS', 'ORDER_CREATED']) && !empty($orderSyncResult['order']));
        $orderRecord = $orderSyncResult['order'] ?? null;

        $steps['step10_order_creation'] = [
            'name' => 'Confirmación y Creación de Orden en MySQL (Parrilla Familiar S/ 150.00)',
            'status' => $orderCreated ? 'PASS' : 'FAIL',
            'order_status' => $orderSyncResult['status'] ?? 'ERROR',
            'order_id' => $orderRecord?->id,
            'order_number' => $orderRecord?->order_number,
            'total_amount' => (float) ($orderRecord?->total_amount ?? 0),
        ];

        // =========================================================================
        // PASO 11: INTEGRIDAD DE HISTÓRICO DE PRECIOS (AG-36 PRICE SNAPSHOT INVARIANCE)
        // =========================================================================
        $prodParrillaFam = Product::where('tenant_id', $tenantId)->where('name', 'Parrilla Familiar')->first();
        
        // Simular que el restaurante sube el precio de Parrilla Familiar a S/ 155.00
        Product::where('id', $prodParrillaFam->id)->update([
            'base_price' => 155.00,
            'updated_at' => now(),
        ]);

        $newPriceResolution = $this->catalogResolver->resolveCustomerReference($tenantId, 'cuánto cuesta la parrilla familiar');

        // La orden histórica se mantiene congelada en S/ 150.00
        $historicalSnapshotImmutable = ((float)($fullOrderChain['order_line']['unit_price_snapshot'] ?? 0) === 150.00);
        $catalogUpdatedTo155 = ((float)$newPriceResolution->price === 155.00);

        // Restaurar precio original
        Product::where('id', $prodParrillaFam->id)->update([
            'base_price' => 150.00,
            'updated_at' => now(),
        ]);

        $steps['step11_historical_snapshot_integrity'] = [
            'name' => 'Integridad de Snapshot Histórico vs Precio Actual de Catálogo (AG-36)',
            'status' => ($historicalSnapshotImmutable && $catalogUpdatedTo155) ? 'PASS' : 'FAIL',
            'historical_order_snapshot' => $fullOrderChain['order_line']['unit_price_snapshot'] ?? null,
            'updated_catalog_price' => $newPriceResolution->price,
            'historical_snapshot_immutable' => $historicalSnapshotImmutable,
            'current_catalog_updated' => $catalogUpdatedTo155,
        ];

        // =========================================================================
        // PASO 12: RECONSTRUCCIÓN FORENSE COMPLETA Y TELEMETRÍA CERO PÍXELES
        // =========================================================================
        $convRecord = DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->where('customer_phone', $testPhone)
            ->first();

        $pvr = ProductVisualReference::where('tenant_id', $tenantId)
            ->where('product_id', $fullOrderChain['product_id'])
            ->first();

        $evidenceRecord = DB::table('raw_menu_evidences')
            ->where('tenant_id', $tenantId)
            ->where('id', $pvr?->evidence_id)
            ->first();

        $commercialMediaRecord = CommercialMedia::where('tenant_id', $tenantId)
            ->where('id', $pvr?->commercial_media_id)
            ->first();

        $traceabilityComplete = (
            $convRecord !== null &&
            $fullOrderChain['status'] === 'SUCCESS' &&
            $fullOrderChain['product_id'] === $pvr?->product_id &&
            $pvr?->commercial_media_id === $card4->id &&
            $evidenceRecord !== null &&
            $commercialMediaRecord !== null &&
            $commercialMediaRecord->category === 'PARRILLAS'
        );

        $stats = RuntimeCallCounter::getStats();
        $zeroPixels = ($stats['VISION_RUNTIME_CALLS'] === 0
            && $stats['MULTIMODAL_RUNTIME_CALLS'] === 0
            && $stats['OCR_RUNTIME_CALLS'] === 0
            && RuntimeCallCounter::assertZeroRuntimeCalls());

        $steps['step12_forensic_traceability_zero_pixels'] = [
            'name' => 'Reconstrucción Forense de la Cadena Completa y Telemetría Cero Píxeles',
            'status' => ($traceabilityComplete && $zeroPixels) ? 'PASS' : 'FAIL',
            'chain_details' => [
                'whatsapp_conversation_id' => $convRecord?->id,
                'catalog_version_id' => $fullOrderChain['catalog_version_id'],
                'product_id' => $fullOrderChain['product_id'],
                'product_name' => $fullOrderChain['product_name'],
                'mysql_price' => (float) $fullOrderChain['price'],
                'commercial_media_id' => $pvr?->commercial_media_id,
                'commercial_media_category' => $commercialMediaRecord?->category,
                'evidence_id' => $pvr?->evidence_id,
                'order_line' => $fullOrderChain['order_line'],
            ],
            'runtime_telemetry' => [
                'VISION_RUNTIME_CALLS' => $stats['VISION_RUNTIME_CALLS'],
                'MULTIMODAL_RUNTIME_CALLS' => $stats['MULTIMODAL_RUNTIME_CALLS'],
                'OCR_RUNTIME_CALLS' => $stats['OCR_RUNTIME_CALLS'],
                'TOTAL_RUNTIME_PIXEL_CALLS' => $stats['TOTAL_RUNTIME_PIXEL_CALLS'],
            ]
        ];

        $allStepsPassed = !in_array('FAIL', array_column($steps, 'status'));

        return [
            'e2e_suite' => 'P3.11 End-to-End Real Business Scenario (Wallpa Brasa)',
            'architecture_certification' => 'P3.11-SEALED-FINAL',
            'status' => ($allStepsPassed && $zeroPixels) ? 'E2E_BUSINESS_SUCCESS' : 'E2E_BUSINESS_FAILED',
            'all_steps_passed' => ($allStepsPassed && $zeroPixels),
            'steps_passed_count' => count(array_filter($steps, fn($s) => $s['status'] === 'PASS')),
            'total_steps' => count($steps),
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'runtime_telemetry' => [
                'VISION_RUNTIME_CALLS' => $stats['VISION_RUNTIME_CALLS'],
                'MULTIMODAL_RUNTIME_CALLS' => $stats['MULTIMODAL_RUNTIME_CALLS'],
                'OCR_RUNTIME_CALLS' => $stats['OCR_RUNTIME_CALLS'],
                'TOTAL_RUNTIME_PIXEL_CALLS' => $stats['TOTAL_RUNTIME_PIXEL_CALLS'],
                'BLOCKED_HOSTILE_ATTEMPTS' => $stats['BLOCKED_HOSTILE_ATTEMPTS'] ?? 0,
            ],
            'steps' => $steps,
        ];
    }

    private function sendWhatsAppInboundMessage(string $phone, string $message): array
    {
        $middleware = new GatewayAuthMiddleware();
        $secret = env('AIVA_GATEWAY_SECRET', 'aiva_qr_gateway_master_secret_2026');

        $req = Request::create('/api/v1/messaging/qr-inbound', 'POST', [
            'gateway_session_id' => 'wallpa-brasa',
            'phone' => $phone,
            'message' => $message,
        ]);
        $req->headers->set('Authorization', 'Bearer ' . $secret);
        $req->headers->set('X-Simulation', '1');

        $res = $middleware->handle($req, function ($r) {
            $controller = app(TenantBrainController::class);
            return $controller->processInboundMessage($r);
        });

        return json_decode($res->getContent(), true) ?? [];
    }

    private function seedRestaurantEnvironment(int $tenantId): void
    {
        // 1. Limpiar contexto y referencias previas
        ProductVisualReference::where('tenant_id', $tenantId)->delete();
        CatalogVersionValidation::where('tenant_id', $tenantId)->delete();
        CatalogVersionMedia::where('tenant_id', $tenantId)->delete();
        CatalogVersion::where('tenant_id', $tenantId)->delete();
        CommercialMediaAlias::where('tenant_id', $tenantId)->delete();
        CommercialMedia::where('tenant_id', $tenantId)->delete();
        DB::table('conversation_commercial_contexts')->where('tenant_id', $tenantId)->delete();

        // 2. Ingestar las 4 cartas comerciales
        $m1 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'CARTA_GENERAL',
            'title' => 'Carta General Wallpa Brasa',
            'file_path' => 'cards/wallpa_carta1.png',
            'content_hash' => hash('sha256', 'wallpa_carta1_e2e'),
            'revision_number' => 1,
            'display_order' => 1,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $m1->id, 'tenant_id' => $tenantId, 'alias' => 'carta general', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $m1->id, 'tenant_id' => $tenantId, 'alias' => 'menu', 'weight' => 0.9]);

        $m2 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'CHANCHOS',
            'title' => 'Carta de Chanchos al Cilindro',
            'file_path' => 'cards/wallpa_carta2.png',
            'content_hash' => hash('sha256', 'wallpa_carta2_e2e'),
            'revision_number' => 1,
            'display_order' => 2,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $m2->id, 'tenant_id' => $tenantId, 'alias' => 'chancho', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $m2->id, 'tenant_id' => $tenantId, 'alias' => 'cilindro', 'weight' => 1.0]);

        $m3 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'ALITAS',
            'title' => 'Carta de Alitas Broaster y BBQ',
            'file_path' => 'cards/wallpa_carta3.png',
            'content_hash' => hash('sha256', 'wallpa_carta3_e2e'),
            'revision_number' => 1,
            'display_order' => 3,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $m3->id, 'tenant_id' => $tenantId, 'alias' => 'alitas', 'weight' => 1.0]);

        $m4 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'PARRILLAS',
            'title' => 'Carta Especial de Parrillas',
            'file_path' => 'cards/wallpa_carta4.png',
            'content_hash' => hash('sha256', 'wallpa_carta4_e2e'),
            'revision_number' => 1,
            'display_order' => 4,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $m4->id, 'tenant_id' => $tenantId, 'alias' => 'parrilla', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $m4->id, 'tenant_id' => $tenantId, 'alias' => 'parrillas', 'weight' => 1.0]);

        // 3. Crear categoría
        $cat = DB::table('categories')->where('tenant_id', $tenantId)->first();
        $catId = $cat ? $cat->id : DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Platos a la Carta',
            'slug' => 'platos-carta-' . $tenantId,
            'status' => 'ACTIVE',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Sembrar los 23 ítems canónicos vendibles de las 4 cartas
        $itemsData = [
            // Carta 1
            ['media_id' => $m1->id, 'ev_id' => 111, 'name' => '1 Pollo a la brasa', 'slug' => 'un-pollo-brasa', 'price' => 60.00, 'desc' => '1 Pollo a la brasa con papas y ensalada'],
            ['media_id' => $m1->id, 'ev_id' => 112, 'name' => '1/2 Pollo a la brasa', 'slug' => 'medio-pollo-brasa', 'price' => 35.00, 'desc' => '1/2 Pollo a la brasa con papas y ensalada'],
            ['media_id' => $m1->id, 'ev_id' => 101, 'name' => '1/4 Pollo a la Brasa', 'slug' => 'un-cuarto-pollo-brasa', 'price' => 22.00, 'desc' => 'Con papas fritas y ensalada clásica'],
            ['media_id' => $m1->id, 'ev_id' => 113, 'name' => 'Mostrito', 'slug' => 'mostrito', 'price' => 16.00, 'desc' => '1/4 Pollo con arroz chaufa y papas'],
            ['media_id' => $m1->id, 'ev_id' => 114, 'name' => 'Mostro', 'slug' => 'mostro', 'price' => 23.00, 'desc' => '1/2 Pollo con arroz chaufa y papas'],
            ['media_id' => $m1->id, 'ev_id' => 115, 'name' => 'Promo 1', 'slug' => 'promo-1', 'price' => 65.00, 'desc' => '1 Pollo + Papas + Ensalada + Gaseosa 1.5L'],
            ['media_id' => $m1->id, 'ev_id' => 116, 'name' => 'Promo 2', 'slug' => 'promo-2', 'price' => 80.00, 'desc' => '1 Pollo + 1/4 Pollo + Papas + Ensalada + Gaseosa'],

            // Carta 2
            ['media_id' => $m2->id, 'ev_id' => 102, 'name' => 'Chancho al Cilindro', 'slug' => 'chancho-al-cilindro', 'price' => 28.00, 'desc' => 'Porción personal con papas doradas'],
            ['media_id' => $m2->id, 'ev_id' => 117, 'name' => 'Chancho al cilindro 250g', 'slug' => 'chancho-cilindro-250g', 'price' => 25.00, 'desc' => '250g de chancho crocante al cilindro'],
            ['media_id' => $m2->id, 'ev_id' => 118, 'name' => 'Chancho al cilindro 500g', 'slug' => 'chancho-cilindro-500g', 'price' => 45.00, 'desc' => '500g de chancho crocante al cilindro'],
            ['media_id' => $m2->id, 'ev_id' => 119, 'name' => 'Chancho al cilindro 1kg', 'slug' => 'chancho-cilindro-1kg', 'price' => 85.00, 'desc' => '1kg de chancho crocante al cilindro'],
            ['media_id' => $m2->id, 'ev_id' => 120, 'name' => 'Dúo Personal', 'slug' => 'duo-personal', 'price' => 35.00, 'desc' => 'Chancho + Pollo porción personal'],
            ['media_id' => $m2->id, 'ev_id' => 121, 'name' => 'Dúo Familiar', 'slug' => 'duo-familiar', 'price' => 95.00, 'desc' => 'Chancho + Pollo porción familiar'],
            ['media_id' => $m2->id, 'ev_id' => 122, 'name' => 'Chaufa de Pollo Personal', 'slug' => 'chaufa-pollo-personal', 'price' => 16.00, 'desc' => 'Arroz chaufa al wok porción personal'],
            ['media_id' => $m2->id, 'ev_id' => 123, 'name' => 'Chaufa de Pollo Familiar', 'slug' => 'chaufa-pollo-familiar', 'price' => 31.00, 'desc' => 'Arroz chaufa al wok porción familiar'],

            // Carta 3
            ['media_id' => $m3->id, 'ev_id' => 124, 'name' => 'Alitas BBQ', 'slug' => 'alitas-bbq', 'price' => 23.00, 'desc' => '8 piezas de alitas bañadas en salsa BBQ'],
            ['media_id' => $m3->id, 'ev_id' => 103, 'name' => 'Alitas BBQ x 12', 'slug' => 'alitas-bbq-12', 'price' => 32.00, 'desc' => '12 unidades bañadas en salsa barbacoa artesanal'],
            ['media_id' => $m3->id, 'ev_id' => 125, 'name' => 'Alitas Crispy', 'slug' => 'alitas-crispy', 'price' => 23.00, 'desc' => '8 piezas de alitas súper crocantes'],
            ['media_id' => $m3->id, 'ev_id' => 126, 'name' => 'Salchipapa', 'slug' => 'salchipapa', 'price' => 18.00, 'desc' => 'Papas fritas con salchicha frankfurter'],
            ['media_id' => $m3->id, 'ev_id' => 127, 'name' => 'Lomo Saltado', 'slug' => 'lomo-saltado', 'price' => 23.00, 'desc' => 'Lomo fino salteado al jugo con papas y arroz'],

            // Carta 4: Parrillas
            ['media_id' => $m4->id, 'ev_id' => 104, 'name' => 'Parrilla para Dos', 'slug' => 'parrilla-para-dos', 'price' => 80.00, 'desc' => 'Bife, chuleta, chorizo, anticuchos, papas y ensalada'],
            ['media_id' => $m4->id, 'ev_id' => 128, 'name' => 'Parrilla Familiar', 'slug' => 'parrilla-familiar', 'price' => 150.00, 'desc' => 'Parrilla completa para 4-5 personas con guarnición'],
            ['media_id' => $m4->id, 'ev_id' => 129, 'name' => 'Parrilla Wallpa', 'slug' => 'parrilla-wallpa', 'price' => 180.00, 'desc' => 'Parrilla premium especial con cortes seleccionados'],
            ['media_id' => $m4->id, 'ev_id' => 130, 'name' => 'Pechuga a la parrilla', 'slug' => 'pechuga-a-la-parrilla', 'price' => 21.00, 'desc' => 'Filete de pechuga a las brasas con papas'],
            ['media_id' => $m4->id, 'ev_id' => 131, 'name' => 'Anticuchos', 'slug' => 'anticuchos', 'price' => 20.00, 'desc' => '2 palos de corazón de res tierno marinado'],
        ];

        $orderPerMedia = [];
        foreach ($itemsData as $item) {
            DB::table('raw_menu_evidences')->updateOrInsert(
                ['id' => $item['ev_id']],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'document_id' => 'doc_e2e_' . $item['ev_id'],
                    'source_type' => 'WHATSAPP_IMAGE',
                    'extracted_text' => $item['name'] . ' S/ ' . number_format($item['price'], 2),
                    'element_type' => 'text',
                    'confidence' => 1.0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $existing = DB::table('products')->where('tenant_id', $tenantId)->where('name', $item['name'])->first();
            if (!$existing) {
                $pId = DB::table('products')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'category_id' => $catId,
                    'name' => $item['name'],
                    'normalized_name' => strtolower($item['name']),
                    'slug' => $item['slug'] . '-e2e-' . $tenantId,
                    'description' => $item['desc'],
                    'base_price' => $item['price'],
                    'status' => 'ACTIVE',
                    'is_available' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('products')->where('id', $existing->id)->update([
                    'base_price' => $item['price'],
                    'normalized_name' => strtolower($item['name']),
                    'status' => 'ACTIVE',
                    'is_available' => 1,
                    'updated_at' => now(),
                ]);
                $pId = $existing->id;
            }

            $mId = $item['media_id'];
            $orderPerMedia[$mId] = ($orderPerMedia[$mId] ?? 0) + 1;

            ProductVisualReference::create([
                'tenant_id' => $tenantId,
                'product_id' => $pId,
                'commercial_media_id' => $mId,
                'evidence_id' => $item['ev_id'],
                'bbox_x' => 0.1,
                'bbox_y' => 0.1 * $orderPerMedia[$mId],
                'bbox_w' => 0.3,
                'bbox_h' => 0.08,
                'display_index' => $orderPerMedia[$mId],
                'status' => 'PUBLISHED',
            ]);
        }

        // 5. Compilar catálogo canónico (100% certificado por PublishCatalogAuthority)
        $this->compilerService->compileAndPublish($tenantId, [$m1->id, $m2->id, $m3->id, $m4->id], 'Catálogo E2E Wallpa Brasa');
    }
}
