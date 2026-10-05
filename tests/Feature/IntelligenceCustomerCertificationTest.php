<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\MetricEngine;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use App\Modules\Intelligence\Domain\Values\PeriodComparison;
use App\Modules\Intelligence\Support\MetricDataStatus;
use App\Services\Authorization\AccessResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IntelligenceCustomerCertificationTest extends TestCase
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
                'name' => 'Tenant Alfa Store',
                'slug' => 'tenant-alfa-cust',
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
                'name' => 'Tenant Beta Foods',
                'slug' => 'tenant-beta-cust',
                'business_type' => 'Gastronomía',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Limpiar datos previos de los tenants de prueba para determinismo
        DB::table('order_items')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->whereIn('tenant_id', [100, 200]);
        })->delete();
        DB::table('orders')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('customers')->whereIn('tenant_id', [100, 200])->delete();

        $this->engine = app(MetricEngine::class);
        $this->registry = app(MetricRegistry::class);
    }

    /**
     * TEST 1: Catalog Integrity & Metric Registry Registration
     * Valida que las 10 métricas de Customer Intelligence estén registradas.
     */
    public function test_01_customer_metric_registry_registration(): void
    {
        $expectedMetrics = [
            'customers.total',
            'customers.new',
            'customers.active',
            'customers.repeat',
            'customers.purchase_frequency',
            'customers.recency',
            'customers.monetary_value',
            'customers.rfm',
            'customers.360',
            'customers.retention',
        ];

        foreach ($expectedMetrics as $metricKey) {
            $this->assertTrue($this->registry->has($metricKey), "Falta métrica '{$metricKey}' en MetricRegistry.");
            $def = $this->registry->get($metricKey);
            $this->assertEquals('customers', $def->category);
            $this->assertNotEmpty($def->calculatorClass);
        }
    }

    /**
     * TEST 2: Fail Closed on Missing TenantContext
     */
    public function test_02_fail_closed_on_missing_tenant(): void
    {
        TenantContext::clear();

        $this->expectException(HttpException::class);

        $filterSet = new AnalyticsFilterSet(
            tenantId: 0,
            timezone: 'America/Lima'
        );

        $this->engine->execute('customers.total', $filterSet);
    }

    /**
     * TEST 3: Read-Only Enforcement — Zero Mutations on Customer Execution
     */
    public function test_03_read_only_enforcement_zero_mutations(): void
    {
        // Sembrar 1 cliente y 1 orden
        $custId = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Fiel',
            'phone' => '51999888111',
            'customer_phone_canonical' => '51999888111',
            'email' => 'cliente@test.com',
            'tag' => 'NUEVO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-TEST-RO-01',
            'customer_name' => 'Cliente Fiel',
            'customer_phone' => '51999888111',
            'customer_phone_canonical' => '51999888111',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $custCountBefore = DB::table('customers')->count();
        $ordersCountBefore = DB::table('orders')->count();
        $custChecksumBefore = DB::table('customers')->where('id', $custId)->value('updated_at');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Ejecutar métricas y 360
        $this->engine->execute('customers.total', $filterSet, skipCache: true);
        $this->engine->execute('customers.active', $filterSet, skipCache: true);
        $this->engine->execute('customers.360', $filterSet, additionalFilters: ['customer_id' => $custId], skipCache: true);
        $this->engine->execute('customers.rfm', $filterSet, additionalFilters: ['customer_id' => $custId], skipCache: true);

        $custCountAfter = DB::table('customers')->count();
        $ordersCountAfter = DB::table('orders')->count();
        $custChecksumAfter = DB::table('customers')->where('id', $custId)->value('updated_at');

        $this->assertEquals($custCountBefore, $custCountAfter);
        $this->assertEquals($ordersCountBefore, $ordersCountAfter);
        $this->assertEquals($custChecksumBefore, $custChecksumAfter);
    }

    /**
     * TEST 4: Cross-Tenant Isolation & Customer Enumeration Protection
     * Tenant A (100) jamás puede ver o consultar Customer de Tenant B (200).
     */
    public function test_04_cross_tenant_isolation_and_enumeration_protection(): void
    {
        // Cliente A en Tenant 100
        $custA = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Alfa',
            'phone' => '51911111111',
            'customer_phone_canonical' => '51911111111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cliente B en Tenant 200
        $custB = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'name' => 'Cliente Beta',
            'phone' => '51922222222',
            'customer_phone_canonical' => '51922222222',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Tenant 100 cuenta total de clientes (debe ser 1, NO 2)
        TenantContext::setTenantId(100);
        $filter100 = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');
        $resTotal100 = $this->engine->execute('customers.total', $filter100, skipCache: true);
        $this->assertEquals(1, $resTotal100->value);

        // 2. Tenant 200 cuenta total de clientes (debe ser 1, NO 2)
        TenantContext::setTenantId(200);
        $filter200 = new AnalyticsFilterSet(tenantId: 200, timezone: 'America/Lima');
        $resTotal200 = $this->engine->execute('customers.total', $filter200, skipCache: true);
        $this->assertEquals(1, $resTotal200->value);

        // 3. Intento de enumeración cross-tenant: Tenant 100 solicita Customer 360 de custB (Tenant 200)
        TenantContext::setTenantId(100);
        $this->expectException(NotFoundHttpException::class);
        $this->engine->execute('customers.360', $filter100, additionalFilters: ['customer_id' => $custB], skipCache: true);
    }

    /**
     * TEST 5: Customer 360 Full Canonical View (Without Orders vs With Orders)
     */
    public function test_05_customer_360_canonical_view(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();

        // 1. Cliente sin órdenes
        $cZero = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Sin Pedidos',
            'phone' => '51900000000',
            'customer_phone_canonical' => '51900000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resZero = $this->engine->execute('customers.360', $filterSet, additionalFilters: ['customer_id' => $cZero], skipCache: true);
        $this->assertEquals(0, $resZero->value['orders_count']);
        $this->assertEquals(0.00, $resZero->value['total_revenue']);
        $this->assertEquals(0.00, $resZero->value['average_order_value']);
        $this->assertNull($resZero->value['first_order_at']);
        $this->assertNull($resZero->value['last_order_at']);
        $this->assertEquals('111', $resZero->value['rfm']['score']);
        $this->assertEquals('NO_ACTIVITY', $resZero->value['rfm']['segment']);

        // 2. Cliente con múltiples pedidos
        $cMulti = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente VIP Multi',
            'phone' => '51933333333',
            'customer_phone_canonical' => '51933333333',
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        // Orden 1 (hace 40 días): 200.00
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-M-1',
            'customer_name' => 'Cliente VIP Multi',
            'customer_phone' => '51933333333',
            'customer_phone_canonical' => '51933333333',
            'status' => 'DELIVERED',
            'payment_status' => 'PAID',
            'subtotal' => 200.00,
            'total_amount' => 200.00,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        // Orden 2 (hace 5 días): 300.00
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-M-2',
            'customer_name' => 'Cliente VIP Multi',
            'customer_phone' => '51933333333',
            'customer_phone_canonical' => '51933333333',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'subtotal' => 300.00,
            'total_amount' => 300.00,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        // Orden 3 Cancelada (NO debe contar)
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-M-CANCEL',
            'customer_name' => 'Cliente VIP Multi',
            'customer_phone' => '51933333333',
            'customer_phone_canonical' => '51933333333',
            'status' => 'CANCELLED',
            'payment_status' => 'REFUNDED',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $resMulti = $this->engine->execute('customers.360', $filterSet, additionalFilters: ['customer_id' => $cMulti], skipCache: true);
        $this->assertEquals(2, $resMulti->value['orders_count']);
        $this->assertEquals(500.00, $resMulti->value['total_revenue']);
        $this->assertEquals(250.00, $resMulti->value['average_order_value']);
        $this->assertNotNull($resMulti->value['first_order_at']);
        $this->assertNotNull($resMulti->value['last_order_at']);
        $this->assertEquals(5, $resMulti->value['days_since_last_order']);
        $this->assertEquals(500.00, $resMulti->value['customer_revenue_to_date']);
    }

    /**
     * TEST 6: Metrics Calculations (Total, New, Active, Repeat, Purchase Frequency, Recency, Monetary)
     */
    public function test_06_customer_metrics_calculations(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();

        // 3 Clientes registrados en el tenant
        // Cliente 1: Creado hoy, 2 pedidos hoy (100 + 200 = 300) -> Active, Repeat, New
        $c1 = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Activo Repetidor',
            'phone' => '51944440001',
            'customer_phone_canonical' => '51944440001',
            'tag' => 'VIP',
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-C1-A',
            'customer_name' => 'Cliente Activo Repetidor',
            'customer_phone' => '51944440001',
            'customer_phone_canonical' => '51944440001',
            'status' => 'DELIVERED',
            'payment_status' => 'PAID',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-C1-B',
            'customer_name' => 'Cliente Activo Repetidor',
            'customer_phone' => '51944440001',
            'customer_phone_canonical' => '51944440001',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'total_amount' => 200.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        // Cliente 2: Creado hoy, 1 pedido hoy (100) -> Active, Single, New
        $c2 = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Activo Unico',
            'phone' => '51944440002',
            'customer_phone_canonical' => '51944440002',
            'tag' => 'NUEVO',
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-C2-A',
            'customer_name' => 'Cliente Activo Unico',
            'customer_phone' => '51944440002',
            'customer_phone_canonical' => '51944440002',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        // Cliente 3: Creado hace 30 días, 0 pedidos en el periodo -> Inactive in period, Not new
        $c3 = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Inactivo',
            'phone' => '51944440003',
            'customer_phone_canonical' => '51944440003',
            'tag' => 'DORMIDO',
            'created_at' => Carbon::now('America/Lima')->subDays(30)->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->subDays(30)->toDateTimeString(),
        ]);

        TenantContext::setTenantId(100);
        $filterToday = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: $today,
            dateTo: $today,
            timezone: 'America/Lima'
        );

        // 1. Total Customers: 3
        $resTotal = $this->engine->execute('customers.total', $filterToday, skipCache: true);
        $this->assertEquals(3, $resTotal->value);

        // 2. New Customers (registrados hoy): 2
        $resNew = $this->engine->execute('customers.new', $filterToday, skipCache: true);
        $this->assertEquals(2, $resNew->value);

        // 3. Active Customers (con órdenes hoy): 2
        $resActive = $this->engine->execute('customers.active', $filterToday, skipCache: true);
        $this->assertEquals(2, $resActive->value);

        // 4. Repeat Customers (>= 2 órdenes hoy): 1
        $resRepeat = $this->engine->execute('customers.repeat', $filterToday, skipCache: true);
        $this->assertEquals(1, $resRepeat->value);

        // 5. Purchase Frequency (3 órdenes / 2 clientes activos = 1.50):
        $resFreq = $this->engine->execute('customers.purchase_frequency', $filterToday, skipCache: true);
        $this->assertEquals(1.50, $resFreq->value);

        // 6. Monetary Value por cliente activo (400.00 / 2 clientes = 200.00):
        $resMonetary = $this->engine->execute('customers.monetary_value', $filterToday, skipCache: true);
        $this->assertEquals(200.00, $resMonetary->value);

        // 7. Recencia promedio hoy (última orden hoy = 0 días de recencia):
        $resRecency = $this->engine->execute('customers.recency', $filterToday, skipCache: true);
        $this->assertEquals(0.00, $resRecency->value);
    }

    /**
     * TEST 7: Customer Retention Rate across Periods
     */
    public function test_07_customer_retention_rate_calculation(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();
        $yesterday = Carbon::now('America/Lima')->subDay()->toDateString();

        // Cliente R1: Compró ayer y compró hoy -> RETENIDO
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-RET-1A',
            'customer_name' => 'Cliente Retenido',
            'customer_phone' => '51988887771',
            'customer_phone_canonical' => '51988887771',
            'status' => 'DELIVERED',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
        ]);
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-RET-1B',
            'customer_name' => 'Cliente Retenido',
            'customer_phone' => '51988887771',
            'customer_phone_canonical' => '51988887771',
            'status' => 'DELIVERED',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        // Cliente R2: Compró ayer, NO compró hoy -> NO RETENIDO
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-RET-2A',
            'customer_name' => 'Cliente No Retenido',
            'customer_phone' => '51988887772',
            'customer_phone_canonical' => '51988887772',
            'status' => 'DELIVERED',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
        ]);

        TenantContext::setTenantId(100);
        // Periodo actual: today (periodo previo automático: yesterday)
        $filterPeriod = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: $today,
            dateTo: $today,
            timezone: 'America/Lima'
        );

        $resRetention = $this->engine->execute('customers.retention', $filterPeriod, periodKey: 'today', skipCache: true);
        // Base ayer: 2 clientes (R1, R2). Retenido hoy: 1 cliente (R1). Tasa: 1 / 2 * 100 = 50.00%
        $this->assertEquals(50.00, $resRetention->value);
    }

    /**
     * TEST 8: RFM Deterministic Scoring and Tenant Distribution (Policy v1.0)
     */
    public function test_08_rfm_deterministic_scoring_and_tenant_distribution(): void
    {
        // 1. Cliente Campeón (Compró hace 5 días, 10 órdenes, 1500 soles): Score 555 -> CHAMPION
        $cChamp = DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'name' => 'Cliente Campeon',
            'phone' => '51955555555',
            'customer_phone_canonical' => '51955555555',
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);
        for ($i = 1; $i <= 10; $i++) {
            DB::table('orders')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'order_number' => "ORD-CHAMP-{$i}",
                'customer_name' => 'Cliente Campeon',
                'customer_phone' => '51955555555',
                'customer_phone_canonical' => '51955555555',
                'status' => 'DELIVERED',
                'total_amount' => 150.00, // 10 * 150 = 1500
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]);
        }

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resRfmChamp = $this->engine->execute('customers.rfm', $filterSet, additionalFilters: ['customer_id' => $cChamp], skipCache: true);
        $this->assertEquals('555', $resRfmChamp->value['rfm_score']);
        $this->assertEquals('CHAMPION', $resRfmChamp->value['rfm_segment']);

        // 2. Tenant-Wide RFM Distribution
        $resDist = $this->engine->execute('customers.rfm', $filterSet, skipCache: true);
        $this->assertArrayHasKey('distribution', $resDist->value);
        $this->assertGreaterThanOrEqual(1, $resDist->value['distribution']['CHAMPION']);
    }

    /**
     * TEST 9: Empty Dataset Handling (Zero Customers, Zero Orders)
     */
    public function test_09_empty_dataset_handling(): void
    {
        // Tenant 200 no tiene datos
        TenantContext::setTenantId(200);
        $filterSet = new AnalyticsFilterSet(tenantId: 200, timezone: 'America/Lima');

        $resTotal = $this->engine->execute('customers.total', $filterSet, skipCache: true);
        $this->assertEquals('NO_DATA', is_object($resTotal->status) ? $resTotal->status->value : (string) $resTotal->status);
        $this->assertEquals(0, $resTotal->value);

        $resActive = $this->engine->execute('customers.active', $filterSet, skipCache: true);
        $this->assertEquals('NO_DATA', is_object($resActive->status) ? $resActive->status->value : (string) $resActive->status);
        $this->assertEquals(0, $resActive->value);
    }

    /**
     * TEST 10: Authorization and AGENT Scope on Customer Intelligence
     */
    public function test_10_authorization_and_agent_scope(): void
    {
        // 1. Unauthenticated -> Fail closed
        Auth::logout();
        $this->assertFalse(AccessResolver::can('tenant.customers.view', 'customer_360'));

        // 2. Authenticated OWNER on Tenant 100
        $owner = new \App\Modules\Identity\Models\User([
            'id' => 501,
            'tenant_id' => 100,
            'role' => 'OWNER',
        ]);
        $this->actingAs($owner, 'web');
        session(['active_tenant_id' => 100]);
        $this->assertTrue(AccessResolver::can('tenant.customers.view'));

        // 3. AGENT Scope Verification
        $agentUser = new \App\Modules\Identity\Models\User([
            'id' => 502,
            'tenant_id' => 100,
            'role' => 'AGENT',
        ]);
        $scopeClosure = AccessResolver::scope($agentUser, 'customers');
        $this->assertInstanceOf(\Closure::class, $scopeClosure);
    }
}
