<?php

namespace Tests\Feature;

use App\Core\Exceptions\TenantResolutionException;
use App\Modules\Core\Identity\PhoneNormalizer;
use App\Modules\Messaging\Services\WhatsAppChannelBindingService;
use App\Modules\Messaging\Services\WhatsAppChannelResolver;
use App\Modules\Multimodal\Runtime\Adapters\PaymentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use App\Modules\Orders\Services\OrderCommercialTransitionPolicyService;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use App\Modules\Tenant\Services\UserTenantAccessService;
use App\Shared\Middleware\GatewayAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * P3.13 Test Suite — Operations, Financial Authority & Channel Integrity.
 * 
 * Verifica los 5 Regression Gates Constitucionales:
 * Gate 1: Concurrencia y Unicidad Telefónica en DB.
 * Gate 2: No-Runtime-Commercial-Mutation.
 * Gate 3: Ortogonalidad PAID / CONFIRMED / PREPARING.
 * Gate 4: Gateway Fail-Closed Inbound (P3.13-08).
 * Gate 5: Operations Center RBAC & Tenant Scoping (P3.13-10).
 */
class P313OperationsAndChannelIntegrityTest extends TestCase
{
    private WhatsAppChannelBindingService $bindingService;
    private OrderCommercialTransitionPolicyService $policyService;
    private OrderStateMachineEngine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindingService = new WhatsAppChannelBindingService();
        $this->policyService = new OrderCommercialTransitionPolicyService();
        $this->stateMachine = new OrderStateMachineEngine();

        // Asegurar que los tenants 11 y 12 existan para pruebas
        DB::table('tenants')->updateOrInsert(
            ['id' => 11],
            ['name' => 'Wallpa Brasa', 'slug' => 'wallpa-brasa', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('tenants')->updateOrInsert(
            ['id' => 12],
            ['name' => 'Carenados Motos', 'slug' => 'carenados-motos', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    // =========================================================================
    // GATE 1: Concurrencia y Unicidad Telefónica en DB (P3.13-05 & P3.13-09)
    // =========================================================================
    public function test_gate_1_channel_reassignment_atomically_deactivates_previous_tenant(): void
    {
        $testPhone = '+51988887766';
        $canonical = PhoneNormalizer::toCanonical($testPhone);
        $national = PhoneNormalizer::toNational($testPhone);

        // 1. Vincular inicialmente a Tenant 11
        $res1 = $this->bindingService->bindChannel(11, $testPhone, 'wallpa-session');
        $this->assertEquals('SUCCESS', $res1['status']);
        $this->assertEquals(11, $res1['tenant_id']);

        $channel11 = DB::table('whatsapp_channels')->where('tenant_id', 11)->first();
        $this->assertEquals('ACTIVE', $channel11->status);
        $this->assertEquals($canonical, $channel11->active_phone_e164);

        // 2. Reasignar el mismo número a Tenant 12
        $res2 = $this->bindingService->bindChannel(12, $testPhone, 'carenados-session');
        $this->assertEquals('SUCCESS', $res2['status']);
        $this->assertEquals(12, $res2['tenant_id']);
        $this->assertContains(11, $res2['deactivated_tenants']);

        // 3. Verificar estado en DB: Tenant 11 pasa a INACTIVE con NULL active_phone_e164
        $channel11After = DB::table('whatsapp_channels')->where('tenant_id', 11)->first();
        $this->assertEquals('INACTIVE', $channel11After->status);
        $this->assertNull($channel11After->active_phone_e164);

        // 4. Tenant 12 es ahora el único ACTIVE con este teléfono
        $channel12 = DB::table('whatsapp_channels')->where('tenant_id', 12)->first();
        $this->assertEquals('ACTIVE', $channel12->status);
        $this->assertEquals($canonical, $channel12->active_phone_e164);

        // 5. La columna active_phone_e164 en DB tiene exactamente una fila activa para este número
        $activeCount = DB::table('whatsapp_channels')
            ->where('active_phone_e164', $canonical)
            ->where('status', 'ACTIVE')
            ->count();
        $this->assertEquals(1, $activeCount);
    }

    // =========================================================================
    // GATE 2: No-Runtime-Commercial-Mutation (P3.13-01 & P3.13-07)
    // =========================================================================
    public function test_gate_2_payment_evidence_adapter_never_mutates_orders_directly(): void
    {
        // Crear un pedido en base de datos
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 11,
            'order_number' => 'ORD-TEST-NO-MUT-' . uniqid(),
            'customer_name' => 'Cliente Test',
            'customer_phone' => '988880000',
            'subtotal' => 218.00,
            'total_amount' => 218.00,
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $normalizer = new VisualEvidenceNormalizationService();
        $adapter = new PaymentEvidenceAdapter($normalizer);

        $evidence = new StructuredVisualEvidenceDTO(
            tenantId: 11,
            conversationId: 'conv_test_gate2',
            messageId: 'msg_gate2_' . time(),
            mediaId: 'med_gate2',
            assetHash: 'hash_gate2',
            assetClassification: CommercialVisualEvidenceType::PAYMENT_RECEIPT->value,
            detectedEvidence: [CommercialVisualEvidenceType::PAYMENT_RECEIPT->value],
            perceptionVersion: 'P3.12-R',
            source: 'WHATSAPP_INBOUND',
            observations: [
                'provider' => 'YAPE',
                'amount' => '218.00',
                'operation_number' => 'OP-GATE2-99',
            ],
            confidence: 0.95,
            createdAt: now()->toIso8601String(),
            correlationId: 'CORR-GATE2'
        );

        // Invocar directamente el reconcile del Adapter (Runtime)
        $reconciliation = $adapter->reconcile($evidence, [
            'order_id' => $orderId,
            'expected_amount' => '218.00',
            'order' => ['id' => $orderId, 'total' => '218.00'],
        ]);

        $this->assertEquals('APPROVED', $reconciliation['status']);
        $this->assertTrue($reconciliation['is_approved']);

        // VERIFICACIÓN CONSTITUCIONAL: El adaptador NO modificó la tabla orders en DB
        $orderInDb = DB::table('orders')->where('id', $orderId)->first();
        $this->assertEquals('AWAITING_PAYMENT', $orderInDb->status);
        $this->assertEquals('PENDING', $orderInDb->payment_status);
        $this->assertEquals('PENDING', $orderInDb->fulfillment_status);
    }

    // =========================================================================
    // GATE 3: Ortogonalidad PAID / CONFIRMED / PREPARING (P3.13-02, P3.13-03, P3.13-04)
    // =========================================================================
    public function test_gate_3_paid_without_confirmed_address_stays_pending_fulfillment(): void
    {
        // Caso A: Pedido con ítems pero SIN dirección de delivery
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 11,
            'order_number' => 'ORD-TEST-GATE3-A-' . uniqid(),
            'customer_name' => 'Cliente Gate 3',
            'customer_phone' => '977770001',
            'delivery_address' => null,
            'delivery_zone' => null,
            'subtotal' => 218.00,
            'total_amount' => 218.00,
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 11,
            'order_id' => $orderId,
            'product_id' => 1,
            'product_name' => 'Producto Prueba',
            'unit_price' => 218.00,
            'quantity' => 1,
            'subtotal' => 218.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $financialResult = [
            'status' => 'APPROVED',
            'is_approved' => true,
            'paid_amount' => '218.00',
            'expected_amount' => '218.00',
            'pending_amount' => '0.00',
            'operation_number' => 'OP-GATE3-111',
            'provider' => 'YAPE',
        ];

        // Procesar resolución financiera a través de la política oficial de Orders
        $resA = $this->policyService->processPaymentResolution(11, $orderId, $financialResult);

        $this->assertEquals('PAID', $resA['financial_status']);
        $this->assertEquals('CONFIRMED', $resA['commercial_status']);
        $this->assertTrue($resA['commercial_transition_executed']);

        // P3.13-04: Como no tiene dirección confirmada, fulfillment NO pasa a PREPARING, queda en PENDING
        $this->assertEquals('PENDING', $resA['fulfillment_status']);
        $this->assertFalse($resA['fulfillment_transition_executed']);

        // Caso B: El cliente luego confirma su dirección de delivery
        $resB = $this->policyService->processDeliveryConfirmation(11, $orderId, 'Av. Javier Prado 1234', 'San Borja');

        // Ahora sí satisface todas las condiciones -> transiciona a PREPARING
        $this->assertEquals('CONFIRMED', $resB['commercial_status']);
        $this->assertEquals('PREPARING', $resB['fulfillment_status']);
        $this->assertTrue($resB['fulfillment_transition_executed']);
    }

    public function test_gate_3_cumulative_partial_payments_do_not_confirm_order(): void
    {
        // Pedido de S/ 218.00
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 11,
            'order_number' => 'ORD-TEST-GATE3-PARTIAL-' . uniqid(),
            'customer_name' => 'Cliente Parcial',
            'customer_phone' => '977770002',
            'delivery_address' => 'Calle Los Pinos 456',
            'delivery_zone' => 'Miraflores',
            'subtotal' => 218.00,
            'total_amount' => 218.00,
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Abono parcial 1: S/ 60.00
        $partialResult1 = [
            'status' => 'AMOUNT_MISMATCH',
            'is_approved' => false,
            'paid_amount' => '60.00',
            'expected_amount' => '218.00',
            'pending_amount' => '158.00',
            'operation_number' => 'OP-PART-01',
            'provider' => 'YAPE',
        ];

        $res1 = $this->policyService->processPaymentResolution(11, $orderId, $partialResult1);

        $this->assertEquals('PARTIALLY_PAID', $res1['financial_status']);
        $this->assertEquals('AWAITING_PAYMENT', $res1['commercial_status']);
        $this->assertEquals('PENDING', $res1['fulfillment_status']);
        $this->assertFalse($res1['commercial_transition_executed']);
        $this->assertFalse($res1['fulfillment_transition_executed']);

        // Abono 2 que completa el total: S/ 158.00 (acumulado total = 218.00)
        $completeResult2 = [
            'status' => 'APPROVED',
            'is_approved' => true,
            'paid_amount' => '218.00',
            'expected_amount' => '218.00',
            'pending_amount' => '0.00',
            'operation_number' => 'OP-PART-02',
            'provider' => 'YAPE',
        ];

        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 11,
            'order_id' => $orderId,
            'product_id' => 1,
            'product_name' => 'Producto 2',
            'unit_price' => 218.00,
            'quantity' => 1,
            'subtotal' => 218.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res2 = $this->policyService->processPaymentResolution(11, $orderId, $completeResult2);

        $this->assertEquals('PAID', $res2['financial_status']);
        $this->assertEquals('CONFIRMED', $res2['commercial_status']);
        // Como ya tenía dirección y ahora está pagado completo -> PREPARING
        $this->assertEquals('PREPARING', $res2['fulfillment_status']);
        $this->assertTrue($res2['commercial_transition_executed']);
        $this->assertTrue($res2['fulfillment_transition_executed']);
    }

    // =========================================================================
    // GATE 4: Gateway Fail-Closed Inbound (P3.13-08)
    // =========================================================================
    public function test_gate_4_gateway_resolves_strictly_by_connected_physical_phone(): void
    {
        $officialPhone = '+51944332211';
        $canonical = PhoneNormalizer::toCanonical($officialPhone);
        $this->bindingService->bindChannel(12, $officialPhone, 'carenados-physical');

        // 1. Teléfono físico activo resuelve exactamente al Tenant 12
        $resolvedTenant = WhatsAppChannelResolver::resolveStrictBinding(null, $officialPhone);
        $this->assertEquals(12, $resolvedTenant);

        // 2. Teléfono físico activo resuelve al Tenant 12 incluso si se envía una sesión stale
        $resolvedWithStaleSession = WhatsAppChannelResolver::resolveStrictBinding('wallpa-brasa', $officialPhone);
        $this->assertEquals(12, $resolvedWithStaleSession);

        // 3. Teléfono desconocido NO resuelve y falla cerrado (Fail-Closed: TenantResolutionException)
        $this->expectException(TenantResolutionException::class);
        WhatsAppChannelResolver::resolveStrictBinding(null, '+51900000000');
    }

    // =========================================================================
    // GATE 5: Operations Center RBAC & Tenant Scoping (P3.13-10)
    // =========================================================================
    public function test_gate_5_operations_center_access_strictly_enforces_tenant_authorization(): void
    {
        $accessService = new UserTenantAccessService();

        // 1. Acceso a tenant válido (11 o 12)
        $activeTenant = $accessService->requireActiveTenant(11);
        $this->assertEquals(11, $activeTenant);

        // 2. Acceso a tenant no autorizado (ej: 999999) lanza HTTP 403 Forbidden
        $this->expectException(HttpException::class);
        $accessService->validateTenantAccess(999999);
    }
}
