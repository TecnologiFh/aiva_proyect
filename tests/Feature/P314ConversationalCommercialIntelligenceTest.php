<?php

namespace Tests\Feature;

use App\Modules\Messaging\Services\ConversationStateManager;
use App\Modules\Multimodal\Runtime\Adapters\PaymentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use App\Modules\Orders\Services\OrderCommercialTransitionPolicyService;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use App\Modules\Orders\Services\TechnicalCompatibilityResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3.14 — Conversational Commercial Intelligence Test Suite.
 * 
 * Verifica las 9 Reglas Constitucionales de P3.14:
 * P3.14-01: Conversational Authority Boundary
 * P3.14-02: Technical Clarification State & Candidate Scope
 * P3.14-03: Context Boundary Integrity (P3.14-CTX-01)
 * P3.14-04: Clarification Priority Rule
 * P3.14-05: Contradiction Replacement
 * P3.14-06: Context Expiration (2 turnos o 10 min TTL)
 * P3.14-07: Tenant-Isolated Conversation Memory (P3.14-TENANT-01)
 * P3.14-08: No-Guess Clarification Terminal
 * P3.14-09: Resolver Authority Preservation
 */
class P314ConversationalCommercialIntelligenceTest extends TestCase
{
    private ConversationStateManager $stateManager;
    private TechnicalCompatibilityResolverService $techResolver;
    private CatalogReferenceResolverService $catalogResolver;
    private OrderCommercialTransitionPolicyService $policyService;
    private OrderStateMachineEngine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateManager = new ConversationStateManager();
        $this->techResolver = new TechnicalCompatibilityResolverService();
        $this->catalogResolver = new CatalogReferenceResolverService();
        $this->policyService = new OrderCommercialTransitionPolicyService();
        $this->stateMachine = new OrderStateMachineEngine();
    }

    /**
     * Caso 1: Continuidad Multi-Turno Carenados Motos (Invariante P3.14-01 & P3.14-02).
     * Turno 1: Ambigüedad con verificación de estado interno y atributo dominante.
     * Turno 2: Fusión de contexto y resolución autorizada.
     */
    public function test_case_1_multi_turn_continuity_carenados_motos(): void
    {
        $tenantId = 12;
        $convId = 'conv_p314_c1_' . uniqid();

        // Turno 1: Cliente consulta carenados para CB190R sin especificar pieza
        $queryTurn1 = "Necesito carenado para mi Honda CB190R";
        $res1 = $this->techResolver->resolve($tenantId, $queryTurn1);

        $this->assertTrue($res1->isAmbiguous());
        $this->assertEquals('SIDE_UNSPECIFIED', $res1->ambiguityReason);

        // Registro de estado formal en ConversationStateManager
        $state1 = $this->stateManager->setPendingTechnicalClarification($tenantId, $convId, [
            'status' => 'AMBIGUOUS',
            'intent' => 'TECHNICAL_INQUIRY',
            'entities' => $res1->normalizedEntities,
            'ambiguity_reason' => $res1->ambiguityReason,
            'candidates' => $res1->compatibleCandidates,
            'candidate_scope' => $res1->compatibleCandidates,
            'disambiguation_prompt' => $res1->requiredDisambiguation,
        ]);

        // Aserciones de Estado Interno Constitucional
        $this->assertEquals('Honda', $state1['entities']['brand']);
        $this->assertEquals('CB190R', $state1['entities']['model']);
        $this->assertNull($state1['entities']['year']);
        $this->assertEquals('piece', $state1['dominant_clarification_attribute']);
        $this->assertNotEmpty($state1['candidate_scope']);

        // Turno 2: Cliente responde aclarando: "Quiero el kit completo año 2018"
        $this->stateManager->incrementTurn($tenantId, $convId);
        $activeClarif = $this->stateManager->getValidTechnicalClarification($tenantId, $convId, 'TECHNICAL_INQUIRY');
        $this->assertNotNull($activeClarif);

        $contextTurn2 = $activeClarif['entities'];
        $contextTurn2['candidate_scope'] = $activeClarif['candidate_scope'];

        $res2 = $this->techResolver->resolve($tenantId, "Quiero el kit completo año 2018", $contextTurn2);

        $this->assertTrue($res2->isResolved());
        $part = $res2->getResolvedCandidate();
        $this->assertEquals('CAR-001', $part['sku']);
        $this->assertEquals('620.00', $part['base_price']);
        $this->assertEquals('Honda', $part['compatibility']['brand']);
        $this->assertEquals('CB190R', $part['compatibility']['model']);
    }

    /**
     * Caso 2: Herencia Técnica y Candidate Scope en Autopartes (Invariante P3.14-02).
     * Turno 1: Sentra 2020 genera candidate_scope acotado a pastillas delanteras y traseras.
     * Turno 2: "Delanteras por favor" resuelve dentro del candidate_scope sin búsqueda global.
     */
    public function test_case_2_multi_turn_technical_inheritance_autopartes(): void
    {
        $tenantId = 13;
        $convId = 'conv_p314_c2_' . uniqid();

        // Turno 1: Consulta sin posición de montaje
        $query1 = "Pastillas de freno para Nissan Sentra 2020";
        $res1 = $this->techResolver->resolve($tenantId, $query1);

        $this->assertTrue($res1->isAmbiguous());
        $this->assertCount(2, $res1->compatibleCandidates);

        $this->stateManager->setPendingTechnicalClarification($tenantId, $convId, [
            'status' => 'AMBIGUOUS',
            'intent' => 'TECHNICAL_INQUIRY',
            'entities' => $res1->normalizedEntities,
            'ambiguity_reason' => $res1->ambiguityReason,
            'candidate_scope' => $res1->compatibleCandidates,
            'candidates' => $res1->compatibleCandidates,
            'disambiguation_prompt' => $res1->requiredDisambiguation,
        ]);

        // Turno 2: "Delanteras por favor"
        $this->stateManager->incrementTurn($tenantId, $convId);
        $clarif = $this->stateManager->getValidTechnicalClarification($tenantId, $convId, 'TECHNICAL_INQUIRY');
        $this->assertNotNull($clarif);

        $context2 = $clarif['entities'];
        $context2['candidate_scope'] = $clarif['candidate_scope'];

        $res2 = $this->techResolver->resolve($tenantId, "Delanteras por favor", $context2);

        $this->assertTrue($res2->isResolved());
        $part = $res2->getResolvedCandidate();
        $this->assertEquals('PA-NIS-001', $part['sku']);
        $this->assertEquals('175.00', $part['base_price']);
        $this->assertEquals('FRONT', $part['attributes']['side']);
    }

    /**
     * Caso 3: Regla de Prioridad de Clarificación (Invariante P3.14-04).
     * Formula una sola pregunta dominante por turno evitando cuestionarios tipo formulario.
     */
    public function test_case_3_clarification_priority_rule(): void
    {
        // Evaluación con incertidumbre de posición
        $attrSide = $this->stateManager->determineDominantAttribute(['ambiguity_reason' => 'SIDE_UNSPECIFIED']);
        $this->assertEquals('piece', $attrSide);

        // Evaluación con incertidumbre de año
        $attrYear = $this->stateManager->determineDominantAttribute(['ambiguity_reason' => 'YEAR_REQUIRED_UNKNOWN_IN_CATALOG']);
        $this->assertEquals('year', $attrYear);

        // Evaluación con incertidumbre de color
        $attrColor = $this->stateManager->determineDominantAttribute(['ambiguity_reason' => 'COLOR_AMBIGUOUS']);
        $this->assertEquals('color', $attrColor);
    }

    /**
     * Caso 4: Límite de Contexto por Cambio de Intención Semántica (Invariante P3.14-03 / P3.14-CTX-01).
     * Una consulta sobre aceite o ubicación NO arrastra atributos técnicos de frenos (ej. FRONT).
     */
    public function test_case_4_context_boundary_integrity_on_intent_shift(): void
    {
        $tenantId = 13;
        $convId = 'conv_p314_c4_' . uniqid();

        // Se registra contexto previo de frenos FRONT
        $this->stateManager->setPendingTechnicalClarification($tenantId, $convId, [
            'status' => 'AMBIGUOUS',
            'intent' => 'TECHNICAL_INQUIRY',
            'entity_type' => 'BRAKE_PAD',
            'entities' => [
                'brand' => 'Nissan',
                'model' => 'Sentra',
                'year' => 2020,
                'side' => 'FRONT',
            ],
            'candidate_scope' => [['id' => 999]],
        ]);

        // Consulta incompatible: Pregunta por horario o ubicación
        $clarifLocation = $this->stateManager->getValidTechnicalClarification($tenantId, $convId, 'LOCATION_INQUIRY');
        $this->assertNull($clarifLocation); // No hereda a intent incompatible

        // Consulta en dominio semántico incompatible (LUBRICANT vs BRAKE_PAD)
        $clarifOil = $this->stateManager->getValidTechnicalClarification($tenantId, $convId, 'TECHNICAL_INQUIRY', 'LUBRICANT');
        $this->assertNull($clarifOil); // No arrastra candidate_scope de frenos a lubricantes
    }

    /**
     * Caso 5: Sustitución Atómica de Atributo (Invariante P3.14-05).
     * Turno 1: CB190R 2018 -> Turno 2: "Perdón, es para una 2022" -> Invalida 2018, activa 2022.
     */
    public function test_case_5_contradiction_attribute_replacement(): void
    {
        $tenantId = 12;
        $convId = 'conv_p314_c5_' . uniqid();

        $existing = [
            'brand' => 'Honda',
            'model' => 'CB190R',
            'year' => 2018,
            'presentation' => 'Kit completo',
        ];

        // Cliente corrige el año
        $correction = [
            'year' => 2022,
        ];

        $replaced = $this->stateManager->applyContradictionReplacement($existing, $correction);

        $this->assertEquals('ATTRIBUTE_REPLACEMENT', $replaced['contradiction_type']);
        $this->assertEquals(2022, $replaced['year']);
        $this->assertEquals('Honda', $replaced['brand']);
        $this->assertEquals('CB190R', $replaced['model']);

        // Al resolver contra AG-46 debe dar el kit de nueva generación CAR-002
        $res = $this->techResolver->resolve($tenantId, "Kit carenado urbano", $replaced);
        $this->assertTrue($res->isResolved());
        $this->assertEquals('CAR-002', $res->getResolvedCandidate()['sku']);
    }

    /**
     * Caso 6: Sustitución de Entidad Raíz (Invariante P3.14-05).
     * Turno 1: Honda CB190R -> Turno 2: "Me equivoqué, es Yamaha FZ" -> Resetea atributos incompatibles de CB190R.
     */
    public function test_case_6_contradiction_root_entity_replacement(): void
    {
        $existing = [
            'brand' => 'Honda',
            'model' => 'CB190R',
            'year' => 2018,
            'side' => 'FRONT',
            'presentation' => 'Kit completo',
        ];

        // Cliente cambia de moto a Yamaha FZ
        $newEntity = [
            'brand' => 'Yamaha',
            'model' => 'FZ 2.0',
        ];

        $replaced = $this->stateManager->applyContradictionReplacement($existing, $newEntity);

        $this->assertEquals('ROOT_ENTITY_REPLACEMENT', $replaced['contradiction_type']);
        $this->assertEquals('Yamaha', $replaced['brand']);
        $this->assertEquals('FZ 2.0', $replaced['model']);
        // Los atributos dependientes de CB190R fueron limpiados para evitar contaminación
        $this->assertNull($replaced['year']);
        $this->assertNull($replaced['side']);
    }

    /**
     * Caso 7: Expiración Estricta de Contexto (Invariante P3.14-06).
     * turns_since_source > 2 OR now > expires_at (el primero que ocurra invalida el estado).
     */
    public function test_case_7_strict_context_expiration(): void
    {
        $tenantId = 12;
        $convId = 'conv_p314_c7_' . uniqid();

        // 1. Expiración por número de turnos (> 2 turnos)
        $this->stateManager->setPendingTechnicalClarification($tenantId, $convId, [
            'status' => 'AMBIGUOUS',
            'entities' => ['brand' => 'Honda', 'model' => 'CB190R'],
        ]);

        $this->stateManager->incrementTurn($tenantId, $convId); // Turno 1
        $this->assertNotNull($this->stateManager->getValidTechnicalClarification($tenantId, $convId));

        $this->stateManager->incrementTurn($tenantId, $convId); // Turno 2
        $this->assertNotNull($this->stateManager->getValidTechnicalClarification($tenantId, $convId));

        $this->stateManager->incrementTurn($tenantId, $convId); // Turno 3 (> 2 turnos vividos)
        $this->assertNull($this->stateManager->getValidTechnicalClarification($tenantId, $convId));

        // 2. Expiración por tiempo (TTL 10 minutos)
        $convIdTime = 'conv_p314_c7_time_' . uniqid();
        $this->stateManager->setPendingTechnicalClarification($tenantId, $convIdTime, [
            'status' => 'AMBIGUOUS',
            'entities' => ['brand' => 'Honda', 'model' => 'CB190R'],
        ]);

        // Simular paso del tiempo manipulando expires_at en el caché
        $state = $this->stateManager->getState($tenantId, $convIdTime);
        $state['pending_technical_clarification']['expires_at'] = time() - 10; // Expirado hace 10 seg
        $this->stateManager->saveState($tenantId, $convIdTime, $state);

        $this->assertNull($this->stateManager->getValidTechnicalClarification($tenantId, $convIdTime));
    }

    /**
     * Caso 8: Aislamiento Multi-Tenant de Memoria Conversacional (Invariante P3.14-07 / P3.14-TENANT-01).
     * Mismo teléfono, mismo conversation_id, mismo número de turno y mismos candidate IDs en dos tenants
     * demuestran que el aislamiento depende de la identidad contextual real (tenant_id) y no de coincidencias del contenido.
     */
    public function test_case_8_tenant_isolated_conversation_memory(): void
    {
        $sharedConvId = 'conv_shared_phone_999888777';

        // Tenant 12: Registra Honda CB190R 2018 con candidate_scope simulado [101, 102]
        $this->stateManager->setPendingTechnicalClarification(12, $sharedConvId, [
            'status' => 'AMBIGUOUS',
            'intent' => 'TECHNICAL_INQUIRY',
            'entities' => ['brand' => 'Honda', 'model' => 'CB190R', 'year' => '2018'],
            'candidate_scope' => [101, 102],
        ]);

        // Tenant 13: Registra Nissan Sentra 2020 con idéntico ID de conversación y candidate_scope [101, 103]
        $this->stateManager->setPendingTechnicalClarification(13, $sharedConvId, [
            'status' => 'AMBIGUOUS',
            'intent' => 'TECHNICAL_INQUIRY',
            'entities' => ['brand' => 'Nissan', 'model' => 'Sentra', 'year' => '2020'],
            'candidate_scope' => [101, 103],
        ]);

        // Avanzamos turno idéntico en ambos tenants
        $this->stateManager->incrementTurn(12, $sharedConvId);
        $this->stateManager->incrementTurn(13, $sharedConvId);

        // Consulta cruzada para Tenant 12 con mismo texto de consulta: "Quiero confirmar"
        $clarif12 = $this->stateManager->getValidTechnicalClarification(12, $sharedConvId, 'TECHNICAL_INQUIRY');
        $this->assertNotNull($clarif12);
        $this->assertEquals('Honda', $clarif12['entities']['brand']);
        $this->assertEquals('CB190R', $clarif12['entities']['model']);
        $this->assertEquals('2018', $clarif12['entities']['year']);
        $this->assertEquals([101, 102], $clarif12['candidate_scope']);
        $this->assertEquals(0, $clarif12['source_turn']);
        $this->assertEquals(1, $this->stateManager->getConversationTurn(12, $sharedConvId));

        // Consulta cruzada para Tenant 13 con mismo texto de consulta: "Quiero confirmar"
        $clarif13 = $this->stateManager->getValidTechnicalClarification(13, $sharedConvId, 'TECHNICAL_INQUIRY');
        $this->assertNotNull($clarif13);
        $this->assertEquals('Nissan', $clarif13['entities']['brand']);
        $this->assertEquals('Sentra', $clarif13['entities']['model']);
        $this->assertEquals('2020', $clarif13['entities']['year']);
        $this->assertEquals([101, 103], $clarif13['candidate_scope']);
        $this->assertEquals(0, $clarif13['source_turn']);
        $this->assertEquals(1, $this->stateManager->getConversationTurn(13, $sharedConvId));

        // Inexistencia de fuga entre tenants aunque compartan candidate ID 101, turnos idénticos y convId
        $this->assertNotEquals($clarif12['entities']['brand'], $clarif13['entities']['brand']);
        $this->assertNotEquals($clarif12['entities']['model'], $clarif13['entities']['model']);
    }

    /**
     * Caso 9: No-Guess Clarification Terminal (Invariante P3.14-08).
     * Ante respuestas ambiguas o vagas ("Sí", "OK"), AIVA reitera las opciones sin adivinar.
     */
    public function test_case_9_no_guess_clarification_terminal(): void
    {
        $tenantId = 13;
        $context = [
            'brand' => 'Nissan',
            'model' => 'Sentra',
            'year' => 2020,
        ];

        // Cliente responde de forma imprecisa: "Sí"
        $resVague = $this->techResolver->resolve($tenantId, "Sí", $context);

        // Debe mantenerse en AMBIGUOUS con SIDE_UNSPECIFIED; no inventa ni FRONT ni REAR
        $this->assertTrue($resVague->isAmbiguous());
        $this->assertEquals('SIDE_UNSPECIFIED', $resVague->ambiguityReason);
        $this->assertCount(2, $resVague->compatibleCandidates);
        $this->assertStringContainsString('delanteras o traseras', $resVague->requiredDisambiguation);
    }

    /**
     * Caso 10: Cadena Conversacional Completa E2E (Invariante P3.14-09, P3.12-R, P2.11, P3.13).
     * Diálogo Multi-Turno -> AG-46 -> Orden AWAITING_PAYMENT -> Yape P3.12-R -> P2.11 -> CONFIRMED -> Dirección -> PREPARING.
     */
    public function test_case_10_end_to_end_conversational_commercial_chain(): void
    {
        $tenantId = 13; // Autopartes del Perú
        $convId = 'conv_p314_c10_' . uniqid();

        // Turno 1: Consulta con desambiguación resuelta
        $context = ['brand' => 'Toyota', 'model' => 'Corolla'];
        $res = $this->techResolver->resolve($tenantId, "Pastillas de freno delanteras 2018", $context);
        $this->assertTrue($res->isResolved());
        $part = $res->getResolvedCandidate();
        $this->assertEquals('PA-TOY-001', $part['sku']);

        // Turno 2: Se crea la orden formal
        $total = (float)$part['base_price']; // S/ 180.00
        $orderNumber = 'ORD-P314-E2E-' . uniqid();

        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => $orderNumber,
            'customer_name' => 'Taller San Martín',
            'customer_phone' => '944332211',
            'delivery_address' => null,
            'delivery_zone' => null,
            'subtotal' => $total,
            'total_amount' => $total,
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
            'unit_price' => $total,
            'quantity' => 1,
            'subtotal' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Turno 3: Recepción de evidencia visual de pago (Yape S/ 180.00)
        $normalizer = new VisualEvidenceNormalizationService();
        $adapter = new PaymentEvidenceAdapter($normalizer);

        $evidence = new StructuredVisualEvidenceDTO(
            tenantId: $tenantId,
            conversationId: $convId,
            messageId: 'msg_p314_' . uniqid(),
            mediaId: 'med_p314',
            assetHash: 'hash_voucher_p314_toyota',
            assetClassification: CommercialVisualEvidenceType::PAYMENT_RECEIPT->value,
            detectedEvidence: [CommercialVisualEvidenceType::PAYMENT_RECEIPT->value],
            perceptionVersion: 'P3.12-R',
            source: 'WHATSAPP_INBOUND',
            observations: ['provider' => 'YAPE', 'amount' => '180.00', 'operation_number' => 'OP-P314-888'],
            confidence: 0.99,
            createdAt: now()->toIso8601String(),
            correlationId: 'CORR-P314-E2E'
        );

        $paymentRecon = $adapter->reconcile($evidence, [
            'order_id' => $orderId,
            'expected_amount' => '180.00',
            'order' => ['id' => $orderId, 'total' => '180.00'],
        ]);
        $this->assertTrue($paymentRecon['is_approved']);

        // Transición financiera P2.11 -> Política comercial
        $finResult = [
            'status' => 'APPROVED',
            'is_approved' => true,
            'paid_amount' => '180.00',
            'expected_amount' => '180.00',
            'pending_amount' => '0.00',
            'operation_number' => 'OP-P314-888',
            'provider' => 'YAPE',
        ];
        $policyRes = $this->policyService->processPaymentResolution($tenantId, $orderId, $finResult);

        // Ortogonalidad: PAID + CONFIRMED pero fulfillment queda en PENDING
        $this->assertEquals('PAID', $policyRes['financial_status']);
        $this->assertEquals('CONFIRMED', $policyRes['commercial_status']);
        $this->assertEquals('PENDING', $policyRes['fulfillment_status']);

        // Turno 4: Cliente proporciona dirección -> P3.13 transiciona a PREPARING
        $deliveryRes = $this->policyService->processDeliveryConfirmation($tenantId, $orderId, 'Calle Las Begonias 333', 'San Isidro');
        $this->assertEquals('PREPARING', $deliveryRes['fulfillment_status']);
        $this->assertTrue($deliveryRes['fulfillment_transition_executed']);

        // Verificación en base de datos
        $finalOrder = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('CONFIRMED', $finalOrder->status);
        $this->assertEquals('PAID', $finalOrder->payment_status);
        $this->assertEquals('PREPARING', $finalOrder->fulfillment_status);
        $this->assertEquals('Calle Las Begonias 333', $finalOrder->delivery_address);
    }
}
