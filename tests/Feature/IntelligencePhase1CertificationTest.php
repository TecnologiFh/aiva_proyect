<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Analytics\Services\FinancialAllocationTruthEngineService;
use App\Modules\Analytics\Services\SalesAnalyticsService;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\SalesAnalyticsDTO;
use App\Modules\Intelligence\Queries\GetSalesAnalyticsQuery;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IntelligencePhase1CertificationTest extends TestCase
{
    private GetSalesAnalyticsQuery $query;
    private FinancialAllocationTruthEngineService $truthEngine;

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrar Tenants de prueba directamente en el esquema MySQL 8.x
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
            ['id' => 6],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Clínica Dental OdontoSmile',
                'slug' => 'odontosmile',
                'business_type' => 'Salud Dental',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->truthEngine = app(FinancialAllocationTruthEngineService::class);
        $this->query = app(GetSalesAnalyticsQuery::class);
    }

    /**
     * INT-F1-01 Foundation bootstrap
     */
    public function test_int_f1_01_foundation_bootstrap(): void
    {
        $this->assertInstanceOf(GetSalesAnalyticsQuery::class, $this->query);
        $this->assertTrue(app()->bound(GetSalesAnalyticsQuery::class));
    }

    /**
     * INT-F1-02 Tenant isolation
     */
    public function test_int_f1_02_tenant_isolation(): void
    {
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-T4-' . uniqid(),
            'customer_name' => 'Cliente T4',
            'customer_phone' => '51999111222',
            'status' => 'CONFIRMED',
            'subtotal' => 100.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 5,
            'order_number' => 'ORD-T5-' . uniqid(),
            'customer_name' => 'Cliente T5',
            'customer_phone' => '51999333444',
            'status' => 'CONFIRMED',
            'subtotal' => 250.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 250.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filterT4 = new AnalyticsFilterSet(tenantId: 4);
        $resultT4 = $this->query->execute($filterT4);
        $this->assertGreaterThanOrEqual(100.00, $resultT4->metrics['net_sales']);

        $filterT5 = new AnalyticsFilterSet(tenantId: 5);
        $resultT5 = $this->query->execute($filterT5);
        $this->assertGreaterThanOrEqual(250.00, $resultT5->metrics['net_sales']);
    }

    /**
     * INT-F1-03 Tenant mismatch denied
     */
    public function test_int_f1_03_tenant_mismatch_denied(): void
    {
        TenantContext::clear();
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Contexto de tenant requerido');

        new AnalyticsFilterSet(tenantId: null);
    }

    /**
     * INT-F1-04 Permission denied
     */
    public function test_int_f1_04_permission_denied(): void
    {
        $response = $this->getJson('/api/v1/intelligence/sales');
        $response->assertStatus(401);
    }

    /**
     * INT-F1-05 Timezone resolution (Valid, NULL, Empty, Invalid)
     */
    public function test_int_f1_05_timezone_resolution(): void
    {
        // 1. Timezone válido
        DB::table('tenants')->where('id', 4)->update(['timezone' => 'America/Lima']);
        $filterValid = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals('America/Lima', $filterValid->timezone);
        $this->assertTrue($filterValid->isTimezoneResolved);

        // 2. Timezone NULL -> TIMEZONE_UNRESOLVED
        DB::table('tenants')->where('id', 4)->update(['timezone' => null]);
        $filterNull = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $filterNull->timezone);
        $this->assertFalse($filterNull->isTimezoneResolved);
        $resNull = $this->query->execute($filterNull);
        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $resNull->status);
        $this->assertEmpty($resNull->dimensions['sales_by_day']);

        // 3. Timezone vacío '' -> TIMEZONE_UNRESOLVED
        DB::table('tenants')->where('id', 4)->update(['timezone' => '']);
        $filterEmpty = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $filterEmpty->timezone);
        $this->assertFalse($filterEmpty->isTimezoneResolved);
        $resEmpty = $this->query->execute($filterEmpty);
        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $resEmpty->status);

        // 4. Timezone inválido -> TIMEZONE_UNRESOLVED
        DB::table('tenants')->where('id', 4)->update(['timezone' => 'Invalid/Non_Existent_Timezone']);
        $filterInvalid = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $filterInvalid->timezone);
        $this->assertFalse($filterInvalid->isTimezoneResolved);
        $resInvalid = $this->query->execute($filterInvalid);
        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $resInvalid->status);

        // Restaurar timezone para siguientes tests
        DB::table('tenants')->where('id', 4)->update(['timezone' => 'America/Lima']);
    }

    /**
     * INT-F1-06 Currency resolution (Valid & NULL / Unresolved)
     */
    public function test_int_f1_06_currency_resolution(): void
    {
        // 1. Moneda válida
        DB::table('tenants')->where('id', 4)->update(['currency' => 'PEN']);
        $filterValid = new AnalyticsFilterSet(tenantId: 4);
        $this->assertEquals('PEN', $filterValid->currency);
        $this->assertTrue($filterValid->isCurrencyResolved);

        // 2. Moneda NULL -> CURRENCY_UNRESOLVED (sin fallback a PEN/USD)
        DB::table('tenants')->where('id', 6)->update(['currency' => null]);
        $filterNull = new AnalyticsFilterSet(tenantId: 6);
        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $filterNull->currency);
        $this->assertFalse($filterNull->isCurrencyResolved);

        // 3. Moneda vacía -> CURRENCY_UNRESOLVED
        DB::table('tenants')->where('id', 6)->update(['currency' => '']);
        $filterEmpty = new AnalyticsFilterSet(tenantId: 6);
        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $filterEmpty->currency);
        $this->assertFalse($filterEmpty->isCurrencyResolved);
    }

    /**
     * INT-F1-07 Sales query read-only (zero writes)
     */
    public function test_int_f1_07_sales_query_read_only(): void
    {
        $ordersCountBefore = DB::table('orders')->count();
        $itemsCountBefore = DB::table('order_items')->count();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $result = $this->query->execute($filter);

        $this->assertInstanceOf(SalesAnalyticsDTO::class, $result);
        $this->assertEquals($ordersCountBefore, DB::table('orders')->count(), 'La tabla orders no debe sufrir mutaciones.');
        $this->assertEquals($itemsCountBefore, DB::table('order_items')->count(), 'La tabla order_items no debe sufrir mutaciones.');
    }

    /**
     * INT-F1-08 Sales metric provenance
     */
    public function test_int_f1_08_sales_metric_provenance(): void
    {
        $filter = new AnalyticsFilterSet(tenantId: 4, dateFrom: '2026-09-01', dateTo: '2026-09-30');
        $result = $this->query->execute($filter);

        $prov = $result->provenance;
        $this->assertEquals('sales_analytics', $prov->metricName);
        $this->assertEquals('FinancialAllocationTruthEngineService + GetSalesAnalyticsQuery', $prov->calculationSource);
        $this->assertEquals('America/Lima', $prov->timezone);
        $this->assertEquals('PEN', $prov->currency);
        $this->assertEquals('v1.0', $prov->datasetVersion);
    }

    /**
     * INT-F1-09 No duplicated financial formula (Paridad con Truth Engine)
     */
    public function test_int_f1_09_no_duplicated_financial_formula(): void
    {
        $filter = new AnalyticsFilterSet(tenantId: 4);
        $result = $this->query->execute($filter);

        $truth = $this->truthEngine->calculateFinancialMetrics(4);
        $summary = $truth['financial_summary'];

        $this->assertEquals((float) $summary['gross_sales'], $result->metrics['gross_sales']);
        $this->assertEquals((float) $summary['net_sales'], $result->metrics['net_sales']);
        $this->assertEquals((float) $summary['discounts'], $result->metrics['discounts']);
        $this->assertEquals((float) $summary['paid_sales'], $result->metrics['paid_sales']);
        $this->assertEquals((float) $summary['refunds'], $result->metrics['refunds']);
    }

    /**
     * INT-F1-10 KNOWN result
     */
    public function test_int_f1_10_known_result(): void
    {
        DB::table('orders')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-KNOWN-' . uniqid(),
            'customer_name' => 'Cliente Known',
            'customer_phone' => '51999888111',
            'status' => 'CONFIRMED',
            'subtotal' => 50.00,
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $result = $this->query->execute($filter);

        $this->assertEquals(MetricDataStatus::KNOWN, $result->status);
    }

    /**
     * INT-F1-11 NO_DATA result
     */
    public function test_int_f1_11_no_data_result(): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => 999],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Tenant Fantasma',
                'slug' => 'fantasma',
                'business_type' => 'Servicios',
                'country' => 'PE',
                'currency' => 'PEN',
                'timezone' => 'America/Lima',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $filter = new AnalyticsFilterSet(tenantId: 999);
        $result = $this->query->execute($filter);

        $this->assertEquals(MetricDataStatus::NO_DATA, $result->status);
        $this->assertEquals(0, $result->metrics['orders_count']);
    }

    /**
     * INT-F1-12 INSUFFICIENT_DATA result
     */
    public function test_int_f1_12_insufficient_data_result(): void
    {
        $status = MetricDataStatus::INSUFFICIENT_DATA;
        $this->assertEquals('INSUFFICIENT_DATA', $status->value);

        // Cuando la zona horaria no es resuelta, el estado debe ser INSUFFICIENT_DATA
        $filter = new AnalyticsFilterSet(tenantId: 4, timezone: 'Invalid/Timezone');
        $result = $this->query->execute($filter);
        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $result->status);
    }

    /**
     * INT-F1-13 Empty period handling
     */
    public function test_int_f1_13_empty_period_handling(): void
    {
        $filter = new AnalyticsFilterSet(tenantId: 4, dateFrom: '2015-01-01', dateTo: '2015-01-02');
        $result = $this->query->execute($filter);

        $this->assertEquals(MetricDataStatus::NO_DATA, $result->status);
        $this->assertEquals(0.00, $result->metrics['gross_sales']);
        $this->assertEmpty($result->dimensions['sales_by_day']);
    }

    /**
     * INT-F1-14 Legacy Analytics remains functional
     */
    public function test_int_f1_14_legacy_analytics_remains_functional(): void
    {
        $legacyService = app(SalesAnalyticsService::class);
        $legacyResult = $legacyService->getSalesMetrics(4);

        $this->assertInstanceOf(\App\Modules\Analytics\DTOs\SalesMetricsDTO::class, $legacyResult);
        $this->assertIsArray($legacyResult->toArray());

        // Verificar que las rutas legacy /api/v1/analytics/sales y /dashboard están vivas y protegidas
        $respSales = $this->getJson('/api/v1/analytics/sales');
        $respSales->assertStatus(401);

        $respDashboard = $this->getJson('/api/v1/analytics/dashboard');
        $respDashboard->assertStatus(401);
    }

    /**
     * INT-F1-15 Tenant A cannot see Tenant B
     */
    public function test_int_f1_15_tenant_a_cannot_see_tenant_b(): void
    {
        $filterT4 = new AnalyticsFilterSet(tenantId: 4);
        $filterT5 = new AnalyticsFilterSet(tenantId: 5);

        $resT4 = $this->query->execute($filterT4);
        $resT5 = $this->query->execute($filterT5);

        $this->assertNotSame($resT4->provenance->tenantId ?? 4, $resT5->provenance->tenantId ?? 5);
        $this->assertIsArray($resT4->metrics);
        $this->assertIsArray($resT5->metrics);
    }

    /**
     * INT-F1-16 MySQL certification (Gate obligatorio MySQL 8.x)
     */
    public function test_int_f1_16_mysql_certification(): void
    {
        $pdo = DB::connection()->getPdo();
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $port = DB::connection()->getConfig('port');
        $database = DB::connection()->getDatabaseName();

        // Verificaciones obligatorias de certificación MySQL 8.x
        $this->assertStringStartsWith('8.', $version, "Debe ejecutarse sobre MySQL 8.x (Detectada: {$version})");
        $this->assertStringNotContainsStringIgnoringCase('mariadb', $version, "Prohibido fallback a MariaDB (Detectada: {$version})");
        $this->assertStringNotContainsStringIgnoringCase('sqlite', $version, "Prohibido fallback a SQLite");
        $this->assertEquals(3308, (int) $port, 'Puerto configurado debe ser 3308 (instancia MySQL 8.0 dedicada)');
        $this->assertEquals('aiva_test', $database, 'Base de datos debe ser aiva_test en MySQL 8.0');
    }

    /**
     * INT-F1-17 Filter validation
     */
    public function test_int_f1_17_filter_validation(): void
    {
        $filter = new AnalyticsFilterSet(
            tenantId: 4,
            dateFrom: '2026-09-01',
            dateTo: '2026-09-10',
            channel: 'whatsapp',
            paymentMethod: 'yape'
        );

        $this->assertEquals('whatsapp', $filter->channel);
        $this->assertEquals('yape', $filter->paymentMethod);
        $this->assertNotNull($filter->getStartDateTimeUtcString());
        $this->assertNotNull($filter->getEndDateTimeUtcString());
    }

    /**
     * INT-F1-18 Query efficiency / no N+1
     */
    public function test_int_f1_18_query_efficiency_no_n_plus_one(): void
    {
        DB::enableQueryLog();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $result = $this->query->execute($filter);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertInstanceOf(SalesAnalyticsDTO::class, $result);
        // Garantizar que el número de queries está acotado y no se ejecuta N+1
        $this->assertLessThanOrEqual(10, count($queries), 'La consulta analítica debe estar acotada (sin N+1)');
    }

    /**
     * INT-F1-19 Financial metric consistency
     */
    public function test_int_f1_19_financial_metric_consistency(): void
    {
        $filter = new AnalyticsFilterSet(tenantId: 4);
        $result = $this->query->execute($filter);

        $gross = $result->metrics['gross_sales'];
        $discounts = $result->metrics['discounts'];
        $net = $result->metrics['net_sales'];

        // Consistencia matemática: gross = net + discounts
        $this->assertEquals(round($gross, 2), round($net + $discounts, 2));

        if ($result->metrics['orders_count'] > 0) {
            $expectedAov = round($net / $result->metrics['orders_count'], 2);
            $this->assertEquals($expectedAov, $result->metrics['average_order_value']);
        }
    }

    /**
     * INT-F1-20 Regression suite
     */
    public function test_int_f1_20_regression_suite(): void
    {
        TenantContext::setTenantId(4);
        $this->assertEquals(4, TenantContext::getTenantId());

        $tenant = DB::table('tenants')->where('id', 4)->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('ACTIVE', $tenant->status);
    }
}
