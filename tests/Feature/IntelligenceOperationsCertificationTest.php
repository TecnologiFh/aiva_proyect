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

class IntelligenceOperationsCertificationTest extends TestCase
{
    private MetricEngine $engine;
    private MetricRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sembrar Tenants de prueba (Tenant 350 y Tenant 450)
        DB::table('tenants')->updateOrInsert(
            ['id' => 350],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Alfa Operations',
                'slug' => 'tenant-alfa-ops',
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
            ['id' => 450],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Beta Operations',
                'slug' => 'tenant-beta-ops',
                'business_type' => 'Food',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Limpiar tablas operativas para aislar los tests de Tenants 350 y 450
        $tenantOrderIds = DB::table('orders')->whereIn('tenant_id', [350, 450])->pluck('id')->toArray();
        if (!empty($tenantOrderIds)) {
            DB::table('payments')->whereIn('tenant_id', [350, 450])->delete();
            DB::table('order_events')->whereIn('tenant_id', [350, 450])->delete();
            DB::table('order_items')->whereIn('tenant_id', [350, 450])->delete();
            DB::table('orders')->whereIn('tenant_id', [350, 450])->delete();
        }

        $this->engine = $this->app->make(MetricEngine::class);
        $this->registry = $this->app->make(MetricRegistry::class);
    }

    private function createOrder(
        int $tenantId,
        string $status = 'CONFIRMED',
        string $fulfillmentStatus = 'PENDING',
        string $paymentStatus = 'PAID',
        float $total = 100.00,
        string $channel = 'WHATSAPP',
        string $orderType = 'DELIVERY',
        ?string $createdAt = null,
        ?string $cancelledAt = null,
        ?string $cancelledBy = null,
        ?string $cancellationReason = null,
        ?string $cancellationChannel = null
    ): int {
        $cDate = $createdAt ?? Carbon::now('UTC')->toDateTimeString();

        return (int) DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-OPS-' . uniqid(),
            'customer_name' => 'Cliente Operativo Test',
            'customer_phone' => '51988' . rand(100000, 999999),
            'delivery_address' => 'Av. Los Pinos 123',
            'delivery_zone' => 'Lima Centro',
            'order_type' => $orderType,
            'channel' => $channel,
            'status' => $status,
            'fulfillment_status' => $fulfillmentStatus,
            'payment_status' => $paymentStatus,
            'refund_status' => 'NONE',
            'payment_method' => 'YAPE',
            'subtotal' => $total,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => $total,
            'notes' => 'Test order',
            'created_at' => $cDate,
            'updated_at' => $cDate,
            'cancelled_at' => $cancelledAt,
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $cancellationReason,
            'cancellation_channel' => $cancellationChannel,
        ]);
    }

    /**
     * P8-T01: Orders Count - Volumen total y filtrado.
     */
    public function test_p8_t01_orders_count(): void
    {
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'PREPARING');
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'READY');
        $this->createOrder(350, status: 'AWAITING_PAYMENT', fulfillmentStatus: 'PENDING');
        $this->createOrder(350, status: 'CANCELLED', fulfillmentStatus: 'CANCELLED');
        $this->createOrder(350, status: 'COMPLETED', fulfillmentStatus: 'DELIVERED');

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        // 1. Total count
        $result = $this->engine->execute('operations.orders_count', $filterSet);
        $this->assertEquals(5, $result->value);
        $this->assertEquals('orders', $result->unit);

        // 2. Count by status filter
        $resConfirmed = $this->engine->execute('operations.orders_count', $filterSet, additionalFilters: ['status' => 'CONFIRMED']);
        $this->assertEquals(2, $resConfirmed->value);

        $resCancelled = $this->engine->execute('operations.orders_count', $filterSet, additionalFilters: ['status' => 'CANCELLED']);
        $this->assertEquals(1, $resCancelled->value);

        // 3. Count by fulfillment_status filter
        $resPreparing = $this->engine->execute('operations.orders_count', $filterSet, additionalFilters: ['fulfillment_status' => 'PREPARING']);
        $this->assertEquals(1, $resPreparing->value);
    }

    /**
     * P8-T02: Orders by Status - Distribución exacta y coherencia porcentual.
     */
    public function test_p8_t02_orders_by_status(): void
    {
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CANCELLED');
        $this->createOrder(350, status: 'CANCELLED');
        $this->createOrder(350, status: 'AWAITING_PAYMENT');

        $filterSet = new AnalyticsFilterSet(tenantId: 350);
        $result = $this->engine->execute('operations.orders_by_status', $filterSet);

        $buckets = $result->value;
        $this->assertIsArray($buckets);
        $this->assertCount(3, $buckets);

        $sumCount = 0;
        $sumPct = 0.0;
        $map = [];
        foreach ($buckets as $b) {
            $sumCount += $b['count'];
            $sumPct += $b['percentage'];
            $map[$b['status']] = $b;
        }

        $this->assertEquals(6, $sumCount);
        $this->assertEquals(100.0, round($sumPct, 1));

        $this->assertEquals(3, $map['CONFIRMED']['count']);
        $this->assertEquals(50.0, $map['CONFIRMED']['percentage']);

        $this->assertEquals(2, $map['CANCELLED']['count']);
        $this->assertEquals(33.33, $map['CANCELLED']['percentage']);

        $this->assertEquals(1, $map['AWAITING_PAYMENT']['count']);
        $this->assertEquals(16.67, $map['AWAITING_PAYMENT']['percentage']);
    }

    /**
     * P8-T03: Pipeline Consistency - Coherencia del pipeline y no-duplicación.
     */
    public function test_p8_t03_pipeline_consistency(): void
    {
        $this->createOrder(350, status: 'AWAITING_PAYMENT', fulfillmentStatus: 'PENDING', total: 50.00);
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'PENDING', total: 75.00);
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'PREPARING', total: 100.00);
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'READY', total: 120.00);
        $this->createOrder(350, status: 'COMPLETED', fulfillmentStatus: 'DELIVERED', total: 200.00);
        $this->createOrder(350, status: 'CANCELLED', fulfillmentStatus: 'CANCELLED', total: 40.00);

        $filterSet = new AnalyticsFilterSet(tenantId: 350);
        $result = $this->engine->execute('operations.order_pipeline', $filterSet);
        $pipeline = $result->value;

        $this->assertEquals(6, $pipeline['total_orders']);
        $this->assertEquals(585.00, $pipeline['total_volume']);

        // Active orders: 4 (AWAITING_PAYMENT, CONFIRMED/PENDING, PREPARING, READY)
        $this->assertEquals(4, $pipeline['active_orders']['count']);
        $this->assertEquals(345.00, $pipeline['active_orders']['volume']);

        // Completed orders: 1 (COMPLETED/DELIVERED)
        $this->assertEquals(1, $pipeline['completed_orders']['count']);
        $this->assertEquals(200.00, $pipeline['completed_orders']['volume']);

        // Cancelled orders: 1 (CANCELLED)
        $this->assertEquals(1, $pipeline['cancelled_orders']['count']);
        $this->assertEquals(40.00, $pipeline['cancelled_orders']['volume']);

        // Sum of partitions equals total
        $this->assertEquals(
            $pipeline['total_orders'],
            $pipeline['active_orders']['count'] + $pipeline['completed_orders']['count'] + $pipeline['cancelled_orders']['count']
        );
    }

    /**
     * P8-T04: Tenant Isolation - Aislamiento estricto entre Tenants.
     */
    public function test_p8_t04_tenant_isolation(): void
    {
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');

        $this->createOrder(450, status: 'CONFIRMED');

        $filter350 = new AnalyticsFilterSet(tenantId: 350);
        $filter450 = new AnalyticsFilterSet(tenantId: 450);

        $res350 = $this->engine->execute('operations.orders_count', $filter350);
        $res450 = $this->engine->execute('operations.orders_count', $filter450);

        $this->assertEquals(3, $res350->value);
        $this->assertEquals(1, $res450->value);
    }

    /**
     * P8-T05: Period Filtering - Inclusión y exclusión exacta por rango temporal.
     */
    public function test_p8_t05_period_filtering(): void
    {
        // 1 orden dentro del rango (2026-08-15)
        $this->createOrder(350, createdAt: '2026-08-15 12:00:00');
        // 1 orden fuera del rango antes (2026-07-20)
        $this->createOrder(350, createdAt: '2026-07-20 12:00:00');
        // 1 orden fuera del rango después (2026-09-10)
        $this->createOrder(350, createdAt: '2026-09-10 12:00:00');

        $filterSet = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $result = $this->engine->execute('operations.orders_count', $filterSet);
        $this->assertEquals(1, $result->value);
    }

    /**
     * P8-T06: Timezone Correctness - Conversión de huso horario del tenant.
     */
    public function test_p8_t06_timezone_correctness(): void
    {
        // Tenant 350 timezone es America/Lima (UTC-5).
        // 2026-08-15 02:00:00 UTC corresponde al día local 2026-08-14 21:00:00 (-5h).
        $this->createOrder(350, createdAt: '2026-08-15 02:00:00');

        // Consultamos el día 2026-08-14 en America/Lima
        $filter14 = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-14',
            dateTo: '2026-08-14'
        );
        $res14 = $this->engine->execute('operations.orders_count', $filter14);
        $this->assertEquals(1, $res14->value, 'La orden debe caer en 2026-08-14 según el huso horario America/Lima');

        // Consultamos el día 2026-08-15 en America/Lima
        $filter15 = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-15',
            dateTo: '2026-08-15'
        );
        $res15 = $this->engine->execute('operations.orders_count', $filter15);
        $this->assertEquals(0, $res15->value, 'La orden no debe caer en 2026-08-15 en America/Lima');
    }

    /**
     * P8-T07: No Duplicate Counting caused by order_items.
     */
    public function test_p8_t07_no_duplicate_counting_caused_by_order_items(): void
    {
        $orderId = $this->createOrder(350, status: 'CONFIRMED', total: 250.00);

        // Insertar 10 ítems para esta orden
        for ($i = 1; $i <= 10; $i++) {
            DB::table('order_items')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 350,
                'order_id' => $orderId,
                'product_name' => "Producto Item {$i}",
                'unit_price' => 25.00,
                'quantity' => 1,
                'subtotal' => 25.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        // orders_count debe seguir siendo 1
        $countRes = $this->engine->execute('operations.orders_count', $filterSet);
        $this->assertEquals(1, $countRes->value);

        // pipeline total_orders debe seguir siendo 1
        $pipeRes = $this->engine->execute('operations.order_pipeline', $filterSet);
        $this->assertEquals(1, $pipeRes->value['total_orders']);
    }

    /**
     * P8-T08: No Duplicate Counting caused by payments.
     */
    public function test_p8_t08_no_duplicate_counting_caused_by_payments(): void
    {
        $orderId = $this->createOrder(350, status: 'CONFIRMED', total: 100.00);

        // Insertar 3 transacciones de pago vinculadas a esta orden
        for ($i = 1; $i <= 3; $i++) {
            DB::table('payments')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => 350,
                'order_id' => $orderId,
                'gateway' => 'MANUAL',
                'payment_method' => 'YAPE',
                'amount' => 33.33,
                'currency' => 'PEN',
                'status' => 'APPROVED',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        $countRes = $this->engine->execute('operations.orders_count', $filterSet);
        $this->assertEquals(1, $countRes->value);
    }

    /**
     * P8-T09: No Mutation Guarantee - Cero mutaciones en base de datos.
     */
    public function test_p8_t09_no_mutation(): void
    {
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CANCELLED');

        $ordersBefore = DB::table('orders')->count();
        $eventsBefore = DB::table('order_events')->count();
        $itemsBefore = DB::table('order_items')->count();
        $paymentsBefore = DB::table('payments')->count();

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        // Ejecutar todas las métricas de operaciones
        $this->engine->execute('operations.orders_count', $filterSet);
        $this->engine->execute('operations.orders_by_status', $filterSet);
        $this->engine->execute('operations.orders_by_fulfillment_status', $filterSet);
        $this->engine->execute('operations.order_pipeline', $filterSet);
        $this->engine->execute('operations.cancelled_orders', $filterSet);
        $this->engine->execute('operations.cancellation_rate', $filterSet);
        $this->engine->execute('operations.cancellation_reasons', $filterSet);

        $this->assertEquals($ordersBefore, DB::table('orders')->count(), 'La tabla orders no debe sufrir mutaciones.');
        $this->assertEquals($eventsBefore, DB::table('order_events')->count(), 'La tabla order_events no debe sufrir mutaciones.');
        $this->assertEquals($itemsBefore, DB::table('order_items')->count(), 'La tabla order_items no debe sufrir mutaciones.');
        $this->assertEquals($paymentsBefore, DB::table('payments')->count(), 'La tabla payments no debe sufrir mutaciones.');
    }

    /**
     * P8-T10: Empty Tenant - Manejo determinista de tenant sin órdenes.
     */
    public function test_p8_t10_empty_tenant(): void
    {
        $filterSet = new AnalyticsFilterSet(tenantId: 450); // Tenant 450 vacío

        $resCount = $this->engine->execute('operations.orders_count', $filterSet);
        $this->assertEquals(0, $resCount->value);

        $resStatus = $this->engine->execute('operations.orders_by_status', $filterSet);
        $this->assertEquals([], $resStatus->value);

        $resRate = $this->engine->execute('operations.cancellation_rate', $filterSet);
        $this->assertEquals(0.00, $resRate->value);

        $resPipeline = $this->engine->execute('operations.order_pipeline', $filterSet);
        $this->assertEquals(0, $resPipeline->value['total_orders']);
    }

    /**
     * P8-T11: Unknown/Unsupported Operational Metric - Fail-closed en catálogo.
     */
    public function test_p8_t11_unknown_operational_metric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->get('operations.non_existent_metric');
    }

    /**
     * P8-T12: State Authority Integrity - Intelligence lee estados sin invocar State Machine.
     */
    public function test_p8_t12_state_authority_integrity(): void
    {
        $orderId = $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'PREPARING');

        $eventsCountBefore = DB::table('order_events')->where('order_id', $orderId)->count();

        $filterSet = new AnalyticsFilterSet(tenantId: 350);
        $res = $this->engine->execute('operations.orders_by_status', $filterSet);

        $eventsCountAfter = DB::table('order_events')->where('order_id', $orderId)->count();

        $this->assertEquals($eventsCountBefore, $eventsCountAfter, 'Intelligence no debe generar eventos de transición en order_events.');
        $this->assertNotEmpty($res->value);
    }

    /**
     * P8-T13: Cancellation Semantics - Conteo y filtrado basado en cancelled_at.
     */
    public function test_p8_t13_cancellation_semantics(): void
    {
        $this->createOrder(
            350,
            status: 'CANCELLED',
            fulfillmentStatus: 'CANCELLED',
            createdAt: '2026-08-10 10:00:00',
            cancelledAt: '2026-08-10 14:00:00',
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Cliente cambió de opinión',
            cancellationChannel: 'WHATSAPP_AI'
        );

        $this->createOrder(
            350,
            status: 'CANCELLED',
            fulfillmentStatus: 'CANCELLED',
            createdAt: '2026-08-12 12:00:00',
            cancelledAt: '2026-08-12 16:30:00',
            cancelledBy: 'OPERATOR',
            cancellationReason: 'Sin stock disponible',
            cancellationChannel: 'DASHBOARD'
        );

        $this->createOrder(350, status: 'CONFIRMED', createdAt: '2026-08-15 10:00:00');

        $filterSet = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        // Total canceladas
        $resCancelled = $this->engine->execute('operations.cancelled_orders', $filterSet);
        $this->assertEquals(2, $resCancelled->value);

        // Filtrado por actor 'CUSTOMER'
        $resByCust = $this->engine->execute('operations.cancelled_orders', $filterSet, additionalFilters: ['cancelled_by' => 'CUSTOMER']);
        $this->assertEquals(1, $resByCust->value);

        // Filtrado por actor 'OPERATOR'
        $resByOp = $this->engine->execute('operations.cancelled_orders', $filterSet, additionalFilters: ['cancelled_by' => 'OPERATOR']);
        $this->assertEquals(1, $resByOp->value);
    }

    /**
     * P8-T14: Cancellation Rate - Cálculo porcentual exacto.
     */
    public function test_p8_t14_cancellation_rate(): void
    {
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(350, status: 'CANCELLED', cancelledAt: now()->toDateTimeString());

        $filterSet = new AnalyticsFilterSet(tenantId: 350);
        $resRate = $this->engine->execute('operations.cancellation_rate', $filterSet);

        // 1 de 4 órdenes = 25.0%
        $this->assertEquals(25.00, $resRate->value);
        $this->assertEquals('percentage', $resRate->unit);
    }

    /**
     * P8-T15: Cancellation Reasons & Attribution - Desglose de motivos y actores.
     */
    public function test_p8_t15_cancellation_reasons_and_attribution(): void
    {
        $this->createOrder(
            350,
            status: 'CANCELLED',
            cancelledAt: now()->toDateTimeString(),
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Dirección fuera de cobertura',
            cancellationChannel: 'WHATSAPP_AI'
        );
        $this->createOrder(
            350,
            status: 'CANCELLED',
            cancelledAt: now()->toDateTimeString(),
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Dirección fuera de cobertura',
            cancellationChannel: 'WHATSAPP_AI'
        );
        $this->createOrder(
            350,
            status: 'CANCELLED',
            cancelledAt: now()->toDateTimeString(),
            cancelledBy: 'SYSTEM',
            cancellationReason: 'Expiración de ventana de pago',
            cancellationChannel: 'SYSTEM'
        );

        $filterSet = new AnalyticsFilterSet(tenantId: 350);
        $result = $this->engine->execute('operations.cancellation_reasons', $filterSet);
        $data = $result->value;

        $this->assertEquals(3, $data['total_cancelled']);

        // by_reason
        $this->assertEquals('Dirección fuera de cobertura', $data['by_reason'][0]['label']);
        $this->assertEquals(2, $data['by_reason'][0]['count']);
        $this->assertEquals(66.67, $data['by_reason'][0]['percentage']);

        // by_actor
        $this->assertEquals('CUSTOMER', $data['by_actor'][0]['label']);
        $this->assertEquals(2, $data['by_actor'][0]['count']);

        // by_channel
        $this->assertEquals('WHATSAPP_AI', $data['by_channel'][0]['label']);
        $this->assertEquals(2, $data['by_channel'][0]['count']);
    }

    /**
     * P8-T16: Fulfillment Semantics - Ortogonalidad entre Pago y Fulfillment (PAID != DELIVERED).
     */
    public function test_p8_t16_fulfillment_semantics_and_orthogonality(): void
    {
        // Orden Pagada pero aún en preparación logística
        $this->createOrder(
            350,
            status: 'CONFIRMED',
            fulfillmentStatus: 'PREPARING',
            paymentStatus: 'PAID'
        );

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        // Desglose de fulfillment
        $fRes = $this->engine->execute('operations.orders_by_fulfillment_status', $filterSet);
        $this->assertEquals('PREPARING', $fRes->value[0]['fulfillment_status']);
        $this->assertEquals(1, $fRes->value[0]['count']);

        // Pipeline
        $pipeRes = $this->engine->execute('operations.order_pipeline', $filterSet);
        $this->assertEquals(1, $pipeRes->value['active_orders']['count']);
        $this->assertEquals(0, $pipeRes->value['completed_orders']['count'], 'Una orden pagada no debe considerarse completada hasta su entrega física.');
    }

    /**
     * P8-T17: Cross-domain regression & State Machine Isolation.
     */
    public function test_p8_t17_cross_domain_regression(): void
    {
        $this->createOrder(350, status: 'CONFIRMED', total: 150.00);

        $filterSet = new AnalyticsFilterSet(tenantId: 350);

        // Ejecutar métricas de distintos dominios
        $salesCount = $this->engine->execute('sales.orders_count', $filterSet);
        $opsCount = $this->engine->execute('operations.orders_count', $filterSet);

        $this->assertEquals($salesCount->value, $opsCount->value);
    }

    /**
     * P8-T18: Cache Isolation - Aislamiento de caché multi-inquilino.
     */
    public function test_p8_t18_cache_isolation(): void
    {
        $this->createOrder(350, status: 'CONFIRMED');
        $this->createOrder(450, status: 'CONFIRMED');
        $this->createOrder(450, status: 'CONFIRMED');

        $filter350 = new AnalyticsFilterSet(tenantId: 350);
        $filter450 = new AnalyticsFilterSet(tenantId: 450);

        // Primera llamada almacena en caché
        $res350_1 = $this->engine->execute('operations.orders_count', $filter350, skipCache: false);
        $res450_1 = $this->engine->execute('operations.orders_count', $filter450, skipCache: false);

        // Segunda llamada lee desde caché
        $res350_2 = $this->engine->execute('operations.orders_count', $filter350, skipCache: false);
        $res450_2 = $this->engine->execute('operations.orders_count', $filter450, skipCache: false);

        $this->assertEquals(1, $res350_1->value);
        $this->assertEquals(1, $res350_2->value);
        $this->assertEquals(2, $res450_1->value);
        $this->assertEquals(2, $res450_2->value);
    }

    /**
     * P8-R1: Current-state period semantics.
     * Demuestra que orders_by_status, orders_by_fulfillment_status y order_pipeline
     * evalúan el estado actual persistido de las órdenes cuyo created_at pertenece al período.
     */
    public function test_p8_r1_current_state_period_semantics(): void
    {
        // Orden creada dentro del período (2026-08-15) que al momento de la consulta está en 'CONFIRMED' / 'PREPARING'
        $this->createOrder(350, status: 'CONFIRMED', fulfillmentStatus: 'PREPARING', createdAt: '2026-08-15 12:00:00');

        $filterSet = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $byStatus = $this->engine->execute('operations.orders_by_status', $filterSet);
        $byFulfillment = $this->engine->execute('operations.orders_by_fulfillment_status', $filterSet);
        $pipeline = $this->engine->execute('operations.order_pipeline', $filterSet);

        $this->assertEquals('CONFIRMED', $byStatus->value[0]['status']);
        $this->assertEquals('PREPARING', $byFulfillment->value[0]['fulfillment_status']);
        $this->assertEquals(1, $pipeline->value['total_orders']);
        $this->assertEquals(1, $pipeline->value['active_orders']['count']);
    }

    /**
     * P8-R2: Cancellation timestamp authority.
     * Demuestra que operations.cancelled_orders cuenta cancelaciones ocurridas dentro del período
     * utilizando estricta y exclusivamente orders.cancelled_at.
     */
    public function test_p8_r2_cancellation_timestamp_semantics(): void
    {
        // Orden creada en Julio pero cancelada en Agosto
        $this->createOrder(
            350,
            status: 'CANCELLED',
            fulfillmentStatus: 'CANCELLED',
            createdAt: '2026-07-25 10:00:00',
            cancelledAt: '2026-08-10 14:00:00',
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Demora'
        );

        // Consulta del período Agosto
        $filterAugust = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $cancelledInAugust = $this->engine->execute('operations.cancelled_orders', $filterAugust);
        $this->assertEquals(1, $cancelledInAugust->value, 'Debe contarse la cancelación ocurrida en Agosto por cancelled_at');

        // Consulta del período Julio
        $filterJuly = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-07-01',
            dateTo: '2026-07-31'
        );

        $cancelledInJuly = $this->engine->execute('operations.cancelled_orders', $filterJuly);
        $this->assertEquals(0, $cancelledInJuly->value, 'No debe contarse en Julio porque no fue cancelada en Julio');
    }

    /**
     * P8-R3: Cancellation-rate denominator (Modelo A: Cohorte de creación).
     * Demuestra que cancellation_rate evalúa la cohorte de órdenes creadas en el período.
     */
    public function test_p8_r3_cancellation_rate_model_a_denominator(): void
    {
        // Cohorte Agosto: 4 órdenes creadas en Agosto (1 cancelada, 3 activas)
        $this->createOrder(350, status: 'CONFIRMED', createdAt: '2026-08-05 10:00:00');
        $this->createOrder(350, status: 'CONFIRMED', createdAt: '2026-08-10 10:00:00');
        $this->createOrder(350, status: 'CONFIRMED', createdAt: '2026-08-15 10:00:00');
        $this->createOrder(350, status: 'CANCELLED', createdAt: '2026-08-20 10:00:00', cancelledAt: '2026-09-02 12:00:00');

        $filterAugust = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $rate = $this->engine->execute('operations.cancellation_rate', $filterAugust);
        // 1 cancelada de 4 creadas en la cohorte = 25.00%
        $this->assertEquals(25.00, $rate->value);
        $this->assertEquals('percentage', $rate->unit);
    }

    /**
     * P8-R4: Cancellation reason temporal consistency.
     * Demuestra que operations.cancellation_reasons agrupa exclusivamente cancelaciones ocurridas en el período por cancelled_at.
     */
    public function test_p8_r4_cancellation_reason_temporal_consistency(): void
    {
        // Cancelada en Agosto
        $this->createOrder(
            350,
            status: 'CANCELLED',
            createdAt: '2026-07-20 10:00:00',
            cancelledAt: '2026-08-15 10:00:00',
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Motivo Agosto'
        );

        // Cancelada en Septiembre
        $this->createOrder(
            350,
            status: 'CANCELLED',
            createdAt: '2026-08-20 10:00:00',
            cancelledAt: '2026-09-05 10:00:00',
            cancelledBy: 'OPERATOR',
            cancellationReason: 'Motivo Septiembre'
        );

        $filterAugust = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-08-01',
            dateTo: '2026-08-31'
        );

        $res = $this->engine->execute('operations.cancellation_reasons', $filterAugust);
        $this->assertEquals(1, $res->value['total_cancelled']);
        $this->assertEquals('Motivo Agosto', $res->value['by_reason'][0]['label']);
    }

    /**
     * P8-R5: Metric Unit Metadata consistency.
     * Demuestra que la metadata de métricas declara unit = 'orders' y aggregation = 'BREAKDOWN' para desglose.
     */
    public function test_p8_r5_metric_unit_metadata(): void
    {
        $mOrdersCount = $this->registry->get('operations.orders_count');
        $this->assertEquals('orders', $mOrdersCount->unit);
        $this->assertEquals('COUNT', $mOrdersCount->aggregation);

        $mByStatus = $this->registry->get('operations.orders_by_status');
        $this->assertEquals('orders', $mByStatus->unit);
        $this->assertEquals('BREAKDOWN', $mByStatus->aggregation);

        $mByFulfillment = $this->registry->get('operations.orders_by_fulfillment_status');
        $this->assertEquals('orders', $mByFulfillment->unit);
        $this->assertEquals('BREAKDOWN', $mByFulfillment->aggregation);

        $mPipeline = $this->registry->get('operations.order_pipeline');
        $this->assertEquals('orders', $mPipeline->unit);
        $this->assertEquals('BREAKDOWN', $mPipeline->aggregation);

        $mCancelledOrders = $this->registry->get('operations.cancelled_orders');
        $this->assertEquals('orders', $mCancelledOrders->unit);
        $this->assertEquals('COUNT', $mCancelledOrders->aggregation);

        $mCancellationRate = $this->registry->get('operations.cancellation_rate');
        $this->assertEquals('percentage', $mCancellationRate->unit);
        $this->assertEquals('RATIO_PERCENT', $mCancellationRate->aggregation);

        $mReasons = $this->registry->get('operations.cancellation_reasons');
        $this->assertEquals('orders', $mReasons->unit);
        $this->assertEquals('BREAKDOWN', $mReasons->aggregation);
    }

    /**
     * P8 Edge-Case Test (Section 6): Explicit temporal universe distinction.
     * Order A: created_at = September 30, cancelled_at = October 1
     * Order B: created_at = October 1, not cancelled
     */
    public function test_p8_edge_case_temporal_distinction_september_october(): void
    {
        // Order A: Creada Septiembre 30 (10:00 AM Lima), Cancelada Octubre 1 (12:00 PM Lima)
        $this->createOrder(
            350,
            status: 'CANCELLED',
            fulfillmentStatus: 'CANCELLED',
            createdAt: '2026-09-30 15:00:00', // 10:00 AM America/Lima
            cancelledAt: '2026-10-01 17:00:00', // 12:00 PM America/Lima
            cancelledBy: 'CUSTOMER',
            cancellationReason: 'Cambio de planes'
        );

        // Order B: Creada Octubre 1 (10:00 AM Lima), No cancelada
        $this->createOrder(
            350,
            status: 'CONFIRMED',
            fulfillmentStatus: 'PREPARING',
            createdAt: '2026-10-01 15:00:00', // 10:00 AM America/Lima
            cancelledAt: null
        );

        // 1. Consulta del día Octubre 1
        $filterOct1 = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-10-01',
            dateTo: '2026-10-01'
        );

        $ordersCountOct1 = $this->engine->execute('operations.orders_count', $filterOct1);
        $cancelledOrdersOct1 = $this->engine->execute('operations.cancelled_orders', $filterOct1);
        $cancellationRateOct1 = $this->engine->execute('operations.cancellation_rate', $filterOct1);

        // Órdenes creadas en Octubre 1: solo Order B
        $this->assertEquals(1, $ordersCountOct1->value, 'En Octubre 1 solo se creó Order B');
        // Cancelaciones ocurridas en Octubre 1: solo Order A
        $this->assertEquals(1, $cancelledOrdersOct1->value, 'En Octubre 1 se canceló Order A');
        // Tasa de cancelación cohorte Octubre 1: 0% (Order B no fue cancelada)
        $this->assertEquals(0.00, $cancellationRateOct1->value, 'Cohorte Octubre 1 tiene 0% de cancelación');

        // 2. Consulta del día Septiembre 30
        $filterSep30 = new AnalyticsFilterSet(
            tenantId: 350,
            dateFrom: '2026-09-30',
            dateTo: '2026-09-30'
        );

        $ordersCountSep30 = $this->engine->execute('operations.orders_count', $filterSep30);
        $cancelledOrdersSep30 = $this->engine->execute('operations.cancelled_orders', $filterSep30);
        $cancellationRateSep30 = $this->engine->execute('operations.cancellation_rate', $filterSep30);

        // Órdenes creadas en Septiembre 30: solo Order A
        $this->assertEquals(1, $ordersCountSep30->value, 'En Septiembre 30 solo se creó Order A');
        // Cancelaciones ocurridas en Septiembre 30: 0 (Order A fue cancelada el 1 de Octubre)
        $this->assertEquals(0, $cancelledOrdersSep30->value, 'En Septiembre 30 ocurrieron 0 cancelaciones');
        // Tasa de cancelación cohorte Septiembre 30: 100% (Order A está actualmente CANCELLED)
        $this->assertEquals(100.00, $cancellationRateSep30->value, 'Cohorte Septiembre 30 tiene 100% de cancelación');
    }
}
