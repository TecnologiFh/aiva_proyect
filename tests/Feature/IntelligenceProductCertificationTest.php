<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\MetricEngine;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use App\Modules\Intelligence\Support\MetricDataStatus;
use App\Services\Authorization\AccessResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IntelligenceProductCertificationTest extends TestCase
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
                'name' => 'Tenant Alfa Fashion',
                'slug' => 'tenant-alfa-prod',
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
                'name' => 'Tenant Beta Gadgets',
                'slug' => 'tenant-beta-prod',
                'business_type' => 'Tecnología',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Sembrar Categorías
        DB::table('categories')->updateOrInsert(
            ['id' => 101],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'name' => 'Camisas',
                'slug' => 'camisas-101',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('categories')->updateOrInsert(
            ['id' => 102],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'name' => 'Pantalones',
                'slug' => 'pantalones-102',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('categories')->updateOrInsert(
            ['id' => 201],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 200,
                'name' => 'Smartphones',
                'slug' => 'smartphones-201',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Limpiar datos previos
        DB::table('order_items')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->whereIn('tenant_id', [100, 200]);
        })->delete();
        DB::table('orders')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('product_variants')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('products')->whereIn('tenant_id', [100, 200])->delete();

        $this->engine = app(MetricEngine::class);
        $this->registry = app(MetricRegistry::class);
    }

    /**
     * TEST 1: Metric Registry Registration for Product Intelligence
     */
    public function test_01_product_metric_registry_registration(): void
    {
        $expectedMetrics = [
            'products.sales',
            'products.units_sold',
            'products.revenue',
            'products.orders_count',
            'products.average_revenue_per_order',
            'products.top_by_quantity',
            'products.top_by_revenue',
            'products.sales_share',
            'products.performance',
            'categories.sales',
            'variants.sales',
        ];

        foreach ($expectedMetrics as $metricKey) {
            $this->assertTrue($this->registry->has($metricKey), "Métrica '{$metricKey}' no está registrada en MetricRegistry.");
            $def = $this->registry->get($metricKey);
            $this->assertNotEmpty($def->calculatorClass);
        }
    }

    /**
     * TEST 2: Historical Price Integrity (Mandatory Section 36)
     * Demonstrates that updating products.base_price later does NOT alter historical sales revenue.
     */
    public function test_02_historical_price_integrity(): void
    {
        // 1. Crear producto con precio base histórico = 50.00
        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Camisa Lino',
            'slug' => 'camisa-lino-' . uniqid(),
            'sku' => 'LIN-01',
            'base_price' => 50.00,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // 2. Crear orden hace 10 días: 2 unidades a precio histórico de 50.00 = 100.00
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-HIST-01',
            'customer_name' => 'Cliente Historico',
            'customer_phone' => '51999111222',
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'product_id' => $pId,
            'product_name' => 'Camisa Lino',
            'unit_price' => 50.00,
            'quantity' => 2,
            'subtotal' => 100.00,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // 3. Modificar precio actual del catálogo a 120.00
        DB::table('products')->where('id', $pId)->update([
            'base_price' => 120.00,
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: now()->subDays(15)->toDateString(),
            dateTo: now()->toDateString(),
            timezone: 'America/Lima'
        );

        // 4. Consultar métricas históricas de Intelligence
        $resRev = $this->engine->execute('products.revenue', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);
        $resUnits = $this->engine->execute('products.units_sold', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);

        // El ingreso histórico DEBE ser 100.00, NUNCA 240.00 (2 * 120.00)
        $this->assertEquals(100.00, $resRev->value);
        $this->assertEquals(2, $resUnits->value);
    }

    /**
     * TEST 3: Multi-Tenant Isolation & Cross-Tenant Boundary
     * Tenant 100 cannot read or manipulate Tenant 200 products.
     */
    public function test_03_multi_tenant_isolation_products(): void
    {
        // Producto A en Tenant 100
        $pA = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Camisa Alfa',
            'slug' => 'camisa-alfa-' . uniqid(),
            'sku' => 'ALFA-01',
            'base_price' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oA = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-ALFA-01',
            'customer_name' => 'Cliente Alfa',
            'customer_phone' => '51999111333',
            'status' => 'DELIVERED',
            'total_amount' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oA,
            'product_id' => $pA,
            'product_name' => 'Camisa Alfa',
            'unit_price' => 80.00,
            'quantity' => 1,
            'subtotal' => 80.00,
        ]);

        // Producto B en Tenant 200
        $pB = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'category_id' => 201,
            'name' => 'Smartphone Beta',
            'slug' => 'phone-beta-' . uniqid(),
            'sku' => 'BETA-01',
            'base_price' => 900.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oB = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'order_number' => 'ORD-BETA-01',
            'customer_name' => 'Cliente Beta',
            'customer_phone' => '51999222444',
            'status' => 'DELIVERED',
            'total_amount' => 900.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'order_id' => $oB,
            'product_id' => $pB,
            'product_name' => 'Smartphone Beta',
            'unit_price' => 900.00,
            'quantity' => 1,
            'subtotal' => 900.00,
        ]);

        // Tenant 100 consulta ranking
        TenantContext::setTenantId(100);
        $filter100 = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');
        $resTop100 = $this->engine->execute('products.top_by_quantity', $filter100, skipCache: true);

        $productNames = array_column($resTop100->value, 'product_name');
        $this->assertContains('Camisa Alfa', $productNames);
        $this->assertNotContains('Smartphone Beta', $productNames);

        // Tenant 100 intenta consultar performance de Producto B (Tenant 200) -> FAIL CLOSED (404)
        $this->expectException(NotFoundHttpException::class);
        $this->engine->execute('products.performance', $filter100, additionalFilters: ['product_id' => $pB], skipCache: true);
    }

    /**
     * TEST 4: Product / Variant Integrity (Section 37)
     * Verifies that variants do NOT cause artificial line duplication in product sales aggregates.
     */
    public function test_04_product_variant_integrity_no_duplication(): void
    {
        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Polo Básico',
            'slug' => 'polo-basico-' . uniqid(),
            'sku' => 'POL-BAS',
            'base_price' => 40.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2 variantes en catálogo
        $v1 = DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $pId,
            'sku' => 'POL-BAS-BLA-M',
            'color' => 'Blanco',
            'talla' => 'M',
            'price_override' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $v2 = DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $pId,
            'sku' => 'POL-BAS-NEG-L',
            'color' => 'Negro',
            'talla' => 'L',
            'price_override' => 45.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1 Orden con 1 item de Variante 1 (cantidad 2, subtotal 80.00)
        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-VAR-01',
            'customer_name' => 'Cliente Var',
            'customer_phone' => '51999555111',
            'status' => 'DELIVERED',
            'total_amount' => 80.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oId,
            'product_id' => $pId,
            'product_name' => 'Polo Básico',
            'unit_price' => 40.00,
            'quantity' => 2,
            'subtotal' => 80.00,
            'options_snapshot' => json_encode(['variant_id' => $v1, 'color' => 'Blanco', 'talla' => 'M']),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Métrica agregada de producto
        $resSales = $this->engine->execute('products.sales', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);

        // Debe haber EXACTAMENTE 1 fila agregada para el producto, cantidad 2, revenue 80.00 (sin duplicarse por 2 variantes)
        $this->assertCount(1, $resSales->value);
        $this->assertEquals(2, $resSales->value[0]['quantity']);
        $this->assertEquals(80.00, $resSales->value[0]['revenue']);
        $this->assertEquals(1, $resSales->value[0]['orders_count']);

        // Métrica a nivel variante
        $resVar = $this->engine->execute('variants.sales', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);
        $this->assertCount(2, $resVar->value);
        $v1Data = collect($resVar->value)->firstWhere('variant_id', $v1);
        $v2Data = collect($resVar->value)->firstWhere('variant_id', $v2);

        $this->assertEquals(2, $v1Data['quantity']);
        $this->assertEquals(80.00, $v1Data['revenue']);
        $this->assertEquals(0, $v2Data['quantity']);
        $this->assertEquals(0.00, $v2Data['revenue']);
    }

    /**
     * TEST 5: Product Calculations (Sales, Units, Orders Count, AOV, Sales Share)
     */
    public function test_05_product_sales_calculations(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();

        // Producto 1: Pantalón Chino (Categoría 102)
        $p1 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 102,
            'name' => 'Pantalón Chino',
            'slug' => 'pantalon-chino-' . uniqid(),
            'sku' => 'PAN-CH',
            'base_price' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Producto 2: Camisa Oxford (Categoría 101)
        $p2 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Camisa Oxford',
            'slug' => 'camisa-oxford-' . uniqid(),
            'sku' => 'CAM-OX',
            'base_price' => 60.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Orden 1: 2 Pantalones (200.00) + 1 Camisa (60.00) = 260.00
        $o1 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-MIX-01',
            'customer_name' => 'Cliente Mix',
            'customer_phone' => '51999777111',
            'status' => 'DELIVERED',
            'total_amount' => 260.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'order_id' => $o1, 'product_id' => $p1, 'product_name' => 'Pantalón Chino', 'unit_price' => 100.00, 'quantity' => 2, 'subtotal' => 200.00, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'order_id' => $o1, 'product_id' => $p2, 'product_name' => 'Camisa Oxford', 'unit_price' => 60.00, 'quantity' => 1, 'subtotal' => 60.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Orden 2: 1 Pantalón (100.00)
        $o2 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-MIX-02',
            'customer_name' => 'Cliente Solo Pantalon',
            'customer_phone' => '51999777222',
            'status' => 'CONFIRMED',
            'total_amount' => 100.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'order_id' => $o2, 'product_id' => $p1, 'product_name' => 'Pantalón Chino', 'unit_price' => 100.00, 'quantity' => 1, 'subtotal' => 100.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        TenantContext::setTenantId(100);
        $filterToday = new AnalyticsFilterSet(
            tenantId: 100,
            dateFrom: $today,
            dateTo: $today,
            timezone: 'America/Lima'
        );

        // 1. Pantalón: 3 unidades, 300.00 soles, 2 pedidos, AOV = 150.00
        $unitsP1 = $this->engine->execute('products.units_sold', $filterToday, additionalFilters: ['product_id' => $p1], skipCache: true);
        $revP1 = $this->engine->execute('products.revenue', $filterToday, additionalFilters: ['product_id' => $p1], skipCache: true);
        $ordP1 = $this->engine->execute('products.orders_count', $filterToday, additionalFilters: ['product_id' => $p1], skipCache: true);
        $aovP1 = $this->engine->execute('products.average_revenue_per_order', $filterToday, additionalFilters: ['product_id' => $p1], skipCache: true);

        $this->assertEquals(3, $unitsP1->value);
        $this->assertEquals(300.00, $revP1->value);
        $this->assertEquals(2, $ordP1->value);
        $this->assertEquals(150.00, $aovP1->value);

        // 2. Sales Share: Total ventas = 360.00 (P1: 300 / 360 * 100 = 83.33%)
        $shareP1 = $this->engine->execute('products.sales_share', $filterToday, additionalFilters: ['product_id' => $p1], skipCache: true);
        $this->assertEquals(83.33, $shareP1->value);

        // 3. Category Sales (Pantalones: 300.00, Camisas: 60.00)
        $catSales = $this->engine->execute('categories.sales', $filterToday, skipCache: true);
        $this->assertCount(2, $catSales->value);
        $this->assertEquals('Pantalones', $catSales->value[0]['category_name']);
        $this->assertEquals(300.00, $catSales->value[0]['revenue']);
    }

    /**
     * TEST 6: Top Products Rankings by Quantity and by Revenue
     */
    public function test_06_top_products_rankings(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();

        // P1: Barato pero alta cantidad (10 unidades x 10.00 = 100.00)
        $p1 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Medias Pack',
            'slug' => 'medias-' . uniqid(),
            'sku' => 'MED-01',
            'base_price' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // P2: Caro pero baja cantidad (1 unidad x 500.00 = 500.00)
        $p2 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Saco Elegante',
            'slug' => 'saco-' . uniqid(),
            'sku' => 'SAC-01',
            'base_price' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-TOP-01',
            'customer_name' => 'Cliente Top',
            'customer_phone' => '51999888999',
            'status' => 'DELIVERED',
            'total_amount' => 600.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'order_id' => $oId, 'product_id' => $p1, 'product_name' => 'Medias Pack', 'unit_price' => 10.00, 'quantity' => 10, 'subtotal' => 100.00, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'order_id' => $oId, 'product_id' => $p2, 'product_name' => 'Saco Elegante', 'unit_price' => 500.00, 'quantity' => 1, 'subtotal' => 500.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        TenantContext::setTenantId(100);
        $filterToday = new AnalyticsFilterSet(tenantId: 100, dateFrom: $today, dateTo: $today, timezone: 'America/Lima');

        // Top by quantity: Medias primero
        $topQty = $this->engine->execute('products.top_by_quantity', $filterToday, additionalFilters: ['limit' => 5], skipCache: true);
        $this->assertEquals('Medias Pack', $topQty->value[0]['product_name']);
        $this->assertEquals(10, $topQty->value[0]['quantity']);

        // Top by revenue: Saco primero
        $topRev = $this->engine->execute('products.top_by_revenue', $filterToday, additionalFilters: ['limit' => 5], skipCache: true);
        $this->assertEquals('Saco Elegante', $topRev->value[0]['product_name']);
        $this->assertEquals(500.00, $topRev->value[0]['revenue']);
    }

    /**
     * TEST 7: Product Performance with Period Comparison (Current vs Previous)
     */
    public function test_07_product_performance_period_comparison(): void
    {
        $today = Carbon::now('America/Lima')->toDateString();

        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Billetera Cuero',
            'slug' => 'billetera-' . uniqid(),
            'sku' => 'BIL-01',
            'base_price' => 70.00,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        // Venta ayer (periodo previo): 1 unidad x 70.00 = 70.00
        $oPrev = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-COMP-PREV',
            'customer_name' => 'Cliente Ayer',
            'customer_phone' => '51999444111',
            'status' => 'DELIVERED',
            'total_amount' => 70.00,
            'created_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oPrev,
            'product_id' => $pId,
            'product_name' => 'Billetera Cuero',
            'unit_price' => 70.00,
            'quantity' => 1,
            'subtotal' => 70.00,
            'created_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->subDay()->toDateTimeString(),
        ]);

        // Venta hoy (periodo actual): 2 unidades x 70.00 = 140.00 (+100%)
        $oCurr = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_number' => 'ORD-COMP-CURR',
            'customer_name' => 'Cliente Hoy',
            'customer_phone' => '51999444222',
            'status' => 'DELIVERED',
            'total_amount' => 140.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'order_id' => $oCurr,
            'product_id' => $pId,
            'product_name' => 'Billetera Cuero',
            'unit_price' => 70.00,
            'quantity' => 2,
            'subtotal' => 140.00,
            'created_at' => Carbon::now('America/Lima')->toDateTimeString(),
            'updated_at' => Carbon::now('America/Lima')->toDateTimeString(),
        ]);

        TenantContext::setTenantId(100);
        $filterToday = new AnalyticsFilterSet(tenantId: 100, dateFrom: $today, dateTo: $today, timezone: 'America/Lima');

        $resPerf = $this->engine->execute('products.performance', $filterToday, periodKey: 'today', additionalFilters: ['product_id' => $pId], skipCache: true);

        $this->assertEquals(140.00, $resPerf->value['revenue']);
        $this->assertEquals(2, $resPerf->value['units_sold']);
        $this->assertNotNull($resPerf->value['revenue_comparison']);
        $this->assertEquals(70.00, $resPerf->value['revenue_comparison']['previous_value']);
        $this->assertEquals(70.00, $resPerf->value['revenue_comparison']['absolute_change']);
        $this->assertEquals(100.00, $resPerf->value['revenue_comparison']['percentage_change']);
        $this->assertEquals('UP', $resPerf->value['revenue_comparison']['trend']);
    }

    /**
     * TEST 8: Empty Catalogue Handling
     */
    public function test_08_empty_catalogue_handling(): void
    {
        TenantContext::setTenantId(200);
        $filterSet = new AnalyticsFilterSet(tenantId: 200, timezone: 'America/Lima');

        $resSales = $this->engine->execute('products.sales', $filterSet, skipCache: true);
        $this->assertEquals('NO_DATA', is_object($resSales->status) ? $resSales->status->value : (string) $resSales->status);
        $this->assertEquals(0, $resSales->value);
    }

    /**
     * TEST 9: Read-Only Enforcement & Zero Mutations on Product Intelligence
     */
    public function test_09_read_only_zero_mutations(): void
    {
        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 101,
            'name' => 'Gorra Trucker',
            'slug' => 'gorra-trucker-' . uniqid(),
            'sku' => 'GOR-01',
            'base_price' => 35.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prodCountBefore = DB::table('products')->count();
        $orderCountBefore = DB::table('orders')->count();
        $itemCountBefore = DB::table('order_items')->count();
        $prodChecksumBefore = DB::table('products')->where('id', $pId)->value('updated_at');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $this->engine->execute('products.sales', $filterSet, skipCache: true);
        $this->engine->execute('products.top_by_revenue', $filterSet, skipCache: true);
        $this->engine->execute('products.revenue', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);
        $this->engine->execute('products.performance', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);

        $this->assertEquals($prodCountBefore, DB::table('products')->count());
        $this->assertEquals($orderCountBefore, DB::table('orders')->count());
        $this->assertEquals($itemCountBefore, DB::table('order_items')->count());
        $this->assertEquals($prodChecksumBefore, DB::table('products')->where('id', $pId)->value('updated_at'));
    }

    /**
     * TEST 10: Authorization & AGENT Scope on Product Intelligence
     */
    public function test_10_authorization_and_rbac(): void
    {
        // 1. Unauthenticated -> Fail-closed
        Auth::logout();
        $this->assertFalse(AccessResolver::can('tenant.catalog.view', 'catalog_inventory'));

        // 2. Authenticated OWNER on Tenant 100
        $owner = new \App\Modules\Identity\Models\User([
            'id' => 601,
            'tenant_id' => 100,
            'role' => 'OWNER',
        ]);
        $this->actingAs($owner, 'web');
        session(['active_tenant_id' => 100]);
        $this->assertTrue(AccessResolver::can('tenant.catalog.view'));

        // 3. AGENT Scope Verification
        $agentUser = new \App\Modules\Identity\Models\User([
            'id' => 602,
            'tenant_id' => 100,
            'role' => 'AGENT',
        ]);
        $scopeClosure = AccessResolver::scope($agentUser, 'orders');
        $this->assertInstanceOf(\Closure::class, $scopeClosure);
    }
}
