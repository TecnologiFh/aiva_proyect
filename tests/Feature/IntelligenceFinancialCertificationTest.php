<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\MetricEngine;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IntelligenceFinancialCertificationTest extends TestCase
{
    private MetricEngine $engine;
    private MetricRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sembrar Tenants de prueba (Tenant 100 y Tenant 200)
        DB::table('tenants')->updateOrInsert(
            ['id' => 100],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Alfa Financial',
                'slug' => 'tenant-alfa-fin',
                'business_type' => 'Retail',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('tenants')->updateOrInsert(
            ['id' => 200],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Beta Financial',
                'slug' => 'tenant-beta-fin',
                'business_type' => 'Food',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Limpiar tablas financieras para aislar los tests de Tenants 100 y 200
        $tenantOrderIds = DB::table('orders')->whereIn('tenant_id', [100, 200])->pluck('id')->toArray();
        if (!empty($tenantOrderIds)) {
            DB::table('payment_allocations')->whereIn('order_id', $tenantOrderIds)->delete();
        }
        DB::table('refunds')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('payments')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('payment_webhook_receipts')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('payment_evidences')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('order_items')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('orders')->whereIn('tenant_id', [100, 200])->delete();

        $this->engine = $this->app->make(MetricEngine::class);
        $this->registry = $this->app->make(MetricRegistry::class);
    }

    private function createOrder(int $tenantId, float $total = 100.00, string $method = 'YAPE', string $status = 'CONFIRMED', string $paymentStatus = 'PAID'): int
    {
        return DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-' . uniqid(),
            'customer_name' => 'Cliente Test',
            'customer_phone' => '51999' . rand(100000, 999999),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => 'PENDING',
            'payment_method' => $method,
            'subtotal' => $total,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEvidence(int $tenantId, ?int $orderId = null, string $provider = 'YAPE'): int
    {
        return DB::table('payment_evidences')->insertGetId([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'payment_revision' => 1,
            'uuid' => 'pe-' . uniqid(),
            'source' => 'WHATSAPP',
            'provider' => $provider,
            'sha256' => hash('sha256', uniqid('sha_', true)),
            'status' => 'PAID',
            'received_at' => now(),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAllocation(int $evidenceId, int $orderId, float $amount, string $status = 'APPLIED'): int
    {
        return DB::table('payment_allocations')->insertGetId([
            'payment_evidence_id' => $evidenceId,
            'order_id' => $orderId,
            'allocated_amount' => $amount,
            'remaining_order_amount' => 0.00,
            'allocation_type' => 'EXACT_PAYMENT',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * TEST 1: Canonical Payment Authority (payments table / Payment model)
     * Counts only APPROVED payments, excludes PENDING or REJECTED.
     */
    public function test_01_canonical_payment_authority(): void
    {
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-FIN-01',
            'customer_name' => 'Cliente Pago 1',
            'customer_phone' => '51999888111',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'YAPE',
            'subtotal' => 100.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pago 1: Aprobado (S/ 60.00)
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 60.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pago 2: Aprobado (S/ 40.00)
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 40.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pago 3: Pendiente / No Aprobado (S/ 50.00)
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'CARD',
            'payment_method' => 'CARD',
            'amount' => 50.00,
            'currency' => 'PEN',
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resRevenue = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // Debe contar exactamente 2 pagos aprobados y S/ 100.00 cobrados (no 3 pagos ni S/ 150.00)
        $this->assertEquals(2, $resCount->value);
        $this->assertEquals(100.00, $resRevenue->value);
    }

    /**
     * TEST 2: Canonical Payment Status Integrity (PAID, PARTIALLY_PAID, UNPAID)
     * Verifies that order statuses are read from canonical orders.payment_status.
     */
    public function test_02_payment_status_integrity(): void
    {
        // 1. Orden Pagada (PAID)
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-STAT-PAID',
            'customer_name' => 'Cliente Pagado',
            'customer_phone' => '51999888222',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'CARD',
            'subtotal' => 200.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 200.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Orden Parcialmente Pagada (PARTIALLY_PAID)
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-STAT-PARTIAL',
            'customer_name' => 'Cliente Parcial',
            'customer_phone' => '51999888333',
            'status' => 'AWAITING_PAYMENT',
            'payment_status' => 'PARTIALLY_PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'YAPE',
            'subtotal' => 150.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Orden Impaga (PENDING / UNPAID)
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-STAT-UNPAID',
            'customer_name' => 'Cliente Impago',
            'customer_phone' => '51999888444',
            'status' => 'NEW',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'CASH',
            'subtotal' => 80.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resPaid = $this->engine->execute('financial.paid_orders', $filterSet, skipCache: true);
        $resPartial = $this->engine->execute('financial.partially_paid_orders', $filterSet, skipCache: true);
        $resUnpaid = $this->engine->execute('financial.unpaid_orders', $filterSet, skipCache: true);

        $this->assertEquals(1, $resPaid->value);
        $this->assertEquals(1, $resPartial->value);
        $this->assertEquals(1, $resUnpaid->value);
    }

    /**
     * TEST 3: Collected Revenue vs Order Value (ORDER VALUE != CASH COLLECTED)
     * Demonstrates that orders.total_amount is NOT automatically assumed as collected revenue.
     */
    public function test_03_collected_revenue_vs_order_value(): void
    {
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-BIG-VALUE',
            'customer_name' => 'Cliente Valor',
            'customer_phone' => '51999888555',
            'status' => 'CONFIRMED',
            'payment_status' => 'PARTIALLY_PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'BANK_TRANSFER',
            'subtotal' => 1000.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 1000.00, // Valor de la orden = 1000
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Monto efectivamente cobrado: solo S/ 350.00
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'BANK_TRANSFER',
            'payment_method' => 'BANK_TRANSFER',
            'amount' => 350.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // Debe reportar S/ 350.00, NUNCA S/ 1000.00
        $this->assertEquals(350.00, $resCollected->value);
    }

    /**
     * TEST 4: Payment Deduplication (Critical Gate 14)
     * 1 Payment + 3 Evidence Records + 2 Webhook Receipts must count as 1 Payment and 1 Amount.
     */
    public function test_04_payment_deduplication_multi_evidence_and_webhooks(): void
    {
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-DEDUP-01',
            'customer_name' => 'Cliente Deduplicado',
            'customer_phone' => '51999888666',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'YAPE',
            'subtotal' => 120.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 120.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 Pago canónico en payments (S/ 120.00)
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 120.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3 Registros de evidencia en payment_evidences
        for ($i = 1; $i <= 3; $i++) {
            DB::table('payment_evidences')->insert([
                'tenant_id' => 100,
                'order_id' => $oId,
                'payment_revision' => 1,
                'uuid' => 'pe-dedup-' . $i . '-' . uniqid(),
                'source' => 'WHATSAPP',
                'provider' => 'YAPE',
                'sha256' => hash('sha256', 'evidence-' . $i . '-' . $oId),
                'status' => 'RECEIVED',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2 Recibos de Webhooks en payment_webhook_receipts
        for ($j = 1; $j <= 2; $j++) {
            DB::table('payment_webhook_receipts')->insert([
                'tenant_id' => 100,
                'whatsapp_message_id' => 'wam-dedup-' . $j . '-' . uniqid(),
                'processing_status' => 'COMPLETED',
                'result_code' => 'OK',
                'response_payload' => json_encode(['order_id' => $oId]),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // El conteo DEBE ser 1 (NUNCA 6), y el monto DEBE ser 120.00 (NUNCA 720.00)
        $this->assertEquals(1, $resCount->value);
        $this->assertEquals(120.00, $resCollected->value);
    }

    /**
     * TEST 5: Payment Methods Breakdown & Payment Method Share
     * Groups payments by canonical method and calculates exact shares.
     */
    public function test_05_payment_methods_breakdown_and_shares(): void
    {
        // 2 Pagos YAPE de S/ 50.00 cada uno = S/ 100.00
        for ($i = 0; $i < 2; $i++) {
            $oId = $this->createOrder(100, 50.00, 'YAPE');
            DB::table('payments')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'order_id' => $oId,
                'gateway' => 'YAPE',
                'payment_method' => 'YAPE',
                'amount' => 50.00,
                'currency' => 'PEN',
                'status' => 'APPROVED',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 1 Pago CARD de S/ 100.00
        $oIdCard = $this->createOrder(100, 100.00, 'CARD');
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oIdCard,
            'gateway' => 'CULQI',
            'payment_method' => 'CARD',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 Pago CASH de S/ 200.00
        $oIdCash = $this->createOrder(100, 200.00, 'CASH');
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oIdCash,
            'gateway' => 'MANUAL',
            'payment_method' => 'CASH',
            'amount' => 200.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Total = 400.00 (YAPE: 100.00 / 25%, CARD: 100.00 / 25%, CASH: 200.00 / 50%)
        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resMethods = $this->engine->execute('financial.payment_methods', $filterSet, skipCache: true);
        $resShareYape = $this->engine->execute('financial.payment_method_share', $filterSet, additionalFilters: ['payment_method' => 'YAPE'], skipCache: true);
        $resShareCash = $this->engine->execute('financial.payment_method_share', $filterSet, additionalFilters: ['payment_method' => 'CASH'], skipCache: true);
        $resAvgPayment = $this->engine->execute('financial.average_payment', $filterSet, skipCache: true);

        $methodsArray = $resMethods->value;
        $this->assertIsArray($methodsArray);
        $this->assertCount(3, $methodsArray);

        // Verificar participaciones
        $this->assertEquals(25.00, $resShareYape->value);
        $this->assertEquals(50.00, $resShareCash->value);
        // Ticket promedio cobrado: 400.00 / 4 pagos = 100.00
        $this->assertEquals(100.00, $resAvgPayment->value);
    }

    /**
     * TEST 6: Historical Amount Integrity
     * Mutating subsequent catalog/order values does NOT alter historical financial facts.
     */
    public function test_06_historical_financial_integrity(): void
    {
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-HIST-FIN',
            'customer_name' => 'Cliente Histórico',
            'customer_phone' => '51999888777',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'YAPE',
            'subtotal' => 80.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 80.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Modificación posterior del total en orden a 250.00
        DB::table('orders')->where('id', $oId)->update(['total_amount' => 250.00]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resRevenue = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // El cobro financiero histórico permanece inalterable en 80.00 (NUNCA 250.00)
        $this->assertEquals(80.00, $resRevenue->value);
    }

    /**
     * TEST 7: Multi-Tenant Boundary Isolation
     * Tenant 100 cannot read or aggregate Tenant 200 payments.
     */
    public function test_07_multi_tenant_isolation_financial(): void
    {
        // Pago en Tenant 100: S/ 100.00
        $o100 = $this->createOrder(100, 100.00, 'YAPE');
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $o100,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pago en Tenant 200: S/ 900.00
        $o200 = $this->createOrder(200, 900.00, 'CARD');
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'order_id' => $o200,
            'gateway' => 'CARD',
            'payment_method' => 'CARD',
            'amount' => 900.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filter100 = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $res100 = $this->engine->execute('financial.collected_revenue', $filter100, skipCache: true);
        $resCount100 = $this->engine->execute('financial.payment_count', $filter100, skipCache: true);

        // Solo debe ver los datos de Tenant 100 (100.00 y 1 pago, NUNCA 1000.00 ni 2 pagos)
        $this->assertEquals(100.00, $res100->value);
        $this->assertEquals(1, $resCount100->value);
    }

    /**
     * TEST 8: Read-Only Enforcement — Zero Mutations on Financial Execution
     */
    public function test_08_read_only_enforcement_zero_mutations(): void
    {
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-RO-FIN',
            'customer_name' => 'Cliente Read Only',
            'customer_phone' => '51999888999',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'PENDING',
            'payment_method' => 'YAPE',
            'subtotal' => 60.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 60.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 60.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ordersBefore = DB::table('orders')->count();
        $paymentsBefore = DB::table('payments')->count();
        $evidencesBefore = DB::table('payment_evidences')->count();
        $refundsBefore = DB::table('refunds')->count();

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Ejecución exhaustiva de calculadores
        $this->engine->execute('financial.paid_orders', $filterSet, skipCache: true);
        $this->engine->execute('financial.partially_paid_orders', $filterSet, skipCache: true);
        $this->engine->execute('financial.unpaid_orders', $filterSet, skipCache: true);
        $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);
        $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $this->engine->execute('financial.average_payment', $filterSet, skipCache: true);
        $this->engine->execute('financial.payment_methods', $filterSet, skipCache: true);
        $this->engine->execute('financial.refunds', $filterSet, skipCache: true);
        $this->engine->execute('financial.refunded_amount', $filterSet, skipCache: true);
        $this->engine->execute('financial.net_collected', $filterSet, skipCache: true);

        $this->assertEquals($ordersBefore, DB::table('orders')->count());
        $this->assertEquals($paymentsBefore, DB::table('payments')->count());
        $this->assertEquals($evidencesBefore, DB::table('payment_evidences')->count());
        $this->assertEquals($refundsBefore, DB::table('refunds')->count());
    }

    /**
     * TEST 9: Refund Authority & Net Collected
     * Validates formal linkage with refunds table (financial.refunds, refunded_amount, net_collected).
     * Tests exact 3 scenarios of Section 14 without silent MAX(0) masking:
     * - collected = 100, refunded = 30  -> net_collected = 70.00
     * - collected = 100, refunded = 100 -> net_collected = 0.00
     * - collected = 100, refunded = 120 -> net_collected = -20.00 (faithful exposure of refund overflow anomaly)
     */
    public function test_09_refund_authority_and_net_collected(): void
    {
        $oId = $this->createOrder(100, 100.00, 'YAPE');

        // Pago de S/ 100.00
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Escenario A: Reembolso de S/ 30.00 -> Net = 70.00
        $rId1 = DB::table('refunds')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'refund_number' => 'REF-001',
            'amount' => 30.00,
            'reason' => 'Devolución parcial',
            'refund_method' => 'YAPE',
            'status' => 'PROCESSED',
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resRefunds = $this->engine->execute('financial.refunds', $filterSet, skipCache: true);
        $resRefundedAmt = $this->engine->execute('financial.refunded_amount', $filterSet, skipCache: true);
        $resNet = $this->engine->execute('financial.net_collected', $filterSet, skipCache: true);

        $this->assertEquals(1, $resRefunds->value);
        $this->assertEquals(30.00, $resRefundedAmt->value);
        $this->assertEquals(70.00, $resNet->value);

        // Escenario B: Reembolso adicional de S/ 70.00 (Total reembolsos = 100.00) -> Net = 0.00
        $rId2 = DB::table('refunds')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'refund_number' => 'REF-002',
            'amount' => 70.00,
            'reason' => 'Devolución restante',
            'refund_method' => 'YAPE',
            'status' => 'PROCESSED',
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resNetZero = $this->engine->execute('financial.net_collected', $filterSet, skipCache: true);
        $this->assertEquals(0.00, $resNetZero->value);

        // Escenario C: Reembolso adicional de S/ 20.00 (Total reembolsos = 120.00 vs Cobrado = 100.00)
        // Regla: NO ocultar la anomalía mediante MAX(0). El resultado neto fiel es -20.00.
        $rId3 = DB::table('refunds')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'refund_number' => 'REF-003',
            'amount' => 20.00,
            'reason' => 'Devolución en exceso',
            'refund_method' => 'YAPE',
            'status' => 'PROCESSED',
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resNetNegative = $this->engine->execute('financial.net_collected', $filterSet, skipCache: true);
        $this->assertEquals(-20.00, $resNetNegative->value);
    }

    /**
     * TEST 10: Safeguard Against Fabricated Cost / Profit / Margin Metrics
     * Confirms that COGS, profit, and margin are formally deferred and throw InvalidArgumentException.
     */
    public function test_10_safeguard_against_unauthorized_cost_metrics(): void
    {
        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $this->expectException(InvalidArgumentException::class);
        $this->engine->execute('financial.profit', $filterSet, skipCache: true);
    }

    /**
     * TEST 11: Period Comparison with Zero-Base Safety
     * Compares financial metrics against previous period without dividing by zero.
     */
    public function test_11_period_comparison_zero_base_safety(): void
    {
        // Pago hoy de S/ 150.00
        $oId = $this->createOrder(100, 150.00, 'YAPE');
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 150.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: Carbon::now('America/Lima')->toDateString(),
            dateTo: Carbon::now('America/Lima')->toDateString(),
            timezone: 'America/Lima'
        );

        $res = $this->engine->execute('financial.collected_revenue', $filterSet, compare: true, skipCache: true);

        $this->assertEquals(150.00, $res->value);
        $this->assertNotNull($res->comparison);
        $this->assertEquals(150.00, $res->comparison->currentValue);
        $this->assertEquals(0.00, $res->comparison->previousValue);
        $this->assertEquals(150.00, $res->comparison->absoluteChange);
        $this->assertEquals(100.00, $res->comparison->percentageChange);
        $this->assertEquals('UP', $res->comparison->trend);
    }

    /**
     * RATIFICATION TEST R1 (Section 6): Payment & Allocation Identity
     * Payment P1 (100) + Allocation A1 (100) => payment_count = 1, collected_revenue = 100.00 (NEVER 2, NEVER 200).
     */
    public function test_r1_payment_allocation_identity(): void
    {
        $oId = $this->createOrder(100, 100.00, 'YAPE');

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evId = $this->createEvidence(100, $oId, 'YAPE');
        $this->createAllocation($evId, $oId, 100.00, 'APPLIED');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        $this->assertEquals(1, $resCount->value);
        $this->assertEquals(100.00, $resCollected->value);
    }

    /**
     * RATIFICATION TEST R2 (Section 7): Multi-Allocation Distribution
     * Payment P1 (100) + Allocation A1 (60) + Allocation A2 (40) => payment_count = 1, collected_revenue = 100.00 (NEVER 160 nor 200).
     */
    public function test_r2_multi_allocation_distribution(): void
    {
        $oId = $this->createOrder(100, 100.00, 'YAPE');

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evId = $this->createEvidence(100, $oId, 'YAPE');
        $this->createAllocation($evId, $oId, 60.00, 'APPLIED');
        $this->createAllocation($evId, $oId, 40.00, 'APPLIED');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        $this->assertEquals(1, $resCount->value);
        $this->assertEquals(100.00, $resCollected->value);
    }

    /**
     * RATIFICATION TEST R3 (Section 8): Multi-Payment & Multi-Allocation
     * Payment P1 (100) + Payment P2 (50) + Allocations (60, 40, 50) => payment_count = 2, collected_revenue = 150.00.
     */
    public function test_r3_multi_payment_multi_allocation(): void
    {
        $oId1 = $this->createOrder(100, 100.00, 'YAPE');
        $oId2 = $this->createOrder(100, 50.00, 'CARD');

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId1,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId2,
            'gateway' => 'CULQI',
            'payment_method' => 'CARD',
            'amount' => 50.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evId1 = $this->createEvidence(100, $oId1, 'YAPE');
        $this->createAllocation($evId1, $oId1, 60.00, 'APPLIED');
        $this->createAllocation($evId1, $oId1, 40.00, 'APPLIED');

        $evId2 = $this->createEvidence(100, $oId2, 'CARD');
        $this->createAllocation($evId2, $oId2, 50.00, 'APPLIED');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // Conteo = 2 pagos (no 3 ni 5 allocations); Monto = 150.00
        $this->assertEquals(2, $resCount->value);
        $this->assertEquals(150.00, $resCollected->value);
    }

    /**
     * RATIFICATION TEST R4 (Section 10): Cross-Order Allocation Distribution
     * Payment P1 (100) distributed across Order A (60) and Order B (40) => total collected = 100.00 (NEVER 200.00).
     */
    public function test_r4_cross_order_allocation(): void
    {
        $orderA = $this->createOrder(100, 60.00, 'BANK_TRANSFER');
        $orderB = $this->createOrder(100, 40.00, 'BANK_TRANSFER');

        // 1 Pago económico de S/ 100.00
        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $orderA, // vinculado a la orden principal del depósito
            'gateway' => 'BANK_TRANSFER',
            'payment_method' => 'BANK_TRANSFER',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evId = $this->createEvidence(100, $orderA, 'BANK_TRANSFER');
        // Alocación dividida entre Order A (60) y Order B (40)
        $this->createAllocation($evId, $orderA, 60.00, 'APPLIED');
        $this->createAllocation($evId, $orderB, 40.00, 'APPLIED');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        $this->assertEquals(1, $resCount->value);
        $this->assertEquals(100.00, $resCollected->value);
    }

    /**
     * RATIFICATION TEST R5 (Section 9): Compound Evidence + Webhook + Allocation Non-Duplication
     * 1 Payment (100) + 3 Evidences + 2 Webhook Receipts + 2 Allocations => 1 Payment, 100.00 Collected.
     */
    public function test_r5_evidence_webhook_allocation_compound_dedup(): void
    {
        $oId = $this->createOrder(100, 100.00, 'YAPE');

        DB::table('payments')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'gateway' => 'YAPE',
            'payment_method' => 'YAPE',
            'amount' => 100.00,
            'currency' => 'PEN',
            'status' => 'APPROVED',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3 Evidencias
        for ($i = 1; $i <= 3; $i++) {
            $this->createEvidence(100, $oId, 'YAPE');
        }

        // 2 Webhooks
        for ($j = 1; $j <= 2; $j++) {
            DB::table('payment_webhook_receipts')->insert([
                'tenant_id' => 100,
                'whatsapp_message_id' => 'wam-r5-' . $j . '-' . uniqid(),
                'processing_status' => 'COMPLETED',
                'result_code' => 'OK',
                'response_payload' => json_encode(['order_id' => $oId]),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2 Alocaciones (60 + 40)
        $evId = $this->createEvidence(100, $oId, 'YAPE');
        $this->createAllocation($evId, $oId, 60.00, 'APPLIED');
        $this->createAllocation($evId, $oId, 40.00, 'APPLIED');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resCount = $this->engine->execute('financial.payment_count', $filterSet, skipCache: true);
        $resCollected = $this->engine->execute('financial.collected_revenue', $filterSet, skipCache: true);

        // El conteo DEBE ser 1 y el monto DEBE ser 100.00 (NUNCA 8 registros ni 200/800 de recaudación)
        $this->assertEquals(1, $resCount->value);
        $this->assertEquals(100.00, $resCollected->value);
    }
}
