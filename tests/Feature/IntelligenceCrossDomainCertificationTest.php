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
use Tests\TestCase;

class IntelligenceCrossDomainCertificationTest extends TestCase
{
    private MetricEngine $engine;
    private MetricRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sembrar Tenants de prueba (Tenant 550 y Tenant 650)
        DB::table('tenants')->updateOrInsert(
            ['id' => 550],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Alfa CrossDomain',
                'slug' => 'tenant-alfa-cross',
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
            ['id' => 650],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Beta CrossDomain',
                'slug' => 'tenant-beta-cross',
                'business_type' => 'Food',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Limpieza de tablas para aislar los tests de Tenants 550 y 650
        $tenantOrderIds = DB::table('orders')->whereIn('tenant_id', [550, 650])->pluck('id')->toArray();
        if (!empty($tenantOrderIds)) {
            DB::table('payment_allocations')->whereIn('order_id', $tenantOrderIds)->delete();
            DB::table('payment_evidences')->whereIn('tenant_id', [550, 650])->delete();
            DB::table('payments')->whereIn('tenant_id', [550, 650])->delete();
            DB::table('refunds')->whereIn('tenant_id', [550, 650])->delete();
            DB::table('order_events')->whereIn('tenant_id', [550, 650])->delete();
            DB::table('order_items')->whereIn('tenant_id', [550, 650])->delete();
            DB::table('orders')->whereIn('tenant_id', [550, 650])->delete();
        }

        DB::table('categories')->updateOrInsert(
            ['id' => 1],
            [
                'tenant_id' => 550,
                'name' => 'General',
                'slug' => 'general-550',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('inventory_stocks')->whereIn('tenant_id', [550, 650])->delete();
        DB::table('product_variants')->whereIn('tenant_id', [550, 650])->delete();
        DB::table('products')->whereIn('tenant_id', [550, 650])->delete();
        DB::table('customers')->whereIn('tenant_id', [550, 650])->delete();

        $this->engine = $this->app->make(MetricEngine::class);
        $this->registry = $this->app->make(MetricRegistry::class);
    }

    private function createCustomer(int $tenantId, string $phone, string $name = 'Cliente Cross', string $tag = 'VIP'): int
    {
        return (int) DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $phone,
            'customer_phone_canonical' => $phone,
            'email' => 'cliente_' . uniqid() . '@example.com',
            'preferred_channel' => 'WHATSAPP',
            'total_orders_count' => 0,
            'total_spent_amount' => 0.00,
            'tag' => $tag,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(int $tenantId, string $name, float $basePrice = 50.00, int $stock = 100): int
    {
        return (int) DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'category_id' => 1,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . uniqid(),
            'product_type' => 'STANDARD',
            'sku' => 'SKU-' . uniqid(),
            'base_price' => $basePrice,
            'price_status' => 'APPROVED',
            'advance_days' => 0,
            'advance_percentage' => 0.00,
            'status' => 'ACTIVE',
            'stock_control_enabled' => 1,
            'requires_advance' => 0,
            'advance_hours' => 0,
            'requires_deposit' => 0,
            'deposit_percentage' => 0.00,
            'is_available' => 1,
            'stock_status' => 'IN_STOCK',
            'stock' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(
        int $tenantId,
        string $phone,
        float $total = 100.00,
        string $status = 'CONFIRMED',
        string $fulfillmentStatus = 'PENDING',
        string $paymentStatus = 'PENDING',
        ?string $createdAt = null,
        ?string $cancelledAt = null
    ): int {
        $date = $createdAt ?? Carbon::now('UTC')->toDateTimeString();

        return (int) DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-CR-' . uniqid(),
            'customer_name' => 'Cliente Cross',
            'customer_phone' => $phone,
            'customer_phone_canonical' => $phone,
            'delivery_address' => 'Av. Central 456',
            'order_type' => 'DELIVERY',
            'channel' => 'WHATSAPP',
            'status' => $status,
            'fulfillment_status' => $fulfillmentStatus,
            'payment_status' => $paymentStatus,
            'refund_status' => 'NONE',
            'payment_method' => 'YAPE',
            'subtotal' => $total,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => $total,
            'created_at' => $date,
            'updated_at' => $date,
            'cancelled_at' => $cancelledAt,
        ]);
    }

    private function addOrderItem(int $tenantId, int $orderId, ?int $productId, string $name, float $unitPrice, int $qty): int
    {
        return (int) DB::table('order_items')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $name,
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'subtotal' => round($unitPrice * $qty, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addPayment(int $tenantId, int $orderId, float $amount, string $status = 'APPROVED'): int
    {
        return (int) DB::table('payments')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'gateway' => 'MANUAL',
            'payment_method' => 'YAPE',
            'amount' => $amount,
            'currency' => 'PEN',
            'status' => $status,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * P9-T01: Relationship correctness - Relaciones canónicas entre dominios.
     */
    public function test_p9_t01_relationship_correctness(): void
    {
        $this->createCustomer(550, '51999111222', 'Juan Perez', 'VIP');
        $this->createCustomer(550, '51999333444', 'Maria Gomez', 'FRECUENTE');

        $prodId = $this->createProduct(550, 'Pollo a la Brasa', basePrice: 65.00);

        // Orden 1: Juan (VIP)
        $ord1 = $this->createOrder(550, '51999111222', total: 65.00);
        $this->addOrderItem(550, $ord1, $prodId, 'Pollo a la Brasa', 65.00, 1);

        // Orden 2: Maria (FRECUENTE)
        $ord2 = $this->createOrder(550, '51999333444', total: 130.00);
        $this->addOrderItem(550, $ord2, $prodId, 'Pollo a la Brasa', 65.00, 2);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $salesBySegment = $this->engine->execute('cross.sales_by_customer_segment', $filterSet);
        $ordersBySegment = $this->engine->execute('cross.orders_by_customer_segment', $filterSet);

        $this->assertCount(2, $salesBySegment->value);
        $this->assertCount(2, $ordersBySegment->value);

        $mapSales = [];
        foreach ($salesBySegment->value as $row) {
            $mapSales[$row['segment']] = $row;
        }

        $this->assertEquals(130.00, $mapSales['FRECUENTE']['revenue']);
        $this->assertEquals(2, $mapSales['FRECUENTE']['units_sold']);
        $this->assertEquals(65.00, $mapSales['VIP']['revenue']);
        $this->assertEquals(1, $mapSales['VIP']['units_sold']);
    }

    /**
     * P9-T02: Tenant Isolation - Aislamiento estricto entre Tenant 550 y Tenant 650.
     */
    public function test_p9_t02_tenant_isolation(): void
    {
        $this->createCustomer(550, '51999111222', 'Juan Alfa', 'VIP');
        $ord550 = $this->createOrder(550, '51999111222', total: 100.00);
        $this->addOrderItem(550, $ord550, null, 'Item Alfa', 100.00, 1);

        $this->createCustomer(650, '51999222333', 'Carlos Beta', 'VIP');
        $ord650 = $this->createOrder(650, '51999222333', total: 400.00);
        $this->addOrderItem(650, $ord650, null, 'Item Beta', 400.00, 1);

        $filter550 = new AnalyticsFilterSet(tenantId: 550);
        $filter650 = new AnalyticsFilterSet(tenantId: 650);

        $res550 = $this->engine->execute('cross.sales_by_customer_segment', $filter550);
        $res650 = $this->engine->execute('cross.sales_by_customer_segment', $filter650);

        $this->assertEquals(100.00, $res550->value[0]['revenue']);
        $this->assertEquals(400.00, $res650->value[0]['revenue']);
    }

    /**
     * P9-T03: Grain Preservation - Garantía de grano primario sin multiplicación.
     */
    public function test_p9_t03_grain_preservation(): void
    {
        $prodId = $this->createProduct(550, 'Hamburguesa', basePrice: 20.00, stock: 50);

        $ordId = $this->createOrder(550, '51999888777', total: 60.00);
        // 3 items distintos en la misma orden
        $this->addOrderItem(550, $ordId, $prodId, 'Hamburguesa Clásica', 20.00, 1);
        $this->addOrderItem(550, $ordId, $prodId, 'Hamburguesa Doble', 20.00, 1);
        $this->addOrderItem(550, $ordId, $prodId, 'Hamburguesa Royal', 20.00, 1);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        // Métrica con Grain ORDER: orders_by_customer_segment debe contar exactamente 1 orden
        $ordersRes = $this->engine->execute('cross.orders_by_customer_segment', $filterSet);
        $this->assertEquals(1, $ordersRes->value[0]['orders_count']);
        $this->assertEquals(60.00, $ordersRes->value[0]['total_volume']);

        // Métrica con Grain ORDER_ITEM: sales_by_customer_segment debe ver 3 unidades vendidas
        $salesRes = $this->engine->execute('cross.sales_by_customer_segment', $filterSet);
        $this->assertEquals(3, $salesRes->value[0]['units_sold']);
        $this->assertEquals(60.00, $salesRes->value[0]['revenue']);
    }

    /**
     * P9-T04: Anti-Cartesian Order/Item (Escenario Mandatorio de la Constitución).
     * 1 orden con 3 order_items no debe inflar el conteo de órdenes ni la facturación.
     */
    public function test_p9_t04_anti_cartesian_order_item(): void
    {
        $ord = $this->createOrder(550, '51999555666', total: 75.00);
        $this->addOrderItem(550, $ord, null, 'Item A', 25.00, 1);
        $this->addOrderItem(550, $ord, null, 'Item B', 25.00, 1);
        $this->addOrderItem(550, $ord, null, 'Item C', 25.00, 1);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $revStatus = $this->engine->execute('cross.revenue_by_order_status', $filterSet);
        $this->assertEquals(1, $revStatus->value['total_orders']);
        $this->assertEquals(75.00, $revStatus->value['total_volume']);
    }

    /**
     * P9-T05: Anti-Cartesian Order/Payment (Escenario Mandatorio).
     * 1 orden con 2 pagos aprobados de 50.00 cada uno:
     * total_order_value = 100.00, total_collected_cash = 100.00, orders_count = 1.
     */
    public function test_p9_t05_anti_cartesian_order_payment(): void
    {
        $ord = $this->createOrder(550, '51999555666', total: 100.00);
        $this->addPayment(550, $ord, 50.00, 'APPROVED');
        $this->addPayment(550, $ord, 50.00, 'APPROVED');

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $res = $this->engine->execute('cross.order_value_vs_collected', $filterSet);

        $this->assertEquals(100.00, $res->value['total_order_value']);
        $this->assertEquals(100.00, $res->value['total_collected_cash']);
        $this->assertEquals(1, $res->value['orders_count']);
        $this->assertEquals(1, $res->value['fully_paid_orders_count']);
        $this->assertEquals(100.00, $res->value['collection_coverage_rate']);
        $this->assertEquals(0.00, $res->value['uncollected_exposure']);
    }

    /**
     * P9-T06: Anti-Cartesian Order/Refund.
     * Reembolsos en órdenes no distorsionan el valor comercial ni duplican filas.
     */
    public function test_p9_t06_anti_cartesian_order_refund(): void
    {
        $ord = $this->createOrder(550, '51999555666', total: 120.00);
        $this->addPayment(550, $ord, 120.00, 'APPROVED');

        // Registrar 2 reembolsos parciales
        for ($i = 1; $i <= 2; $i++) {
            DB::table('refunds')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 550,
                'order_id' => $ord,
                'refund_number' => 'REF-' . $i,
                'amount' => 20.00,
                'refund_method' => 'YAPE',
                'status' => 'PROCESSED',
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $valVsColl = $this->engine->execute('cross.order_value_vs_collected', $filterSet);
        $this->assertEquals(1, $valVsColl->value['orders_count']);
        $this->assertEquals(120.00, $valVsColl->value['total_order_value']);
    }

    /**
     * P9-T07: Historical Price Integrity.
     * Orden histórica se vendió a unit_price = 10.00; el producto hoy cuesta 25.00.
     * P9 debe reportar 10.00, NUNCA 25.00.
     */
    public function test_p9_t07_historical_price_integrity(): void
    {
        // Producto actual cuesta 25.00
        $prodId = $this->createProduct(550, 'Producto Inflado', basePrice: 25.00);

        // Venta histórica registrada a 10.00
        $ord = $this->createOrder(550, '51999111222', total: 20.00);
        $this->addOrderItem(550, $ord, $prodId, 'Producto Inflado', 10.00, 2);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $prodSalesStock = $this->engine->execute('cross.product_sales_with_current_stock', $filterSet);

        $item = $prodSalesStock->value[0];
        $this->assertEquals(2, $item['units_sold']);
        $this->assertEquals(20.00, $item['sales_revenue'], 'Debe utilizar el valor histórico de order_items (2 * 10 = 20), nunca el precio actual del catálogo (2 * 25 = 50).');
    }

    /**
     * P9-T08: Payment Authority Integrity (Ratificación P7 Model A).
     * payment.amount = 100, payment_allocations = 60 + 40.
     * Cash collected debe ser 100, nunca 200 ni 140.
     */
    public function test_p9_t08_payment_authority_integrity(): void
    {
        $ord = $this->createOrder(550, '51999111222', total: 100.00);
        $payId = $this->addPayment(550, $ord, 100.00, 'APPROVED');

        $evId = DB::table('payment_evidences')->insertGetId([
            'tenant_id' => 550,
            'order_id' => $ord,
            'payment_revision' => 1,
            'uuid' => 'pe-' . uniqid(),
            'source' => 'WHATSAPP',
            'provider' => 'YAPE',
            'sha256' => hash('sha256', uniqid('sha_', true)),
            'status' => 'PAID',
            'received_at' => now(),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simular dos allocations que suman 100
        DB::table('payment_allocations')->insert([
            ['payment_evidence_id' => $evId, 'order_id' => $ord, 'allocated_amount' => 60.00, 'remaining_order_amount' => 40.00, 'allocation_type' => 'PRIMARY', 'status' => 'APPLIED', 'created_at' => now(), 'updated_at' => now()],
            ['payment_evidence_id' => $evId, 'order_id' => $ord, 'allocated_amount' => 40.00, 'remaining_order_amount' => 0.00, 'allocation_type' => 'PRIMARY', 'status' => 'APPLIED', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $res = $this->engine->execute('cross.order_value_vs_collected', $filterSet);
        $this->assertEquals(100.00, $res->value['total_collected_cash'], 'El efectivo cobrado debe ser exactamente 100.00 sin sumar las alocaciones.');
    }

    /**
     * P9-T09: Inventory Authority Integrity (Precedencia P6).
     * inventory_stocks > products.stock. No suma ambos.
     */
    public function test_p9_t09_inventory_authority_integrity(): void
    {
        // Producto con stock legacy = 50
        $prodId = $this->createProduct(550, 'Producto con Inventario P6', basePrice: 30.00, stock: 50);

        // inventory_stocks P6 tiene current_stock = 12
        DB::table('inventory_stocks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 550,
            'branch_id' => 1,
            'product_id' => $prodId,
            'current_stock' => 12.00,
            'reserved_stock' => 0.00,
            'minimum_stock' => 5.00,
            'track_inventory' => 1,
            'is_out_of_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ord = $this->createOrder(550, '51999111222', total: 60.00);
        $this->addOrderItem(550, $ord, $prodId, 'Producto con Inventario P6', 30.00, 2);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $res = $this->engine->execute('cross.product_sales_with_current_stock', $filterSet);
        $p = $res->value[0];

        $this->assertEquals(12, $p['current_stock'], 'Debe prevalecer inventory_stocks (12) sobre products.stock (50), sin sumar 62.');
    }

    /**
     * P9-T10: Customer Relation Integrity - Manejo de clientes no registrados ('UNKNOWN').
     */
    public function test_p9_t10_customer_relation_integrity(): void
    {
        // Orden con teléfono que no existe en customers
        $ord = $this->createOrder(550, '51988888888', total: 90.00);
        $this->addOrderItem(550, $ord, null, 'Item No Registrado', 90.00, 1);

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        $sales = $this->engine->execute('cross.sales_by_customer_segment', $filterSet);
        $this->assertEquals('UNKNOWN', $sales->value[0]['segment']);
        $this->assertEquals(90.00, $sales->value[0]['revenue']);
    }

    /**
     * P9-T11: Period Semantics - Inclusión y exclusión exacta por rango temporal.
     */
    public function test_p9_t11_period_semantics(): void
    {
        $this->createOrder(550, '51999111222', total: 50.00, createdAt: '2026-08-15 12:00:00');
        $this->createOrder(550, '51999111222', total: 70.00, createdAt: '2026-07-20 12:00:00'); // Fuera

        $filterSet = new AnalyticsFilterSet(
            tenantId: 550,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $res = $this->engine->execute('cross.revenue_by_order_status', $filterSet);
        $this->assertEquals(1, $res->value['total_orders']);
        $this->assertEquals(50.00, $res->value['total_volume']);
    }

    /**
     * P9-T12: Timezone Semantics - Manejo exacto del huso horario del tenant.
     */
    public function test_p9_t12_timezone_semantics(): void
    {
        // 2026-08-15 02:00:00 UTC = 2026-08-14 21:00:00 en America/Lima (UTC-5)
        $this->createOrder(550, '51999111222', total: 80.00, createdAt: '2026-08-15 02:00:00');

        $filter14 = new AnalyticsFilterSet(
            tenantId: 550,
            dateFrom: '2026-08-14',
            dateTo: '2026-08-14'
        );

        $res14 = $this->engine->execute('cross.revenue_by_order_status', $filter14);
        $this->assertEquals(1, $res14->value['total_orders']);

        $filter15 = new AnalyticsFilterSet(
            tenantId: 550,
            dateFrom: '2026-08-15',
            dateTo: '2026-08-15'
        );

        $res15 = $this->engine->execute('cross.revenue_by_order_status', $filter15);
        $this->assertEquals(0, $res15->value['total_orders']);
    }

    /**
     * P9-T13: Empty Tenant - Manejo determinista sin excepciones en tenant vacío.
     */
    public function test_p9_t13_empty_tenant(): void
    {
        $filterSet = new AnalyticsFilterSet(tenantId: 650); // Tenant 650 vacío

        $resSales = $this->engine->execute('cross.sales_by_customer_segment', $filterSet);
        $this->assertEquals([], $resSales->value);

        $resOrders = $this->engine->execute('cross.orders_by_customer_segment', $filterSet);
        $this->assertEquals([], $resOrders->value);

        $resVal = $this->engine->execute('cross.order_value_vs_collected', $filterSet);
        $this->assertEquals(0.00, $resVal->value['total_order_value']);
        $this->assertEquals(0.00, $resVal->value['total_collected_cash']);
    }

    /**
     * P9-T14: Read-Only Invariant - Cero mutaciones en base de datos.
     */
    public function test_p9_t14_read_only_invariant(): void
    {
        $this->createCustomer(550, '51999111222');
        $ord = $this->createOrder(550, '51999111222', total: 50.00);
        $this->addOrderItem(550, $ord, null, 'Item Test', 50.00, 1);
        $this->addPayment(550, $ord, 50.00, 'APPROVED');

        $ordersBefore = DB::table('orders')->count();
        $itemsBefore = DB::table('order_items')->count();
        $paymentsBefore = DB::table('payments')->count();
        $customersBefore = DB::table('customers')->count();
        $productsBefore = DB::table('products')->count();

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        // Ejecutar todas las métricas transversales
        $this->engine->execute('cross.sales_by_customer_segment', $filterSet);
        $this->engine->execute('cross.orders_by_customer_segment', $filterSet);
        $this->engine->execute('cross.revenue_by_order_status', $filterSet);
        $this->engine->execute('cross.order_value_vs_collected', $filterSet);
        $this->engine->execute('cross.payment_status_by_fulfillment_status', $filterSet);
        $this->engine->execute('cross.cancellation_rate_by_customer_segment', $filterSet);
        $this->engine->execute('cross.product_sales_with_current_stock', $filterSet);
        $this->engine->execute('cross.low_stock_sales_exposure', $filterSet);

        $this->assertEquals($ordersBefore, DB::table('orders')->count());
        $this->assertEquals($itemsBefore, DB::table('order_items')->count());
        $this->assertEquals($paymentsBefore, DB::table('payments')->count());
        $this->assertEquals($customersBefore, DB::table('customers')->count());
        $this->assertEquals($productsBefore, DB::table('products')->count());
    }

    /**
     * P9-T15: Cache Isolation - Aislamiento estricto de caché entre tenants.
     */
    public function test_p9_t15_cache_isolation(): void
    {
        $this->createCustomer(550, '51999111222', tag: 'VIP');
        $ord550 = $this->createOrder(550, '51999111222', total: 50.00);
        $this->addOrderItem(550, $ord550, null, 'Item Alfa', 50.00, 1);

        $this->createCustomer(650, '51999222333', tag: 'FRECUENTE');
        $ord650 = $this->createOrder(650, '51999222333', total: 200.00);
        $this->addOrderItem(650, $ord650, null, 'Item Beta', 200.00, 1);

        $filter550 = new AnalyticsFilterSet(tenantId: 550);
        $filter650 = new AnalyticsFilterSet(tenantId: 650);

        // Primera llamada almacena en caché
        $res550_1 = $this->engine->execute('cross.sales_by_customer_segment', $filter550, skipCache: false);
        $res650_1 = $this->engine->execute('cross.sales_by_customer_segment', $filter650, skipCache: false);

        // Segunda llamada lee desde caché
        $res550_2 = $this->engine->execute('cross.sales_by_customer_segment', $filter550, skipCache: false);
        $res650_2 = $this->engine->execute('cross.sales_by_customer_segment', $filter650, skipCache: false);

        $this->assertEquals('VIP', $res550_1->value[0]['segment']);
        $this->assertEquals('VIP', $res550_2->value[0]['segment']);
        $this->assertEquals(50.00, $res550_1->value[0]['revenue']);
        $this->assertEquals(50.00, $res550_2->value[0]['revenue']);

        $this->assertEquals('FRECUENTE', $res650_1->value[0]['segment']);
        $this->assertEquals('FRECUENTE', $res650_2->value[0]['segment']);
        $this->assertEquals(200.00, $res650_1->value[0]['revenue']);
        $this->assertEquals(200.00, $res650_2->value[0]['revenue']);
    }

    /**
     * P9-T16: Deferred Metric Fail-Closed - Métricas diferidas no se inventan ni calculan.
     */
    public function test_p9_t16_deferred_metric_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->get('cross.profit_by_customer_segment');
    }

    /**
     * P9-T17: Cross-Domain Regression - Regresión cruzada entre dominios congelados.
     */
    public function test_p9_t17_cross_domain_regression(): void
    {
        $prodId = $this->createProduct(550, 'Pizza Familiar', basePrice: 45.00, stock: 30);
        $ord = $this->createOrder(550, '51999111222', total: 45.00);
        $this->addOrderItem(550, $ord, $prodId, 'Pizza Familiar', 45.00, 1);
        $this->addPayment(550, $ord, 45.00, 'APPROVED');

        $filterSet = new AnalyticsFilterSet(tenantId: 550);

        // Verificar que los calculadores individuales de P2, P5, P6, P7 y P8 coinciden con P9
        $salesRev = $this->engine->execute('sales.revenue', $filterSet);
        $opsCount = $this->engine->execute('operations.orders_count', $filterSet);
        $finColl = $this->engine->execute('financial.collected_revenue', $filterSet);
        $crossVal = $this->engine->execute('cross.order_value_vs_collected', $filterSet);

        $this->assertEquals($salesRev->value, $crossVal->value['total_order_value']);
        $this->assertEquals($opsCount->value, $crossVal->value['orders_count']);
        $this->assertEquals($finColl->value, $crossVal->value['total_collected_cash']);
    }
}
