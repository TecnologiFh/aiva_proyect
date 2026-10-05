<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\MetricEngine;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Support\MetricDataStatus;
use App\Services\Authorization\AccessResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IntelligenceInventoryCertificationTest extends TestCase
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
                'name' => 'Tenant Alfa Inventory',
                'slug' => 'tenant-alfa-inv',
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
                'name' => 'Tenant Beta Inventory',
                'slug' => 'tenant-beta-inv',
                'business_type' => 'Almacén',
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
            ['id' => 301],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 100,
                'name' => 'Ropa Inv',
                'slug' => 'ropa-inv-301',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Limpiar datos previos
        DB::table('inventory_movements')->whereIn('tenant_id', [100, 200])->delete();
        DB::table('inventory_stocks')->whereIn('tenant_id', [100, 200])->delete();
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
     * TEST 1: Inventory Metric Registry Bootstrap
     */
    public function test_01_inventory_metric_registry_bootstrap(): void
    {
        $expectedMetrics = [
            'inventory.stock',
            'inventory.total_stock',
            'inventory.available_stock',
            'inventory.out_of_stock',
            'inventory.low_stock',
            'inventory.stock_by_product',
            'inventory.stock_by_variant',
        ];

        foreach ($expectedMetrics as $metricKey) {
            $this->assertTrue($this->registry->has($metricKey), "Falta métrica '{$metricKey}' en MetricRegistry.");
            $def = $this->registry->get($metricKey);
            $this->assertEquals('inventory', $def->category);
            $this->assertNotEmpty($def->calculatorClass);
        }
    }

    /**
     * TEST 2: Stock Authority & Total Stock Aggregation
     * Verifies that stock is read from products and inventory_stocks canonically.
     */
    public function test_02_stock_authority_and_total_stock(): void
    {
        // Producto 1: 50 unidades directas en products.stock
        $p1 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Camisa Algodón',
            'slug' => 'camisa-algodon-' . uniqid(),
            'sku' => 'CAM-ALG',
            'base_price' => 45.00,
            'stock' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Producto 2: 100 unidades con track_inventory en inventory_stocks (20 reservadas)
        $p2 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Pantalón Jean',
            'slug' => 'pantalon-jean-' . uniqid(),
            'sku' => 'PAN-JEA',
            'base_price' => 80.00,
            'stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_stocks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $p2,
            'current_stock' => 100,
            'reserved_stock' => 20,
            'minimum_stock' => 15,
            'track_inventory' => true,
            'is_out_of_stock' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Total Stock: 50 (P1) + 100 (P2) = 150 unidades
        $resTotal = $this->engine->execute('inventory.total_stock', $filterSet, skipCache: true);
        $this->assertEquals(150, $resTotal->value);

        // Available Stock (sin reservas): 50 (P1) + 80 (P2: 100-20) = 130 unidades
        $resAvailable = $this->engine->execute('inventory.available_stock', $filterSet, skipCache: true);
        $this->assertEquals(130, $resAvailable->value);
    }

    /**
     * TEST 3: Product / Variant Stock Integrity (No Multiplication)
     * Multiple variants for a product do not duplicate or multiply total stock.
     */
    public function test_03_product_variant_stock_integrity_no_multiplication(): void
    {
        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Vestido Seda',
            'slug' => 'vestido-seda-' . uniqid(),
            'sku' => 'VES-SED',
            'base_price' => 120.00,
            'stock' => 0, // Stock distribuido en variantes
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3 variantes: 10 + 15 + 25 = 50 unidades en total
        DB::table('product_variants')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'product_id' => $pId, 'sku' => 'VES-SED-S', 'color' => 'Rojo', 'talla' => 'S', 'stock' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'product_id' => $pId, 'sku' => 'VES-SED-M', 'color' => 'Rojo', 'talla' => 'M', 'stock' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 100, 'product_id' => $pId, 'sku' => 'VES-SED-L', 'color' => 'Rojo', 'talla' => 'L', 'stock' => 25, 'created_at' => now(), 'updated_at' => now()],
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Stock a nivel producto: debe sumar exactamente 50 unidades (sin duplicación)
        $resProduct = $this->engine->execute('inventory.stock', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);
        $this->assertEquals(50, $resProduct->value['current_stock']);
        $this->assertEquals(50, $resProduct->value['available_stock']);

        // Stock total del tenant
        $resTotal = $this->engine->execute('inventory.total_stock', $filterSet, skipCache: true);
        $this->assertEquals(50, $resTotal->value);

        // Stock a nivel variante: 3 filas
        $resVariant = $this->engine->execute('inventory.stock_by_variant', $filterSet, additionalFilters: ['product_id' => $pId], skipCache: true);
        $this->assertCount(3, $resVariant->value);
    }

    /**
     * TEST 4: Out of Stock Metric
     * Correctly identifies products with available_stock <= 0.
     */
    public function test_04_out_of_stock_metric(): void
    {
        // P1: En stock (30)
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Polo Blanco',
            'slug' => 'polo-blanco-' . uniqid(),
            'sku' => 'POL-BLA',
            'base_price' => 30.00,
            'stock' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // P2: Agotado directo (stock = 0)
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Polo Negro Agotado',
            'slug' => 'polo-negro-' . uniqid(),
            'sku' => 'POL-NEG',
            'base_price' => 30.00,
            'stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // P3: Agotado por reservas (current 10 - reserved 10 = available 0)
        $p3 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Polo Azul Reservado',
            'slug' => 'polo-azul-' . uniqid(),
            'sku' => 'POL-AZU',
            'base_price' => 30.00,
            'stock' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_stocks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $p3,
            'current_stock' => 10,
            'reserved_stock' => 10,
            'minimum_stock' => 0,
            'track_inventory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Productos agotados: P2 (0) y P3 (10-10=0) -> 2 productos agotados
        $resOutOfStock = $this->engine->execute('inventory.out_of_stock', $filterSet, skipCache: true);
        $this->assertEquals(2, $resOutOfStock->value);
    }

    /**
     * TEST 5: Low Stock Metric with Canonical minimum_stock Threshold
     */
    public function test_05_low_stock_metric_canonical_threshold(): void
    {
        // P1: Stock saludable (current 50, minimum 10) -> NO bajo
        $p1 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Zapato Formal',
            'slug' => 'zapato-formal-' . uniqid(),
            'sku' => 'ZAP-FOR',
            'base_price' => 150.00,
            'stock' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_stocks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $p1,
            'current_stock' => 50,
            'reserved_stock' => 0,
            'minimum_stock' => 10,
            'track_inventory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // P2: Stock bajo canónico (current 8, minimum 10) -> BAJO STOCK
        $p2 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Zapato Casual',
            'slug' => 'zapato-casual-' . uniqid(),
            'sku' => 'ZAP-CAS',
            'base_price' => 130.00,
            'stock' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_stocks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'product_id' => $p2,
            'current_stock' => 8,
            'reserved_stock' => 0,
            'minimum_stock' => 10,
            'track_inventory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $resLow = $this->engine->execute('inventory.low_stock', $filterSet, skipCache: true);
        $this->assertEquals(1, $resLow->value);
    }

    /**
     * TEST 6: Multi-Tenant Isolation & Cross-Tenant Boundary
     * Tenant 100 cannot read Tenant 200 stock.
     */
    public function test_06_multi_tenant_isolation_inventory(): void
    {
        // Producto y Stock en Tenant 100
        $pA = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Producto Alfa Inv',
            'slug' => 'prod-alfa-inv-' . uniqid(),
            'sku' => 'ALF-INV',
            'base_price' => 100.00,
            'stock' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Producto y Stock en Tenant 200
        $pB = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 200,
            'category_id' => 301,
            'name' => 'Producto Beta Inv',
            'slug' => 'prod-beta-inv-' . uniqid(),
            'sku' => 'BET-INV',
            'base_price' => 200.00,
            'stock' => 900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setTenantId(100);
        $filter100 = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        // Total stock de Tenant 100: sólo 80 (NO 980)
        $resTotal100 = $this->engine->execute('inventory.total_stock', $filter100, skipCache: true);
        $this->assertEquals(80, $resTotal100->value);

        // Tenant 100 intenta consultar stock de Producto B de Tenant 200 -> Fail-closed 404
        $this->expectException(NotFoundHttpException::class);
        $this->engine->execute('inventory.stock', $filter100, additionalFilters: ['product_id' => $pB], skipCache: true);
    }

    /**
     * TEST 7: Read-Only Enforcement — Zero Mutations on Inventory Execution
     */
    public function test_07_read_only_enforcement_zero_mutations(): void
    {
        $pId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 100,
            'category_id' => 301,
            'name' => 'Item Read Only',
            'slug' => 'item-ro-' . uniqid(),
            'sku' => 'RO-01',
            'base_price' => 25.00,
            'stock' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prodCountBefore = DB::table('products')->count();
        $invStockCountBefore = DB::table('inventory_stocks')->count();
        $movementsCountBefore = DB::table('inventory_movements')->count();
        $prodChecksumBefore = DB::table('products')->where('id', $pId)->value('updated_at');

        TenantContext::setTenantId(100);
        $filterSet = new AnalyticsFilterSet(tenantId: 100, timezone: 'America/Lima');

        $this->engine->execute('inventory.stock', $filterSet, skipCache: true);
        $this->engine->execute('inventory.total_stock', $filterSet, skipCache: true);
        $this->engine->execute('inventory.available_stock', $filterSet, skipCache: true);
        $this->engine->execute('inventory.out_of_stock', $filterSet, skipCache: true);
        $this->engine->execute('inventory.low_stock', $filterSet, skipCache: true);
        $this->engine->execute('inventory.stock_by_product', $filterSet, skipCache: true);

        $this->assertEquals($prodCountBefore, DB::table('products')->count());
        $this->assertEquals($invStockCountBefore, DB::table('inventory_stocks')->count());
        $this->assertEquals($movementsCountBefore, DB::table('inventory_movements')->count());
        $this->assertEquals($prodChecksumBefore, DB::table('products')->where('id', $pId)->value('updated_at'));
    }

    /**
     * TEST 8: Inventory Value & Turnover Deferred Verification (Sections 7 & 8)
     * Demonstrates that inventory.value and turnover are NOT arbitrarily fabricated with selling prices.
     */
    public function test_08_inventory_value_and_turnover_not_fabricated(): void
    {
        // En ausencia de autoridad formal de costos (COGS / unit_cost), no deben existir métricas
        // que multipliquen fraudulentamente stock * base_price pretendiendo ser costo de inventario.
        $this->assertFalse($this->registry->has('inventory.value'), "inventory.value no debe existir sin autoridad formal de costo.");
        $this->assertFalse($this->registry->has('inventory.turnover'), "inventory.turnover no debe existir sin autoridad de COGS.");
    }

    /**
     * TEST 9: Empty Catalogue Handling
     */
    public function test_09_empty_catalogue_handling(): void
    {
        // Tenant 200 sin productos ni inventario
        TenantContext::setTenantId(200);
        $filterSet = new AnalyticsFilterSet(tenantId: 200, timezone: 'America/Lima');

        $resStock = $this->engine->execute('inventory.stock', $filterSet, skipCache: true);
        $this->assertEquals('NO_DATA', is_object($resStock->status) ? $resStock->status->value : (string) $resStock->status);
        $this->assertEquals(0, $resStock->value);
    }

    /**
     * TEST 10: Authorization and Fail-Closed on Missing Tenant
     */
    public function test_10_authorization_and_fail_closed(): void
    {
        // 1. Missing tenant -> Fail-closed
        TenantContext::clear();
        $this->expectException(HttpException::class);
        $filterSet = new AnalyticsFilterSet(tenantId: 0, timezone: 'America/Lima');
        $this->engine->execute('inventory.total_stock', $filterSet);
    }
}
