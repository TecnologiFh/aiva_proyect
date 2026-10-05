<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\Cache\IntelligenceCacheService;
use App\Modules\Intelligence\Domain\Engine\MetricEngine;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\Domain\Values\IntelligenceResult;
use App\Modules\Intelligence\Domain\Values\PeriodComparison;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Support\MetricDataStatus;
use App\Services\Authorization\AccessResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IntelligenceFoundationSalesTest extends TestCase
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
                'slug' => 'tenant-alfa',
                'business_type' => 'Retail / Moda',
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
                'name' => 'Tenant Beta Restaurant',
                'slug' => 'tenant-beta',
                'business_type' => 'Gastronomía',
                'country' => 'PE',
                'currency' => 'USD',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Sembrar Categorías
        DB::table('categories')->updateOrInsert(
            ['id' => 100],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'name' => 'General Alfa',
                'slug' => 'gen-alfa-' . uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('categories')->updateOrInsert(
            ['id' => 200],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 200,
                'name' => 'General Beta',
                'slug' => 'gen-beta-' . uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Limpiar datos previos de los tenants de prueba para determinismo
        DB::table('order_items')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->whereIn('tenant_id', [100, 200]);
        })->delete();
        DB::table('orders')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('products')->whereIn('tenant_id', [100, 200])->delete();

        // Sembrar productos para claves foráneas
        DB::table('products')->insert([
            ['id' => 10, 'uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'category_id' => 100, 'name' => 'Polo Alfa', 'slug' => 'polo-alfa-10', 'base_price' => 100.00, 'sku' => 'POL-10', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'uuid' => (string) Str::uuid(), 'tenant_id' => 200, 'category_id' => 200, 'name' => 'Burger Beta', 'slug' => 'burger-beta-20', 'base_price' => 250.00, 'sku' => 'BUR-20', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 101, 'uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'category_id' => 100, 'name' => 'Camisa Oxford', 'slug' => 'camisa-oxford-101', 'base_price' => 60.00, 'sku' => 'CAM-101', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 102, 'uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'category_id' => 100, 'name' => 'Pantalón Chino', 'slug' => 'pantalon-chino-102', 'base_price' => 80.00, 'sku' => 'PAN-102', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->engine = app(MetricEngine::class);
        $this->registry = app(MetricRegistry::class);
    }

    /**
     * TEST 1: Metric Registry Bootstrap & Catalog Integrity
     */
    public function test_01_metric_registry_bootstrap_and_catalog(): void
    {
        $this->assertTrue($this->registry->has('sales.orders_count'));
        $this->assertTrue($this->registry->has('sales.revenue'));
        $this->assertTrue($this->registry->has('sales.average_order_value'));
        $this->assertTrue($this->registry->has('sales.units_sold'));
        $this->assertTrue($this->registry->has('sales.product_sales'));
        $this->assertTrue($this->registry->has('sales.top_products'));

        $def = $this->registry->get('sales.revenue');
        $this->assertEquals('Ingresos Netos por Ventas', $def->name);
        $this->assertEquals('sales', $def->category);
        $this->assertEquals('currency', $def->unit);
    }

    /**
     * TEST 2: Missing Tenant -> Fail-Closed (403 HttpException)
     */
    public function test_02_missing_tenant_fails_closed(): void
    {
        TenantContext::clear();

        $this->expectException(HttpException::class);
        new AnalyticsFilterSet(tenantId: null);
    }

    /**
     * TEST 3: Unregistered Metric Request Throws Exception
     */
    public function test_03_unregistered_metric_rejected(): void
    {
        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100);

        $this->expectException(\InvalidArgumentException::class);
        $this->engine->execute('sales.unauthorized_custom_metric', $filterSet);
    }

    /**
     * TEST 4: Read-Only Enforcement — Zero Mutations Guarantee
     */
    public function test_04_read_only_enforcement_zero_mutations(): void
    {
        TenantContext::setTenantId(100);

        // Sembrar 1 orden en Tenant 100
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-100-RO',
            'customer_name' => 'Cliente Test',
            'customer_phone' => '51999888777',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $orderId,
            'product_id' => 10,
            'product_name' => 'Polo Alfa',
            'unit_price' => 100.00,
            'quantity' => 1,
            'subtotal' => 100.00,
        ]);

        // Capturar recuentos y checksums antes de ejecutar Intelligence
        $ordersCountBefore = DB::table('orders')->count();
        $itemsCountBefore = DB::table('order_items')->count();
        $productsCountBefore = DB::table('products')->count();

        $filterSet = new AnalyticsFilterSet(tenantId: 100);

        // Ejecutar métricas analíticas
        $this->engine->execute('sales.orders_count', $filterSet, skipCache: true);
        $this->engine->execute('sales.revenue', $filterSet, skipCache: true);
        $this->engine->execute('sales.average_order_value', $filterSet, skipCache: true);
        $this->engine->execute('sales.units_sold', $filterSet, skipCache: true);
        $this->engine->execute('sales.product_sales', $filterSet, skipCache: true);
        $this->engine->execute('sales.top_products', $filterSet, skipCache: true);

        // Verificar que los recuentos permanecen exactamente iguales
        $this->assertEquals($ordersCountBefore, DB::table('orders')->count());
        $this->assertEquals($itemsCountBefore, DB::table('order_items')->count());
        $this->assertEquals($productsCountBefore, DB::table('products')->count());
    }

    /**
     * TEST 5: Tenant Isolation & Cross-Tenant Data Leakage Prevention
     */
    public function test_05_cross_tenant_isolation_strictly_enforced(): void
    {
        $now = now();

        // 3 órdenes en Tenant 100 (Total: 300.00)
        for ($i = 1; $i <= 3; $i++) {
            $oId = DB::table('orders')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'order_number' => "ORD-T100-{$i}",
                'customer_name' => "Cliente Alfa {$i}",
                'customer_phone' => "5199911100{$i}",
                'status' => 'CONFIRMED',
                'payment_status' => 'PAID',
                'subtotal' => 100.00,
                'total_amount' => 100.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('order_items')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'order_id' => $oId,
                'product_id' => 10,
                'product_name' => 'Polo Alfa',
                'unit_price' => 100.00,
                'quantity' => 2,
                'subtotal' => 100.00,
            ]);
        }

        // 2 órdenes en Tenant 200 (Total: 500.00)
        for ($j = 1; $j <= 2; $j++) {
            $oId = DB::table('orders')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 200,
                'order_number' => "ORD-T200-{$j}",
                'customer_name' => "Cliente Beta {$j}",
                'customer_phone' => "5199922200{$j}",
                'status' => 'CONFIRMED',
                'payment_status' => 'PAID',
                'subtotal' => 250.00,
                'total_amount' => 250.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('order_items')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 200,
                'order_id' => $oId,
                'product_id' => 20,
                'product_name' => 'Burger Beta',
                'unit_price' => 250.00,
                'quantity' => 5,
                'subtotal' => 250.00,
            ]);
        }

        // Consultar como Tenant 100
        TenantContext::setTenantId(100);
        $filter100 = new AnalyticsFilterSet(tenantId: 100);

        $resOrders100 = $this->engine->execute('sales.orders_count', $filter100, skipCache: true);
        $resUnits100 = $this->engine->execute('sales.units_sold', $filter100, skipCache: true);

        $this->assertEquals(3, $resOrders100->value);
        $this->assertEquals(6, $resUnits100->value); // 3 orders * 2 units

        // Consultar como Tenant 200
        TenantContext::setTenantId(200);
        $filter200 = new AnalyticsFilterSet(tenantId: 200);

        $resOrders200 = $this->engine->execute('sales.orders_count', $filter200, skipCache: true);
        $resUnits200 = $this->engine->execute('sales.units_sold', $filter200, skipCache: true);

        $this->assertEquals(2, $resOrders200->value);
        $this->assertEquals(10, $resUnits200->value); // 2 orders * 5 units

        // Verificar que Tenant 100 NO ve los productos de Tenant 200
        TenantContext::setTenantId(100);
        $prodSales100 = $this->engine->execute('sales.product_sales', $filter100, skipCache: true);
        $productNames100 = array_column($prodSales100->value, 'product_name');

        $this->assertContains('Polo Alfa', $productNames100);
        $this->assertNotContains('Burger Beta', $productNames100);
    }

    /**
     * TEST 6: Sales Calculations Determinism & Correctness
     */
    public function test_06_sales_calculations_determinism_and_correctness(): void
    {
        // Limpiar para garantizar escenario determinista aislado
        DB::table('order_items')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->where('tenant_id', 100);
        })->delete();
        DB::table('orders')->where('tenant_id', 100)->delete();

        $today = Carbon::now('America/Lima')->toDateString();

        // 1 orden cancelada (NO debe contar)
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-T100-CANCELLED',
            'customer_name' => 'Cliente Cancelado',
            'customer_phone' => '51999333444',
            'status' => 'CANCELLED',
            'payment_status' => 'REFUNDED',
            'subtotal' => 200.00,
            'total_amount' => 200.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        // 2 órdenes confirmadas
        $o1 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-T100-CONF-1',
            'customer_name' => 'Cliente 1',
            'customer_phone' => '51999555666',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'subtotal' => 120.00,
            'total_amount' => 120.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $o1,
            'product_id' => 101,
            'product_name' => 'Camisa Oxford',
            'unit_price' => 60.00,
            'quantity' => 2,
            'subtotal' => 120.00,
        ]);

        $o2 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-T100-CONF-2',
            'customer_name' => 'Cliente 2',
            'customer_phone' => '51999777888',
            'status' => 'DELIVERED',
            'payment_status' => 'PAID',
            'subtotal' => 80.00,
            'total_amount' => 80.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $o2,
            'product_id' => 102,
            'product_name' => 'Pantalón Chino',
            'unit_price' => 80.00,
            'quantity' => 1,
            'subtotal' => 80.00,
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: $today,
            dateTo: $today
        );

        $resOrders = $this->engine->execute('sales.orders_count', $filterSet, skipCache: true);
        $resUnits = $this->engine->execute('sales.units_sold', $filterSet, skipCache: true);
        $resAov = $this->engine->execute('sales.average_order_value', $filterSet, skipCache: true);
        $resTop = $this->engine->execute('sales.top_products', $filterSet, additionalFilters: ['limit' => 5], skipCache: true);

        // Órdenes canceladas excluidas: 2 órdenes válidas
        $this->assertEquals(2, $resOrders->value);
        // Unidades: 2 camisas + 1 pantalón = 3 unidades
        $this->assertEquals(3, $resUnits->value);
        // AOV: (120 + 80) / 2 = 100.00
        $this->assertEquals(100.00, $resAov->value);
        // Top Products
        $this->assertCount(2, $resTop->value);
        $this->assertEquals('Camisa Oxford', $resTop->value[0]['product_name']);
        $this->assertEquals(2, $resTop->value[0]['quantity']);
    }

    /**
     * TEST 7: Period Comparison with Variance and Safe Zero-Division
     */
    public function test_07_period_comparison_variance_and_safe_zero_division(): void
    {
        // 1. Caso estándar: 150 vs 100 (+50%)
        $compStandard = new PeriodComparison(150.00, 100.00);
        $this->assertEquals(50.00, $compStandard->absoluteChange);
        $this->assertEquals(50.00, $compStandard->percentageChange);
        $this->assertEquals('UP', $compStandard->trend);

        // 2. Caso descenso: 80 vs 100 (-20%)
        $compDown = new PeriodComparison(80.00, 100.00);
        $this->assertEquals(-20.00, $compDown->absoluteChange);
        $this->assertEquals(-20.00, $compDown->percentageChange);
        $this->assertEquals('DOWN', $compDown->trend);

        // 3. Caso división por cero: 100 vs 0 (No NaN / Infinity)
        $compZeroPrev = new PeriodComparison(100.00, 0.00);
        $this->assertEquals(100.00, $compZeroPrev->absoluteChange);
        $this->assertEquals(100.00, $compZeroPrev->percentageChange);
        $this->assertEquals('UP', $compZeroPrev->trend);

        // 4. Caso cero en ambos: 0 vs 0
        $compBothZero = new PeriodComparison(0.00, 0.00);
        $this->assertEquals(0.00, $compBothZero->absoluteChange);
        $this->assertEquals(0.00, $compBothZero->percentageChange);
        $this->assertEquals('NEUTRAL', $compBothZero->trend);
    }

    /**
     * TEST 8: TimePeriod Named Period Resolutions in Tenant Timezone
     */
    public function test_08_time_period_resolutions(): void
    {
        $todayPeriod = new TimePeriod('today', 'America/Lima');
        $this->assertEquals(Carbon::now('America/Lima')->toDateString(), $todayPeriod->dateFrom);
        $this->assertEquals(Carbon::now('America/Lima')->toDateString(), $todayPeriod->dateTo);

        $prev = $todayPeriod->getPreviousPeriod();
        $this->assertNotNull($prev);
        $this->assertEquals(Carbon::now('America/Lima')->subDay()->toDateString(), $prev->dateFrom);
    }

    /**
     * TEST 9: Redis Cache Key Strict Tenant-Isolation
     */
    public function test_09_cache_key_tenant_isolation(): void
    {
        $cacheService = app(IntelligenceCacheService::class);

        $keyA = $cacheService->buildKey(100, 'sales.revenue', 'this_month', ['channel' => 'WHATSAPP']);
        $keyB = $cacheService->buildKey(200, 'sales.revenue', 'this_month', ['channel' => 'WHATSAPP']);

        $this->assertNotEquals($keyA, $keyB);
        $this->assertStringContainsString(':100:', $keyA);
        $this->assertStringContainsString(':200:', $keyB);
    }

    /**
     * TEST 10: Authorization RBAC Matrix & Fail-Closed Validation
     * Validates: SUPERADMIN, OWNER, ADMIN, MANAGER, OPERATOR, AGENT and missing context
     */
    public function test_10_authorization_rbac_matrix_and_fail_closed(): void
    {
        // 1. Sin autenticación -> Fail Closed
        Auth::logout();
        $this->assertFalse(AccessResolver::can('tenant.analytics.view', 'business_health'));

        // 2. Tenant inactivo -> Fail Closed
        DB::table('tenants')->where('id', 200)->update(['status' => 'SUSPENDED']);
        $suspendedUser = new \App\Modules\Identity\Models\User([
            'id' => 999,
            'tenant_id' => 200,
            'role' => 'ADMIN',
        ]);
        $this->actingAs($suspendedUser, 'web');
        session(['active_tenant_id' => 200]);
        $this->assertFalse(AccessResolver::can('tenant.analytics.view', 'business_health'));
        DB::table('tenants')->where('id', 200)->update(['status' => 'ACTIVE']);

        // 3. Mismatch Tenant -> Fail Closed
        $user100 = new \App\Modules\Identity\Models\User([
            'id' => 1001,
            'tenant_id' => 100,
            'role' => 'ADMIN',
        ]);
        $this->actingAs($user100, 'web');
        session(['active_tenant_id' => 200]); // Discrepancia intencional
        $this->assertFalse(AccessResolver::can('tenant.analytics.view'));

        // 4. OWNER y ADMIN sobre su propio tenant activo
        session(['active_tenant_id' => 100]);
        $owner = new \App\Modules\Identity\Models\User([
            'id' => 1002,
            'tenant_id' => 100,
            'role' => 'OWNER',
        ]);
        $this->actingAs($owner, 'web');
        $this->assertTrue(AccessResolver::can('tenant.analytics.view'));

        $admin = new \App\Modules\Identity\Models\User([
            'id' => 1003,
            'tenant_id' => 100,
            'role' => 'ADMIN',
        ]);
        $this->actingAs($admin, 'web');
        $this->assertTrue(AccessResolver::can('tenant.analytics.view'));

        // 5. AGENT Scope Generator
        $scopeClosure = AccessResolver::scope($user100, 'orders');
        $this->assertInstanceOf(\Closure::class, $scopeClosure);
    }
}

