<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Analytics\Services\SalesAnalyticsService;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\OrdersOperationsAnalyticsDTO;
use App\Modules\Intelligence\Queries\GetOrdersOperationsQuery;
use App\Modules\Intelligence\Queries\GetOrdersStatusQuery;
use App\Modules\Intelligence\Queries\GetOrdersSummaryQuery;
use App\Modules\Intelligence\Queries\GetSalesAnalyticsQuery;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IntelligencePhase2CertificationTest extends TestCase
{
    private GetOrdersSummaryQuery $summaryQuery;
    private GetOrdersStatusQuery $statusQuery;
    private GetOrdersOperationsQuery $operationsQuery;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configurar Tenants de prueba en MySQL 8.0.30
        DB::table('tenants')->updateOrInsert(
            ['id' => 4],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Pastelería Donuts Fs',
                'slug' => 'donuts-fs',
                'business_type' => 'Gastronomía / Pastelería',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('tenants')->updateOrInsert(
            ['id' => 5],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Boutique Moda Elegante',
                'slug' => 'boutique-moda',
                'business_type' => 'Moda y Calzado',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('tenants')->updateOrInsert(
            ['id' => 999],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Vacío',
                'slug' => 'tenant-vacio',
                'business_type' => 'Servicios',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->summaryQuery = app(GetOrdersSummaryQuery::class);
        $this->statusQuery = app(GetOrdersStatusQuery::class);
        $this->operationsQuery = app(GetOrdersOperationsQuery::class);
    }

    /**
     * Sembrar un fixture determinista y controlado para Tenant 4.
     */
    private function seedControlledFixture(): array
    {
        // Limpiar pedidos previos para prueba determinista
        DB::table('order_items')->where('tenant_id', 4)->delete();
        DB::table('orders')->where('tenant_id', 4)->delete();

        // Orden 1: CONFIRMED, PREPARING, WHATSAPP, 2 líneas de ítems (cantidades 3 + 2 = 5 unidades)
        $ord1 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F2-001',
            'customer_name' => 'Cliente Uno',
            'customer_phone' => '51999111001',
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'PREPARING',
            'channel' => 'WHATSAPP',
            'total_amount' => 50.00,
            'created_at' => '2026-09-05 12:00:00',
            'updated_at' => '2026-09-05 12:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord1, 'product_name' => 'Donut Choco', 'quantity' => 3, 'unit_price' => 5.00, 'subtotal' => 15.00, 'created_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord1, 'product_name' => 'Café Latte', 'quantity' => 2, 'unit_price' => 7.00, 'subtotal' => 14.00, 'created_at' => now()],
        ]);

        // Orden 2: CANCELLED, CANCELLED, WEB, 1 línea (1 unidad)
        $ord2 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F2-002',
            'customer_name' => 'Cliente Dos',
            'customer_phone' => '51999111002',
            'status' => 'CANCELLED',
            'fulfillment_status' => 'CANCELLED',
            'channel' => 'WEB',
            'total_amount' => 20.00,
            'cancelled_at' => '2026-09-05 14:00:00',
            'cancelled_by' => 'CUSTOMER',
            'cancellation_reason' => 'Cliente canceló por demora',
            'created_at' => '2026-09-05 13:00:00',
            'updated_at' => '2026-09-05 14:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord2, 'product_name' => 'Pastel Vainilla', 'quantity' => 1, 'unit_price' => 20.00, 'subtotal' => 20.00, 'created_at' => now()],
        ]);

        // Orden 3: COMPLETED, DELIVERED, WHATSAPP, 1 línea (2 unidades)
        $ord3 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F2-003',
            'customer_name' => 'Cliente Tres',
            'customer_phone' => '51999111003',
            'status' => 'COMPLETED',
            'fulfillment_status' => 'DELIVERED',
            'channel' => 'WHATSAPP',
            'total_amount' => 40.00,
            'created_at' => '2026-09-06 10:00:00',
            'updated_at' => '2026-09-06 11:30:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord3, 'product_name' => 'Muffins Pack', 'quantity' => 2, 'unit_price' => 20.00, 'subtotal' => 40.00, 'created_at' => now()],
        ]);

        // Orden 4: PENDING_CONFIRMATION (Legacy status!), PENDING, canal con espacios en blanco '   ', sin ítems
        $ord4 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F2-004',
            'customer_name' => 'Cliente Cuatro',
            'customer_phone' => '51999111004',
            'status' => 'PENDING_CONFIRMATION',
            'fulfillment_status' => 'PENDING',
            'channel' => '   ',
            'total_amount' => 10.00,
            'created_at' => '2026-09-06 15:00:00',
            'updated_at' => '2026-09-06 15:00:00',
        ]);

        // Orden 5: NEW, PENDING, canal NULL, 1 línea (1 unidad)
        $ord5 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F2-005',
            'customer_name' => 'Cliente Cinco',
            'customer_phone' => '51999111005',
            'status' => 'NEW',
            'fulfillment_status' => 'PENDING',
            'channel' => null,
            'total_amount' => 15.00,
            'created_at' => '2026-09-07 18:00:00',
            'updated_at' => '2026-09-07 18:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord5, 'product_name' => 'Tarta Frutas', 'quantity' => 1, 'unit_price' => 15.00, 'subtotal' => 15.00, 'created_at' => now()],
        ]);

        // Total pedidos en fixture: 5
        // Pedidos con ítems: 4 (Ord 1, 2, 3, 5)
        // Pedidos sin ítems: 1 (Ord 4)
        // Total unidades vendidas: 5 + 1 + 2 + 0 + 1 = 9 unidades
        // Cancelados: 1 (Ord 2)
        // Completados: 1 (Ord 3)
        // Estados observados: CONFIRMED(1), CANCELLED(1), COMPLETED(1), PENDING_CONFIRMATION(1), NEW(1)
        // Fulfillment observados: PREPARING(1), CANCELLED(1), DELIVERED(1), PENDING(2)
        return [$ord1, $ord2, $ord3, $ord4, $ord5];
    }

    /**
     * INT-F2-01 — Foundation reuse
     */
    public function test_int_f2_01_foundation_reuse(): void
    {
        $this->assertInstanceOf(GetOrdersSummaryQuery::class, $this->summaryQuery);
        $this->assertInstanceOf(GetOrdersStatusQuery::class, $this->statusQuery);
        $this->assertInstanceOf(GetOrdersOperationsQuery::class, $this->operationsQuery);

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals(4, $filter->tenantId);
        $this->assertEquals('America/Lima', $filter->timezone);
        $this->assertEquals('PEN', $filter->currency);
    }

    /**
     * INT-F2-02 — Tenant isolation
     */
    public function test_int_f2_02_tenant_isolation(): void
    {
        $this->seedControlledFixture(); // 5 pedidos en Tenant 4

        // Sembrar 1 pedido en Tenant 5
        DB::table('orders')->where('tenant_id', 5)->delete();
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 5,
            'order_number' => 'ORD-T5-ISOLATION',
            'customer_name' => 'Cliente Tenant 5',
            'customer_phone' => '51999999005',
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'READY',
            'channel' => 'WHATSAPP',
            'total_amount' => 999.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filterT4 = new AnalyticsFilterSet(tenantId: 4);
        $resT4 = $this->summaryQuery->execute($filterT4);

        $filterT5 = new AnalyticsFilterSet(tenantId: 5);
        $resT5 = $this->summaryQuery->execute($filterT5);

        $this->assertEquals(5, $resT4['metrics']['orders_count'], 'Tenant 4 solo debe ver sus 5 pedidos');
        $this->assertEquals(1, $resT5['metrics']['orders_count'], 'Tenant 5 solo debe ver su pedido único');
    }

    /**
     * INT-F2-03 — Tenant mismatch denied
     */
    public function test_int_f2_03_tenant_mismatch_denied(): void
    {
        TenantContext::clear();
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Contexto de tenant requerido');

        new AnalyticsFilterSet(tenantId: null);
    }

    /**
     * INT-F2-04 — RBAC fail-closed
     */
    public function test_int_f2_04_rbac_fail_closed(): void
    {
        // Sin token Sanctum
        $respOrders = $this->getJson('/api/v1/intelligence/orders');
        $respOrders->assertStatus(401);

        $respStatus = $this->getJson('/api/v1/intelligence/orders/status');
        $respStatus->assertStatus(401);

        $respOperations = $this->getJson('/api/v1/intelligence/operations');
        $respOperations->assertStatus(401);
    }

    /**
     * INT-F2-05 — Timezone valid
     */
    public function test_int_f2_05_timezone_valid(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4, timezone: 'America/Lima');
        $result = $this->operationsQuery->execute($filter);

        $this->assertInstanceOf(OrdersOperationsAnalyticsDTO::class, $result);
        $this->assertEquals(MetricDataStatus::KNOWN, $result->status);
        $this->assertNotEmpty($result->byDay);
        $this->assertEquals('America/Lima', $result->timezone);
    }

    /**
     * INT-F2-06 — Timezone unresolved
     */
    public function test_int_f2_06_timezone_unresolved(): void
    {
        $this->seedControlledFixture();

        // Forzar timezone inválido
        $filter = new AnalyticsFilterSet(tenantId: 4, timezone: 'Invalid/Non_Existent_Timezone');
        $result = $this->operationsQuery->execute($filter);

        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $result->status);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $result->timezone);
        $this->assertEmpty($result->byDay, 'La serie temporal debe estar vacía ante timezone unresolved');
        $this->assertEmpty($result->cancellations['cancellations_by_day']);
    }

    /**
     * INT-F2-07 — Orders read-only
     */
    public function test_int_f2_07_orders_read_only(): void
    {
        $this->seedControlledFixture();

        $ordersCountBefore = DB::table('orders')->count();
        $itemsCountBefore = DB::table('order_items')->count();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $this->summaryQuery->execute($filter);
        $this->statusQuery->execute($filter);
        $this->operationsQuery->execute($filter);

        $this->assertEquals($ordersCountBefore, DB::table('orders')->count(), 'Prohibida mutación en orders');
        $this->assertEquals($itemsCountBefore, DB::table('order_items')->count(), 'Prohibida mutación en order_items');
    }

    /**
     * INT-F2-08 — Fulfillment read-only
     */
    public function test_int_f2_08_fulfillment_read_only(): void
    {
        $this->seedControlledFixture();

        $beforeStatusDistribution = DB::table('orders')->select('fulfillment_status', DB::raw('count(*) as c'))->groupBy('fulfillment_status')->pluck('c', 'fulfillment_status')->toArray();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $this->operationsQuery->execute($filter);

        $afterStatusDistribution = DB::table('orders')->select('fulfillment_status', DB::raw('count(*) as c'))->groupBy('fulfillment_status')->pluck('c', 'fulfillment_status')->toArray();

        $this->assertEquals($beforeStatusDistribution, $afterStatusDistribution, 'Prohibida alteración de fulfillment_status');
    }

    /**
     * INT-F2-09 — Orders summary
     */
    public function test_int_f2_09_orders_summary(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->summaryQuery->execute($filter);
        $metrics = $res['metrics'];

        // Verificación de los conteos del fixture:
        // 5 pedidos en total
        $this->assertEquals(5, $metrics['orders_count']);
        // 4 pedidos con ítems (el #4 no tiene líneas)
        $this->assertEquals(4, $metrics['orders_with_items']);
        // 1 pedido cancelado (#2)
        $this->assertEquals(1, $metrics['cancelled_orders']);
        // 1 pedido completado (#3)
        $this->assertEquals(1, $metrics['completed_orders']);
        // Unidades totales = 9 / 5 pedidos = 1.80 promedio
        $this->assertEquals(1.80, $metrics['average_items_per_order']);
    }

    /**
     * INT-F2-10 — Status distribution (Dynamic & Historical Preserved)
     */
    public function test_int_f2_10_status_distribution(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->statusQuery->execute($filter);
        $byStatus = $res['orders_by_status'];

        // Debe contener los estados del fixture sin filtrado por whitelist:
        $this->assertEquals(1, $byStatus['CONFIRMED']);
        $this->assertEquals(1, $byStatus['CANCELLED']);
        $this->assertEquals(1, $byStatus['COMPLETED']);
        $this->assertEquals(1, $byStatus['PENDING_CONFIRMATION'], 'El estado legacy PENDING_CONFIRMATION debe preservarse');
        $this->assertEquals(1, $byStatus['NEW']);
    }

    /**
     * INT-F2-11 — Fulfillment distribution
     */
    public function test_int_f2_11_fulfillment_distribution(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->statusQuery->execute($filter);
        $byFulfillment = $res['orders_by_fulfillment_status'];

        $this->assertEquals(1, $byFulfillment['PREPARING']);
        $this->assertEquals(1, $byFulfillment['CANCELLED'], 'El estado CANCELLED asignado por OrderCancellationService debe observarse');
        $this->assertEquals(1, $byFulfillment['DELIVERED']);
        $this->assertEquals(2, $byFulfillment['PENDING']);
    }

    /**
     * INT-F2-12 — Timeline timezone correctness
     */
    public function test_int_f2_12_timeline_timezone_correctness(): void
    {
        // Limpiar y crear una orden con created_at en UTC 02:30 del 10 de Septiembre
        // En America/Lima (UTC-5), esto corresponde a las 21:30 del 9 de Septiembre
        DB::table('order_items')->where('tenant_id', 4)->delete();
        DB::table('orders')->where('tenant_id', 4)->delete();

        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-TZ-BORDER',
            'customer_name' => 'Cliente Borde UTC',
            'customer_phone' => '51999888777',
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'PENDING',
            'channel' => 'WHATSAPP',
            'total_amount' => 50.00,
            'created_at' => '2026-09-10 02:30:00', // UTC
            'updated_at' => '2026-09-10 02:30:00',
        ]);

        $filter = new AnalyticsFilterSet(tenantId: 4, timezone: 'America/Lima');
        $result = $this->operationsQuery->execute($filter);

        $days = collect($result->byDay)->pluck('date')->toArray();
        // La fecha debe ser 2026-09-09 y NO 2026-09-10
        $this->assertContains('2026-09-09', $days, 'El timestamp UTC 02:30 debe mapearse a 2026-09-09 en America/Lima');
        $this->assertNotContains('2026-09-10', $days);
    }

    /**
     * INT-F2-13 — Channel provenance & UNKNOWN normalization
     */
    public function test_int_f2_13_channel_provenance_and_unknown_normalization(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->summaryQuery->execute($filter);
        $channels = $res['channel_breakdown'];

        // En fixture:
        // Ord 1: WHATSAPP
        // Ord 2: WEB
        // Ord 3: WHATSAPP
        // Ord 4: '   ' -> UNKNOWN
        // Ord 5: null -> UNKNOWN
        $this->assertEquals(2, $channels['WHATSAPP']);
        $this->assertEquals(1, $channels['WEB']);
        $this->assertEquals(2, $channels['UNKNOWN'], 'Canal nulo o con espacios en blanco debe normalizarse a UNKNOWN');
    }

    /**
     * INT-F2-14 — Cancellation integrity
     */
    public function test_int_f2_14_cancellation_integrity(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->summaryQuery->execute($filter);

        $this->assertEquals(1, $res['metrics']['cancelled_orders']);
        $this->assertEquals(1, $res['cancellations_by_channel']['WEB']);
    }

    /**
     * INT-F2-15 — NO_DATA
     */
    public function test_int_f2_15_no_data(): void
    {
        // Tenant 999 sin órdenes
        $filter = new AnalyticsFilterSet(tenantId: 999);
        $resSummary = $this->summaryQuery->execute($filter);
        $resOps = $this->operationsQuery->execute($filter);

        $this->assertEquals(MetricDataStatus::NO_DATA, $resSummary['status']);
        $this->assertEquals(0, $resSummary['metrics']['orders_count']);
        $this->assertEquals(MetricDataStatus::NO_DATA, $resOps->status);
        $this->assertEmpty($resOps->byDay);
    }

    /**
     * INT-F2-16 — KNOWN zero
     */
    public function test_int_f2_16_known_zero(): void
    {
        // Crear 1 pedido confirmado y activo sin cancelaciones
        DB::table('order_items')->where('tenant_id', 4)->delete();
        DB::table('orders')->where('tenant_id', 4)->delete();

        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-KNOWN-ZERO',
            'customer_name' => 'Cliente Sin Cancelaciones',
            'customer_phone' => '51999111999',
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'PENDING',
            'channel' => 'WHATSAPP',
            'total_amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $this->summaryQuery->execute($filter);

        // Existe actividad: orders_count = 1 -> KNOWN
        // Pero cancelled_orders = 0 (cero real, NO data_status NO_DATA)
        $this->assertEquals(MetricDataStatus::KNOWN, $res['status']);
        $this->assertEquals(1, $res['metrics']['orders_count']);
        $this->assertEquals(0, $res['metrics']['cancelled_orders']);
    }

    /**
     * INT-F2-17 — Provenance
     */
    public function test_int_f2_17_provenance(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->operationsQuery->execute($filter);

        $prov = $dto->provenance;
        $this->assertEquals('orders_and_operations_analytics', $prov->metricName);
        $this->assertEquals('orders + order_items', $prov->sourceOfTruth);
        $this->assertEquals('OrdersOperationsIntelligence (DIRECT_QUERY)', $prov->calculationSource);
        $this->assertEquals('America/Lima', $prov->timezone);
        $this->assertEquals('PEN', $prov->currency);
    }

    /**
     * INT-F2-18 — No N+1 / Bounded query count
     */
    public function test_int_f2_18_no_n_plus_one_bounded_query_count(): void
    {
        $this->seedControlledFixture();

        DB::enableQueryLog();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->operationsQuery->execute($filter);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertInstanceOf(OrdersOperationsAnalyticsDTO::class, $dto);
        // Garantizar que la consulta compuesta completa se resuelve en un número estrictamente acotado (<= 10 queries)
        $this->assertLessThanOrEqual(10, count($queries), 'La ejecución no debe realizar queries por fila ni N+1');
    }

    /**
     * INT-F2-19 — Legacy regression
     */
    public function test_int_f2_19_legacy_regression(): void
    {
        // 1. SalesAnalyticsService legacy
        $legacyService = app(SalesAnalyticsService::class);
        $legacyRes = $legacyService->getSalesMetrics(4);
        $this->assertInstanceOf(\App\Modules\Analytics\DTOs\SalesMetricsDTO::class, $legacyRes);

        // 2. Intelligence Sales Query (Fase 1)
        $salesQuery = app(GetSalesAnalyticsQuery::class);
        $salesFilter = new AnalyticsFilterSet(tenantId: 4);
        $salesRes = $salesQuery->execute($salesFilter);
        $this->assertInstanceOf(\App\Modules\Intelligence\DTOs\SalesAnalyticsDTO::class, $salesRes);

        // 3. Rutas HTTP Legacy activas y protegidas
        $respSales = $this->getJson('/api/v1/analytics/sales');
        $respSales->assertStatus(401);

        $respDashboard = $this->getJson('/api/v1/analytics/dashboard');
        $respDashboard->assertStatus(401);
    }

    /**
     * INT-F2-20 — MySQL 8 certification
     */
    public function test_int_f2_20_mysql_8_certification(): void
    {
        $pdo = DB::connection()->getPdo();
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $port = DB::connection()->getConfig('port');
        $database = DB::connection()->getDatabaseName();

        $this->assertStringStartsWith('8.', $version, "Debe ejecutarse sobre MySQL 8.x (Detectada: {$version})");
        $this->assertStringNotContainsStringIgnoringCase('mariadb', $version, "Prohibido fallback a MariaDB (Detectada: {$version})");
        $this->assertStringNotContainsStringIgnoringCase('sqlite', $version, "Prohibido fallback a SQLite");
        $this->assertEquals(3308, (int) $port, 'Puerto configurado debe ser 3308');
        $this->assertEquals('aiva_test', $database, 'Base de datos debe ser aiva_test en MySQL 8.0');
    }
}
