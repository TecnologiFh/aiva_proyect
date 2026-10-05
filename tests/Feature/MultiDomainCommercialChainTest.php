<?php

namespace Tests\Feature;

use App\Modules\Multimodal\Runtime\Adapters\PaymentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\Adapters\VisualCatalogAdapter;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\DTOs\VisualEntityDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use App\Modules\Orders\Services\OrderCommercialTransitionPolicyService;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use App\Modules\Orders\Services\TechnicalCompatibilityResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Multi-Domain Commercial & Technical Synergy Test Suite.
 * 
 * Valida la cadena integral unificada en producción:
 * P3.12-R (Percepción Multimodal) + AG-46 (Autoridad de Catálogo y Compatibilidad Técnica) + P3.13 (Integridad Operativa y Financiera).
 * 
 * Dominios evaluados:
 * - Tenant 12: Carenados Motos (Catálogo complejo con generaciones por año y piezas compuestas).
 * - Tenant 13: Autopartes del Perú / Frenos (Catálogo con posiciones de montaje y atributos técnicos de fricción).
 */
class MultiDomainCommercialChainTest extends TestCase
{
    private TechnicalCompatibilityResolverService $techResolver;
    private VisualCatalogAdapter $visualCatalogAdapter;
    private OrderCommercialTransitionPolicyService $policyService;
    private OrderStateMachineEngine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->techResolver = new TechnicalCompatibilityResolverService();
        $this->visualCatalogAdapter = new VisualCatalogAdapter();
        $this->policyService = new OrderCommercialTransitionPolicyService();
        $this->stateMachine = new OrderStateMachineEngine();
    }

    /**
     * Prueba 1: Carenados Motos - Resolución determinista por modelo y rango de años de fabricación.
     */
    public function test_carenados_motos_resolves_exact_model_and_year_generation(): void
    {
        $tenantId = 12;

        // Generación 1: Honda CB190R 2016-2020 (Kit completo deportivo -> CAR-001, S/ 620.00)
        $queryGen1 = "Quiero el kit completo de carenado para Honda CB190R 2018";
        $resGen1 = $this->techResolver->resolve($tenantId, $queryGen1);

        $this->assertTrue($resGen1->isResolved());
        $candGen1 = $resGen1->getResolvedCandidate();
        $this->assertEquals('CAR-001', $candGen1['sku']);
        $this->assertEquals('620.00', $candGen1['base_price']);
        $this->assertEquals('Honda', $candGen1['compatibility']['brand']);
        $this->assertEquals('CB190R', $candGen1['compatibility']['model']);
        $this->assertEquals(2016, $candGen1['compatibility']['year_start']);
        $this->assertEquals(2020, $candGen1['compatibility']['year_end']);

        // Generación 2: Honda CB190R 2021-2024 (Kit completo urbano -> CAR-002, S/ 680.00)
        $queryGen2 = "Kit carenado urbano para Honda CB190R año 2022";
        $resGen2 = $this->techResolver->resolve($tenantId, $queryGen2);

        $this->assertTrue($resGen2->isResolved());
        $candGen2 = $resGen2->getResolvedCandidate();
        $this->assertEquals('CAR-002', $candGen2['sku']);
        $this->assertEquals('680.00', $candGen2['base_price']);
        $this->assertEquals(2021, $candGen2['compatibility']['year_start']);
        $this->assertEquals(2024, $candGen2['compatibility']['year_end']);
    }

    /**
     * Prueba 2: Carenados Motos - Prevalencia de catálogo AG-46 ante precio visual en conflicto.
     */
    public function test_carenados_motos_visual_price_conflict_ag46_prevails(): void
    {
        $tenantId = 12;

        // Cliente envía imagen donde la etiqueta/gráfico dice S/ 450.00 para el kit CB190R 2022
        $evidence = new StructuredVisualEvidenceDTO(
            tenantId: $tenantId,
            conversationId: 'conv_car_test_' . uniqid(),
            messageId: 'msg_car_' . uniqid(),
            mediaId: 'med_car_' . uniqid(),
            assetHash: 'hash_car_visual_audit',
            assetClassification: 'PRODUCT_IMAGE',
            detectedEvidence: [CommercialVisualEvidenceType::PRODUCT_ITEM->value],
            perceptionVersion: 'P3.12-R',
            source: 'WHATSAPP_INBOUND',
            observations: [],
            confidence: 0.95,
            createdAt: now()->toIso8601String(),
            correlationId: 'CORR-CAR-AUDIT',
            entities: [
                new VisualEntityDTO(
                    id: 'ent_car_01',
                    type: 'PRODUCT_ITEM',
                    name: 'Kit carenado urbano para Honda CB190R año 2022',
                    normalizedName: 'kit carenado urbano para honda cb190r ano 2022',
                    observedPrice: '450.00',
                    confidence: 0.90
                )
            ]
        );

        $reconciliation = $this->visualCatalogAdapter->reconcile($evidence, [
            'target_entity' => 'Kit carenado urbano para Honda CB190R año 2022',
        ]);

        // Verificaciones Constitucionales
        $this->assertEquals(VisualResolutionStatus::CONFLICT->value, $reconciliation['status']);
        $this->assertEquals('450.00', $reconciliation['observed_price']);
        $this->assertEquals('680.00', $reconciliation['authorized_price']);
        $this->assertEquals('680.00', $reconciliation['sale_price']); // Soberanía comercial
        $this->assertTrue($reconciliation['has_conflict']);
        $this->assertStringContainsString('680.00', $reconciliation['audit_note']);
    }

    /**
     * Prueba 3: Carenados Motos - Detección de ambigüedad técnica cuando no se especifica la pieza.
     */
    public function test_carenados_motos_disambiguation_when_piece_unspecified(): void
    {
        $tenantId = 12;

        // Cliente solo dice "carenado para Honda CB190R 2018" sin aclarar si quiere frontal, colín o kit completo
        $query = "Quiero un carenado para Honda CB190R 2018";
        $res = $this->techResolver->resolve($tenantId, $query);

        $this->assertTrue($res->isAmbiguous());
        $this->assertEquals('SIDE_UNSPECIFIED', $res->ambiguityReason);
        $this->assertNotEmpty($res->compatibleCandidates);
        $this->assertGreaterThanOrEqual(2, count($res->compatibleCandidates));
    }

    /**
     * Prueba 4: Autopartes del Perú - Diferenciación técnica de pastillas delanteras vs traseras y discos.
     */
    public function test_autopartes_frenos_resolves_front_and_rear_pads(): void
    {
        $tenantId = 13;

        // Caso A: Pastillas delanteras Corolla 2018 -> PA-TOY-001 (S/ 180.00)
        $queryFront = "Pastillas de freno delanteras para Toyota Corolla 2018";
        $resFront = $this->techResolver->resolve($tenantId, $queryFront);

        $this->assertTrue($resFront->isResolved());
        $candFront = $resFront->getResolvedCandidate();
        $this->assertEquals('PA-TOY-001', $candFront['sku']);
        $this->assertEquals('180.00', $candFront['base_price']);
        $this->assertEquals('FRONT', $candFront['attributes']['side']);

        // Caso B: Pastillas traseras Corolla 2018 -> PA-TOY-002 (S/ 160.00)
        $queryRear = "Pastillas de freno traseras para Toyota Corolla 2018";
        $resRear = $this->techResolver->resolve($tenantId, $queryRear);

        $this->assertTrue($resRear->isResolved());
        $candRear = $resRear->getResolvedCandidate();
        $this->assertEquals('PA-TOY-002', $candRear['sku']);
        $this->assertEquals('160.00', $candRear['base_price']);

        // Caso C: Disco de freno ventilado Corolla 2019 -> DI-TOY-001 (S/ 240.00)
        $queryDisc = "Disco de freno ventilado para Toyota Corolla 2019";
        $resDisc = $this->techResolver->resolve($tenantId, $queryDisc);

        $this->assertTrue($resDisc->isResolved());
        $candDisc = $resDisc->getResolvedCandidate();
        $this->assertEquals('DI-TOY-001', $candDisc['sku']);
        $this->assertEquals('240.00', $candDisc['base_price']);
    }

    /**
     * Prueba 5: Autopartes del Perú - Detección de ambigüedad cuando se omite la posición de montaje.
     */
    public function test_autopartes_frenos_disambiguation_between_mounting_positions(): void
    {
        $tenantId = 13;

        // Cliente pregunta por pastillas Sentra 2020 sin indicar delantero o trasero
        $query = "Pastillas de freno para Nissan Sentra 2020";
        $res = $this->techResolver->resolve($tenantId, $query);

        $this->assertTrue($res->isAmbiguous());
        $this->assertEquals('SIDE_UNSPECIFIED', $res->ambiguityReason);
        $this->assertCount(2, $res->compatibleCandidates);

        $skus = collect($res->compatibleCandidates)->pluck('sku')->toArray();
        $this->assertContains('PA-NIS-001', $skus); // Delantero
        $this->assertContains('PA-NIS-002', $skus); // Trasero

        // Pregunta automática para el asistente de ventas
        $this->assertStringContainsString('delanteras o traseras', $res->requiredDisambiguation);
    }

    /**
     * Prueba 6: Cadena Completa E2E:
     * AG-46 (Resolución de repuesto) -> Creación de Pedido -> P3.12-R (Comprobante Yape) -> P2.11 (Financiero) -> P3.13 (Máquina de Estados)
     */
    public function test_end_to_end_multimodal_commercial_and_financial_chain(): void
    {
        $tenantId = 13; // Autopartes del Perú

        // Paso 1: Resolución técnica autorizada de catálogo
        $res = $this->techResolver->resolve($tenantId, "Pastillas de freno delanteras para Toyota Corolla 2018");
        $this->assertTrue($res->isResolved());
        $part = $res->getResolvedCandidate();

        $totalAmount = (float) $part['base_price']; // 180.00
        $orderNumber = 'ORD-E2E-AUTO-' . uniqid();

        // Paso 2: Creación de la orden en estado inicial
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => $orderNumber,
            'customer_name' => 'Taller Mecánico Lima',
            'customer_phone' => '955443322',
            'delivery_address' => null,
            'delivery_zone' => null,
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'product_id' => $part['id'],
            'product_name' => $part['name'],
            'unit_price' => $totalAmount,
            'quantity' => 1,
            'subtotal' => $totalAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Paso 3: P3.12-R Percepción Multimodal recibe comprobante de pago de S/ 180.00
        $normalizer = new VisualEvidenceNormalizationService();
        $adapter = new PaymentEvidenceAdapter($normalizer);

        $evidence = new StructuredVisualEvidenceDTO(
            tenantId: $tenantId,
            conversationId: 'conv_e2e_auto_' . uniqid(),
            messageId: 'msg_e2e_' . uniqid(),
            mediaId: 'med_e2e_01',
            assetHash: 'hash_voucher_toyota_180',
            assetClassification: CommercialVisualEvidenceType::PAYMENT_RECEIPT->value,
            detectedEvidence: [CommercialVisualEvidenceType::PAYMENT_RECEIPT->value],
            perceptionVersion: 'P3.12-R',
            source: 'WHATSAPP_INBOUND',
            observations: [
                'provider' => 'YAPE',
                'amount' => '180.00',
                'operation_number' => 'OP-YAPE-E2E-AUTO',
            ],
            confidence: 0.98,
            createdAt: now()->toIso8601String(),
            correlationId: 'CORR-E2E-AUTO'
        );

        // Reconciliación en Adapter (Sin mutar DB)
        $paymentRecon = $adapter->reconcile($evidence, [
            'order_id' => $orderId,
            'expected_amount' => '180.00',
            'order' => ['id' => $orderId, 'total' => '180.00'],
        ]);

        $this->assertTrue($paymentRecon['is_approved']);

        // Verificamos que DB aún sigue intacta (Principio P3.13-01)
        $orderBefore = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('AWAITING_PAYMENT', $orderBefore->status);
        $this->assertEquals('PENDING', $orderBefore->payment_status);

        // Paso 4: P2.11 emite resultado financiero canónico -> Orquestado por Policy Service
        $canonicalFinancialResult = [
            'status' => 'APPROVED',
            'is_approved' => true,
            'paid_amount' => '180.00',
            'expected_amount' => '180.00',
            'pending_amount' => '0.00',
            'operation_number' => 'OP-YAPE-E2E-AUTO',
            'provider' => 'YAPE',
        ];

        $policyResult = $this->policyService->processPaymentResolution($tenantId, $orderId, $canonicalFinancialResult);

        // Verificación de Estados Ortogonales (P3.13-02, P3.13-03, P3.13-04)
        $this->assertEquals('PAID', $policyResult['financial_status']);
        $this->assertEquals('CONFIRMED', $policyResult['commercial_status']);
        $this->assertEquals('PENDING', $policyResult['fulfillment_status']); // No tiene dirección confirmada todavía

        $orderPaid = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('CONFIRMED', $orderPaid->status);
        $this->assertEquals('PAID', $orderPaid->payment_status);
        $this->assertEquals('PENDING', $orderPaid->fulfillment_status);

        // Paso 5: El cliente ahora confirma su dirección de entrega en el taller
        $deliveryResult = $this->policyService->processDeliveryConfirmation(
            $tenantId,
            $orderId,
            'Av. República de Panamá 4567',
            'Surquillo'
        );

        $this->assertEquals('PREPARING', $deliveryResult['fulfillment_status']);
        $this->assertTrue($deliveryResult['fulfillment_transition_executed']);

        // Verificación final en base de datos
        $orderFinal = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('CONFIRMED', $orderFinal->status);
        $this->assertEquals('PAID', $orderFinal->payment_status);
        $this->assertEquals('PREPARING', $orderFinal->fulfillment_status);
        $this->assertEquals('Av. República de Panamá 4567', $orderFinal->delivery_address);
        $this->assertEquals('Surquillo', $orderFinal->delivery_zone);
    }
}
