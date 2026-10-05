<?php

namespace Tests\Feature;

use App\Core\Services\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\CustomersAnalyticsDTO;
use App\Modules\Intelligence\DTOs\ProductsAnalyticsDTO;
use App\Modules\Intelligence\Queries\GetCustomersAnalyticsQuery;
use App\Modules\Intelligence\Queries\GetOrdersOperationsQuery;
use App\Modules\Intelligence\Queries\GetOrdersStatusQuery;
use App\Modules\Intelligence\Queries\GetOrdersSummaryQuery;
use App\Modules\Intelligence\Queries\GetProductsAnalyticsQuery;
use App\Modules\Intelligence\Queries\GetSalesAnalyticsQuery;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntelligencePhase3_1CertificationTest extends TestCase
{
    private GetCustomersAnalyticsQuery $customersQuery;
    private GetProductsAnalyticsQuery $productsQuery;

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

        $this->customersQuery = app(GetCustomersAnalyticsQuery::class);
        $this->productsQuery = app(GetProductsAnalyticsQuery::class);
    }

    /**
     * Sembrar un fixture determinista y controlado para Customers & Products Intelligence (Tenant 4).
     */
    private function seedControlledFixture(): array
    {
        // 1. Limpiar datos previos de Tenant 4
        DB::table('order_items')->where('tenant_id', 4)->delete();
        DB::table('orders')->where('tenant_id', 4)->delete();
        DB::table('products')->where('tenant_id', 4)->delete();
        DB::table('categories')->where('tenant_id', 4)->delete();
        DB::table('customers')->where('tenant_id', 4)->delete();

        // 2. Sembrar Categorías
        $cat1 = DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'name' => 'Donuts',
            'slug' => 'donuts-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cat2 = DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'name' => 'Bebidas',
            'slug' => 'bebidas-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Sembrar Productos (Catálogo Actual)
        // Prod 1: Activo en Donuts, base_price = 10.00
        $prod1 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'category_id' => $cat1,
            'name' => 'Donut Glaseada',
            'slug' => 'donut-glaseada-' . uniqid(),
            'base_price' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        // Prod 2: Activo en Bebidas, base_price = 15.00
        $prod2 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'category_id' => $cat2,
            'name' => 'Café Latte',
            'slug' => 'cafe-latte-' . uniqid(),
            'base_price' => 15.00,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        // Prod 3: Soft-deleted en Donuts (deleted_at no nulo)
        $prod3 = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'category_id' => $cat1,
            'name' => 'Donut Rellena Manjar',
            'slug' => 'donut-rellena-' . uniqid(),
            'base_price' => 8.00,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => '2026-09-10 10:00:00',
        ]);

        // 4. Sembrar Clientes CRM (Tabla `customers` - CRM row count = 3)
        DB::table('customers')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'name' => 'Juan Perez', 'phone' => '51999111001', 'preferred_channel' => 'WHATSAPP', 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'name' => 'Maria Lopez', 'phone' => '51999111002', 'preferred_channel' => 'WHATSAPP', 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'name' => 'Carlos Diaz', 'phone' => '51999111003', 'preferred_channel' => 'WEB', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 5. Sembrar Pedidos y Líneas de Pedido
        // Orden 1: Juan Perez (+51999111001), CONFIRMED, WHATSAPP, total = 50.00
        // Items: 2x Prod 1 con precio histórico 6.00 (subtotal 12.00, base_price actual 10.00)
        //        1x Prod 3 (soft-deleted) con precio histórico 8.00 (subtotal 8.00)
        $ord1 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F3-001',
            'customer_name' => 'Juan Perez',
            'customer_phone' => '999111001',
            'customer_phone_canonical' => '+51999111001',
            'status' => 'CONFIRMED',
            'fulfillment_status' => 'DELIVERED',
            'channel' => 'WHATSAPP',
            'total_amount' => 50.00,
            'created_at' => '2026-09-05 12:00:00',
            'updated_at' => '2026-09-05 12:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord1, 'product_id' => $prod1, 'product_name' => 'Donut Glaseada', 'quantity' => 2, 'unit_price' => 6.00, 'subtotal' => 12.00, 'created_at' => '2026-09-05 12:00:00'],
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord1, 'product_id' => $prod3, 'product_name' => 'Donut Rellena Manjar', 'quantity' => 1, 'unit_price' => 8.00, 'subtotal' => 8.00, 'created_at' => '2026-09-05 12:00:00'],
        ]);

        // Orden 2: Juan Perez (+51999111001), COMPLETED, WHATSAPP, total = 30.00
        // Repite comprador! (>= 2 pedidos no cancelados)
        // Items: 1x Prod 2 (Café Latte, subtotal 14.00), 1x Línea sin product_id (NULL product_id, subtotal 16.00)
        $ord2 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F3-002',
            'customer_name' => 'Juan Perez',
            'customer_phone' => '999111001',
            'customer_phone_canonical' => '+51999111001',
            'status' => 'COMPLETED',
            'fulfillment_status' => 'DELIVERED',
            'channel' => 'WHATSAPP',
            'total_amount' => 30.00,
            'created_at' => '2026-09-06 14:00:00',
            'updated_at' => '2026-09-06 14:00:00',
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_id' => $ord2,
            'product_id' => $prod2,
            'product_name' => 'Café Latte',
            'quantity' => 1,
            'unit_price' => 14.00,
            'subtotal' => 14.00,
            'created_at' => '2026-09-06 14:00:00',
        ]);
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_id' => $ord2,
            'product_id' => null,
            'product_name' => 'Pack Especial Sorpresa',
            'quantity' => 1,
            'unit_price' => 16.00,
            'subtotal' => 16.00,
            'options_snapshot' => json_encode(['flavor' => 'chocolate', 'size' => 'large']),
            'created_at' => '2026-09-06 14:00:00',
        ]);

        // Orden 3: Maria Lopez (+51999111002), CANCELLED, WEB, total = 40.00
        // Orden cancelada: debe excluirse de buyer_identities_with_non_cancelled_orders y product_revenue
        $ord3 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F3-003',
            'customer_name' => 'Maria Lopez',
            'customer_phone' => '999111002',
            'customer_phone_canonical' => '+51999111002',
            'status' => 'CANCELLED',
            'fulfillment_status' => 'CANCELLED',
            'channel' => 'WEB',
            'total_amount' => 40.00,
            'created_at' => '2026-09-07 16:00:00',
            'updated_at' => '2026-09-07 16:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord3, 'product_id' => $prod1, 'product_name' => 'Donut Glaseada', 'quantity' => 4, 'unit_price' => 10.00, 'subtotal' => 40.00, 'created_at' => '2026-09-07 16:00:00'],
        ]);

        // Orden 4: Sin teléfono canónico (NULL), PREPARING, WEB, total = 25.00
        // Demuestra IDENTITY_ATTRIBUTION_PARTIAL
        $ord4 = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'order_number' => 'ORD-F3-004',
            'customer_name' => 'Anonimo',
            'customer_phone' => '900000000',
            'customer_phone_canonical' => null,
            'status' => 'PREPARING',
            'fulfillment_status' => 'PREPARING',
            'channel' => 'WEB',
            'total_amount' => 25.00,
            'created_at' => '2026-09-08 10:00:00',
            'updated_at' => '2026-09-08 10:00:00',
        ]);
        DB::table('order_items')->insert([
            ['uuid' => (string) Str::uuid(), 'tenant_id' => 4, 'order_id' => $ord4, 'product_id' => $prod2, 'product_name' => 'Café Latte', 'quantity' => 1, 'unit_price' => 15.00, 'subtotal' => 15.00, 'created_at' => '2026-09-08 10:00:00'],
        ]);

        return [
            'cat1' => $cat1,
            'cat2' => $cat2,
            'prod1' => $prod1,
            'prod2' => $prod2,
            'prod3' => $prod3,
            'ord1' => $ord1,
            'ord2' => $ord2,
            'ord3' => $ord3,
            'ord4' => $ord4,
        ];
    }

    private function getOrCreateOwnerUser(int $tenantId): User
    {
        $roleId = DB::table('roles')->where('name', 'OWNER')->value('id');
        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'OWNER',
                'description' => 'Owner Role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = User::where('tenant_id', $tenantId)->where('email', 'like', 'owner_%')->first();
        if (!$user) {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Owner Tenant ' . $tenantId,
                'email' => 'owner_' . $tenantId . '_' . uniqid() . '@example.com',
                'password' => bcrypt('secret123'),
                'status' => \App\Core\Enums\UserStatus::ACTIVE,
            ]);

            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * INT-F3-01 — Authenticated access
     */
    public function test_int_f3_01_authenticated_access(): void
    {
        $this->seedControlledFixture();
        $user = $this->getOrCreateOwnerUser(4);
        Sanctum::actingAs($user);

        $respCustomers = $this->withHeaders(['X-Tenant-ID' => '4'])
            ->getJson('/api/v1/intelligence/customers');
        $respCustomers->assertStatus(200);
        $respCustomers->assertJsonPath('success', true);
        $this->assertArrayHasKey('summary', $respCustomers->json('data'));

        $respProducts = $this->withHeaders(['X-Tenant-ID' => '4'])
            ->getJson('/api/v1/intelligence/products');
        $respProducts->assertStatus(200);
        $respProducts->assertJsonPath('success', true);
        $this->assertArrayHasKey('catalog_summary', $respProducts->json('data'));
    }

    /**
     * INT-F3-02 — Unauthenticated = 401
     */
    public function test_int_f3_02_unauthenticated_equals_401(): void
    {
        $respCustomers = $this->getJson('/api/v1/intelligence/customers');
        $respCustomers->assertStatus(401);

        $respProducts = $this->getJson('/api/v1/intelligence/products');
        $respProducts->assertStatus(401);
    }

    /**
     * INT-F3-03 — Permission denied = 403
     */
    public function test_int_f3_03_permission_denied_equals_403(): void
    {
        // Crear usuario sin roles ni permisos
        $userWithoutPerms = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 4,
            'name' => 'Usuario Sin Permisos',
            'email' => 'noperm_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'status' => \App\Core\Enums\UserStatus::ACTIVE,
        ]);

        Sanctum::actingAs($userWithoutPerms);

        $respCustomers = $this->withHeaders(['X-Tenant-ID' => '4'])
            ->getJson('/api/v1/intelligence/customers');
        $respCustomers->assertStatus(403);

        $respProducts = $this->withHeaders(['X-Tenant-ID' => '4'])
            ->getJson('/api/v1/intelligence/products');
        $respProducts->assertStatus(403);
    }

    /**
     * INT-F3-04 — Tenant isolation
     */
    public function test_int_f3_04_tenant_isolation(): void
    {
        $this->seedControlledFixture(); // Fixture en Tenant 4

        // Sembrar 1 cliente y 1 producto en Tenant 5
        DB::table('customers')->where('tenant_id', 5)->delete();
        DB::table('products')->where('tenant_id', 5)->delete();
        DB::table('categories')->where('tenant_id', 5)->delete();

        $catT5 = DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 5,
            'name' => 'Ropa Mujer',
            'slug' => 'ropa-mujer-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customers')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 5,
            'name' => 'Cliente Tenant 5',
            'phone' => '51999999005',
            'preferred_channel' => 'WHATSAPP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => 5,
            'category_id' => $catT5,
            'name' => 'Vestido Tenant 5',
            'slug' => 'vestido-tenant-5-' . uniqid(),
            'base_price' => 120.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filterT4 = new AnalyticsFilterSet(tenantId: 4);
        $resCustT4 = $this->customersQuery->execute($filterT4);
        $resProdT4 = $this->productsQuery->execute($filterT4);

        $filterT5 = new AnalyticsFilterSet(tenantId: 5);
        $resCustT5 = $this->customersQuery->execute($filterT5);
        $resProdT5 = $this->productsQuery->execute($filterT5);

        // Tenant 4 solo debe ver sus 3 clientes CRM y 2 productos activos no eliminados
        $this->assertEquals(3, $resCustT4->summary['registered_customers']);
        $this->assertEquals(2, $resProdT4->catalogSummary['products_count']);

        // Tenant 5 solo debe ver su 1 cliente CRM y 1 producto
        $this->assertEquals(1, $resCustT5->summary['registered_customers']);
        $this->assertEquals(1, $resProdT5->catalogSummary['products_count']);
    }

    /**
     * INT-F3-05 — registered_customers
     */
    public function test_int_f3_05_registered_customers(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // 3 registros en la tabla customers de Tenant 4
        $this->assertEquals(3, $dto->summary['registered_customers']);
        $this->assertStringContainsString('customers', $dto->provenance->sourceOfTruth);
    }

    /**
     * INT-F3-06 — buyer_identities
     */
    public function test_int_f3_06_buyer_identities(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // Órdenes con canonical phone:
        // Ord 1: +51999111001
        // Ord 2: +51999111001
        // Ord 3: +51999111002 (incluso cancelada tiene identidad de comprador)
        // Ord 4: null (excluida)
        // Distinct canonical phones = 2 (+51999111001, +51999111002)
        $this->assertEquals(2, $dto->summary['buyer_identities']);
    }

    /**
     * INT-F3-07 — buyer_identities_with_non_cancelled_orders
     */
    public function test_int_f3_07_buyer_identities_with_non_cancelled_orders(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // Ord 1: +51999111001 (CONFIRMED)
        // Ord 2: +51999111001 (COMPLETED)
        // Ord 3: +51999111002 (CANCELLED -> excluida)
        // Ord 4: null canonical (excluida)
        // Total con órdenes no canceladas = 1 (+51999111001)
        $this->assertEquals(1, $dto->summary['buyer_identities_with_non_cancelled_orders']);
    }

    /**
     * INT-F3-08 — repeat_buyer_identities_by_non_cancelled_orders
     */
    public function test_int_f3_08_repeat_buyer_identities_by_non_cancelled_orders(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // +51999111001 tiene 2 órdenes no canceladas (Ord 1 y Ord 2) -> Cumple >= 2
        // Total repeat identities = 1
        $this->assertEquals(1, $dto->summary['repeat_buyer_identities_by_non_cancelled_orders']);
    }

    /**
     * INT-F3-09 — customer_order_value
     */
    public function test_int_f3_09_customer_order_value(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // Órdenes no canceladas con canonical phone válido:
        // Ord 1: 50.00
        // Ord 2: 30.00
        // (Ord 3 cancelada = 0, Ord 4 sin canonical = 0 para identidades atribuidas)
        // Total = 80.00
        $this->assertEquals(80.00, $dto->summary['customer_order_value']);
    }

    /**
     * INT-F3-10 — customer_product_revenue
     */
    public function test_int_f3_10_customer_product_revenue(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // Subtotales de ítems en órdenes no canceladas con canonical phone:
        // Ord 1: 12.00 + 8.00 = 20.00
        // Ord 2: 14.00 + 16.00 = 30.00
        // Total = 50.00
        $this->assertEquals(50.00, $dto->summary['customer_product_revenue']);
    }

    /**
     * INT-F3-11 — products_count
     */
    public function test_int_f3_11_products_count(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Productos de catálogo en Tenant 4:
        // Prod 1: Activo
        // Prod 2: Activo
        // Prod 3: Soft-deleted (deleted_at IS NOT NULL) -> excluido
        // Total = 2
        $this->assertEquals(2, $dto->catalogSummary['products_count']);
    }

    /**
     * INT-F3-12 — products_sold
     */
    public function test_int_f3_12_products_sold(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Distinct authoritative product_id values in non-cancelled order items:
        // Ord 1: Prod 1, Prod 3
        // Ord 2: Prod 2, NULL
        // Ord 4: Prod 2
        // Distinct non-null product_ids in non-cancelled = [Prod 1, Prod 2, Prod 3] = 3
        $this->assertEquals(3, $dto->orderLinesSummary['products_sold']);
    }

    /**
     * INT-F3-13 — units_sold
     */
    public function test_int_f3_13_units_sold(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Unidades en órdenes no canceladas:
        // Ord 1: 2 + 1 = 3
        // Ord 2: 1 + 1 = 2
        // Ord 4: 1
        // (Ord 3 cancelada con 4 unidades excluida)
        // Total = 3 + 2 + 1 = 6 unidades
        $this->assertEquals(6, $dto->orderLinesSummary['units_sold']);
    }

    /**
     * INT-F3-14 — product_revenue
     */
    public function test_int_f3_14_product_revenue(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Facturación histórica por líneas de pedido no canceladas (order_items.subtotal):
        // Ord 1: 12.00 + 8.00 = 20.00
        // Ord 2: 14.00 + 16.00 = 30.00
        // Ord 4: 15.00
        // Total = 20.00 + 30.00 + 15.00 = 65.00
        $this->assertEquals(65.00, $dto->orderLinesSummary['product_revenue']);
    }

    /**
     * INT-F3-15 — Current product category
     */
    public function test_int_f3_15_current_product_category(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Desglose de catálogo actual (excluyendo soft-deleted):
        // Cat 1 (Donuts): Prod 1 (Prod 3 está soft-deleted) -> 1 producto
        // Cat 2 (Bebidas): Prod 2 -> 1 producto
        $this->assertEquals(1, $dto->byCategoryCurrent['Donuts']);
        $this->assertEquals(1, $dto->byCategoryCurrent['Bebidas']);
    }

    /**
     * INT-F3-16 — NULL canonical phone attribution
     */
    public function test_int_f3_16_null_canonical_phone_attribution(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->customersQuery->execute($filter);

        // 4 órdenes en total, 1 sin canonical phone (Ord 4)
        $this->assertEquals(CustomersAnalyticsDTO::ATTRIBUTION_PARTIAL, $dto->attribution['status']);
        $this->assertEquals(4, $dto->attribution['total_orders_in_scope']);
        $this->assertEquals(3, $dto->attribution['orders_with_valid_canonical_phone']);
        $this->assertEquals(1, $dto->attribution['orders_without_valid_canonical_phone']);
        $this->assertEquals(75.00, $dto->attribution['coverage_percentage']);
        $this->assertStringContainsString('IDENTITY_ATTRIBUTION_PARTIAL', $dto->message);
    }

    /**
     * INT-F3-17 — product_id NULL unresolved identity
     */
    public function test_int_f3_17_product_id_null_unresolved_identity(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Buscar el ítem 'Pack Especial Sorpresa' en topProducts
        $unresolvedFound = false;
        foreach ($dto->topProducts as $top) {
            if ($top['product_name'] === 'Pack Especial Sorpresa') {
                $unresolvedFound = true;
                $this->assertNull($top['product_id']);
                $this->assertEquals('PRODUCT_IDENTITY_UNRESOLVED', $top['product_identity_status']);
                $this->assertEquals(16.00, $top['product_revenue']);
            }
        }
        $this->assertTrue($unresolvedFound, 'Debe reportar PRODUCT_IDENTITY_UNRESOLVED para ítems sin product_id');
    }

    /**
     * INT-F3-18 — Deleted product observation
     */
    public function test_int_f3_18_deleted_product_observation(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // Prod 3 ('Donut Rellena Manjar') está soft-deleted en catálogo:
        // No debe contarse en products_count (excluido)
        // Pero en topProducts históricos debe observarse con is_currently_deleted = true
        $deletedFound = false;
        foreach ($dto->topProducts as $top) {
            if ($top['product_name'] === 'Donut Rellena Manjar') {
                $deletedFound = true;
                $this->assertTrue($top['is_currently_deleted'], 'Debe reportar is_currently_deleted = true');
                $this->assertEquals('RESOLVED', $top['product_identity_status']);
            }
        }
        $this->assertTrue($deletedFound, 'El producto eliminado debe aparecer en topProducts observados con flag is_currently_deleted');
    }

    /**
     * INT-F3-19 — Timezone unresolved
     */
    public function test_int_f3_19_timezone_unresolved(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4, timezone: 'Invalid_Zone_123');
        $resCust = $this->customersQuery->execute($filter);
        $resProd = $this->productsQuery->execute($filter);

        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $resCust->status);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $resCust->timezone);

        $this->assertEquals(MetricDataStatus::INSUFFICIENT_DATA, $resProd->status);
        $this->assertEquals(AnalyticsFilterSet::TIMEZONE_UNRESOLVED, $resProd->timezone);
    }

    /**
     * INT-F3-20 — Currency unresolved
     */
    public function test_int_f3_20_currency_unresolved(): void
    {
        $this->seedControlledFixture();

        // Configurar tenant con moneda NULL
        DB::table('tenants')->where('id', 4)->update(['currency' => null]);

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $resCust = $this->customersQuery->execute($filter);
        $resProd = $this->productsQuery->execute($filter);

        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $resCust->currency);
        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $resCust->provenance->currency);

        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $resProd->currency);
        $this->assertEquals(AnalyticsFilterSet::CURRENCY_UNRESOLVED, $resProd->provenance->currency);

        // Restaurar moneda de Tenant 4
        DB::table('tenants')->where('id', 4)->update(['currency' => 'PEN']);
    }

    /**
     * INT-F3-21 — Cancellation filter
     */
    public function test_int_f3_21_cancellation_filter(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $resCust = $this->customersQuery->execute($filter);
        $resProd = $this->productsQuery->execute($filter);

        // La Orden 3 tiene total_amount = 40.00 y 4 unidades de Donut Glaseada, pero está CANCELLED
        // No debe figurar en customer_order_value ni en units_sold
        $this->assertEquals(80.00, $resCust->summary['customer_order_value']);
        $this->assertEquals(6, $resProd->orderLinesSummary['units_sold']);
    }

    /**
     * INT-F3-22 — No sale inference & No customer_active inference
     *
     * Demuestra contractualmente que:
     * buyer_identities_with_non_cancelled_orders ≠ customer_active
     *
     * Intelligence:
     * 1. Observa órdenes no canceladas.
     * 2. Cuenta identidades de comprador por teléfono canónico.
     * 3. NO declara un estado ACTIVE ni lifecycle semantics.
     * 4. NO infiere venta contractual (SALE/PURCHASE/ACQUISITION).
     * 5. NO modifica la tabla customers.
     */
    public function test_int_f3_22_no_sale_inference(): void
    {
        $this->seedControlledFixture();
        $customersCountBefore = DB::table('customers')->count();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $resCust = $this->customersQuery->execute($filter);
        $resProd = $this->productsQuery->execute($filter);

        $custArray = $resCust->toArray();
        $prodArray = $resProd->toArray();

        // 1. Prohibido usar términos de venta contractual / compra / adquisición en resumen
        $custSummaryKeys = array_keys($custArray['summary']);
        $this->assertNotContains('sales', $custSummaryKeys);
        $this->assertNotContains('purchases', $custSummaryKeys);
        $this->assertNotContains('customer_acquisition', $custSummaryKeys);
        $this->assertNotContains('active_customers', $custSummaryKeys);
        $this->assertNotContains('active_buyers', $custSummaryKeys);
        $this->assertNotContains('customer_active', $custSummaryKeys);
        $this->assertNotContains('loyal_customers', $custSummaryKeys);
        $this->assertNotContains('retained_customers', $custSummaryKeys);
        $this->assertNotContains('churned_customers', $custSummaryKeys);

        // 2. buyer_identities_with_non_cancelled_orders es estrictamente observacional
        $this->assertArrayHasKey('buyer_identities_with_non_cancelled_orders', $custArray['summary']);
        $this->assertEquals(1, $custArray['summary']['buyer_identities_with_non_cancelled_orders']);

        // 3. Prohibido declarar ACTIVE en provenance o labels
        $this->assertStringNotContainsStringIgnoringCase('sale_contract', $prodArray['provenance']['calculation_source']);
        $this->assertStringNotContainsStringIgnoringCase('customer_acquisition', $custArray['provenance']['calculation_source']);
        $this->assertStringNotContainsStringIgnoringCase('active_customer', $custArray['provenance']['calculation_source']);
        $this->assertStringNotContainsStringIgnoringCase('active_buyer', $custArray['provenance']['calculation_source']);

        // 4. Invariante: Cero mutación sobre la tabla customers
        $this->assertEquals($customersCountBefore, DB::table('customers')->count());
    }

    /**
     * INT-F3-23 — No variant inference
     */
    public function test_int_f3_23_no_variant_inference(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        // La orden 2 tiene options_snapshot = '{"flavor":"chocolate","size":"large"}'
        // Verificar que no se creó ninguna entidad product_variants ni se extrajeron métricas de variantes
        $prodArray = $dto->toArray();
        $this->assertArrayNotHasKey('variants', $prodArray);
        $this->assertArrayNotHasKey('variant_sales', $prodArray['order_lines_summary']);
    }

    /**
     * INT-F3-24 — Read-only dynamic evidence
     */
    public function test_int_f3_24_read_only_dynamic_evidence(): void
    {
        $this->seedControlledFixture();

        $customersBefore = DB::table('customers')->count();
        $ordersBefore = DB::table('orders')->count();
        $itemsBefore = DB::table('order_items')->count();
        $productsBefore = DB::table('products')->count();
        $categoriesBefore = DB::table('categories')->count();

        DB::enableQueryLog();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $this->customersQuery->execute($filter);
        $this->productsQuery->execute($filter);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verificar conteos de tablas exactamente idénticos
        $this->assertEquals($customersBefore, DB::table('customers')->count());
        $this->assertEquals($ordersBefore, DB::table('orders')->count());
        $this->assertEquals($itemsBefore, DB::table('order_items')->count());
        $this->assertEquals($productsBefore, DB::table('products')->count());
        $this->assertEquals($categoriesBefore, DB::table('categories')->count());

        // Verificar que ninguna consulta ejecutada sea INSERT, UPDATE, DELETE o DDL
        foreach ($queries as $q) {
            $sql = strtoupper(trim($q['query']));
            $this->assertFalse(str_starts_with($sql, 'INSERT'), "Consulta INSERT prohibida: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'UPDATE'), "Consulta UPDATE prohibida: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'DELETE'), "Consulta DELETE prohibida: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'ALTER'), "Consulta ALTER prohibida: {$sql}");
            $this->assertFalse(str_starts_with($sql, 'DROP'), "Consulta DROP prohibida: {$sql}");
        }
    }

    /**
     * INT-F3-25 — Read-only static evidence
     */
    public function test_int_f3_25_read_only_static_evidence(): void
    {
        $filesToScan = [
            app_path('Modules/Intelligence/Queries/GetCustomersAnalyticsQuery.php'),
            app_path('Modules/Intelligence/Queries/GetProductsAnalyticsQuery.php'),
            app_path('Modules/Intelligence/DTOs/CustomersAnalyticsDTO.php'),
            app_path('Modules/Intelligence/DTOs/ProductsAnalyticsDTO.php'),
        ];

        $forbiddenPatterns = [
            '->insert(',
            '->insertGetId(',
            '->insertOrIgnore(',
            '->update(',
            '->upsert(',
            '->delete(',
            '->forceDelete(',
            '->save(',
            '->saveOrFail(',
            '->increment(',
            '->decrement(',
            'DB::statement(',
            'DB::unprepared(',
        ];

        foreach ($filesToScan as $filePath) {
            $this->assertFileExists($filePath);
            $content = file_get_contents($filePath);

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $content,
                    "Patrón de mutación prohibido '{$pattern}' encontrado en {$filePath}"
                );
            }
        }
    }

    /**
     * INT-F3-26 — Provenance integrity
     */
    public function test_int_f3_26_provenance_integrity(): void
    {
        $this->seedControlledFixture();

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $resCust = $this->customersQuery->execute($filter);
        $resProd = $this->productsQuery->execute($filter);

        $this->assertEquals('customers_analytics', $resCust->provenance->metricName);
        $this->assertStringContainsString('customers', $resCust->provenance->sourceOfTruth);
        $this->assertStringContainsString('orders.customer_phone_canonical', $resCust->provenance->sourceOfTruth);
        $this->assertEquals('GetCustomersAnalyticsQuery (DIRECT_QUERY)', $resCust->provenance->calculationSource);
        $this->assertEquals('America/Lima', $resCust->provenance->timezone);
        $this->assertEquals('PEN', $resCust->provenance->currency);

        $this->assertEquals('products_analytics', $resProd->provenance->metricName);
        $this->assertStringContainsString('products (current catalog)', $resProd->provenance->sourceOfTruth);
        $this->assertStringContainsString('order_items.subtotal (historical lines)', $resProd->provenance->sourceOfTruth);
        $this->assertEquals('GetProductsAnalyticsQuery (DIRECT_QUERY)', $resProd->provenance->calculationSource);
    }

    /**
     * INT-F3-27 — Historical snapshot integrity
     */
    public function test_int_f3_27_historical_snapshot_integrity(): void
    {
        $this->seedControlledFixture();

        // Prod 1 tiene base_price actual = 10.00
        // En Ord 1, se facturaron 2 unidades con unit_price histórico = 6.00 (subtotal histórico = 12.00)
        // Si se calculara usando el precio actual del catálogo (2 * 10.00 = 20.00), fallaría la prueba
        $filter = new AnalyticsFilterSet(tenantId: 4);
        $dto = $this->productsQuery->execute($filter);

        $prod1Data = null;
        foreach ($dto->topProducts as $top) {
            if ($top['product_name'] === 'Donut Glaseada') {
                $prod1Data = $top;
                break;
            }
        }

        $this->assertNotNull($prod1Data);
        // Subtotal histórico de Donut Glaseada en órdenes no canceladas (Ord 1) = 12.00
        $this->assertEquals(12.00, $prod1Data['product_revenue'], 'El ingreso por producto DEBE usar order_items.subtotal y jamás products.base_price');
        $this->assertNotEquals(20.00, $prod1Data['product_revenue'], 'Violación: Se reconstruyó el precio histórico desde el catálogo actual');
    }

    /**
     * INT-F3-28 — EXPLAIN / Index evidence
     */
    public function test_int_f3_28_explain_index_evidence(): void
    {
        $this->seedControlledFixture();

        // 1. EXPLAIN para consulta de clientes registrados
        $explainCustomers = DB::select("EXPLAIN SELECT COUNT(id) FROM customers WHERE tenant_id = 4");
        $this->assertNotEmpty($explainCustomers);

        // 2. EXPLAIN para consulta de identidades de comprador
        $explainOrders = DB::select("
            EXPLAIN SELECT COUNT(DISTINCT customer_phone_canonical)
            FROM orders
            WHERE tenant_id = 4 AND customer_phone_canonical IS NOT NULL AND customer_phone_canonical != ''
        ");
        $this->assertNotEmpty($explainOrders);

        // 3. EXPLAIN para consulta de facturación histórica por líneas de orden
        $explainItems = DB::select("
            EXPLAIN SELECT oi.product_id, SUM(oi.subtotal)
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.tenant_id = 4 AND oi.tenant_id = 4 AND o.status != 'CANCELLED'
            GROUP BY oi.product_id
        ");
        $this->assertNotEmpty($explainItems);
    }

    /**
     * INT-F3-29 — Legacy Phase 1 regression
     */
    public function test_int_f3_29_legacy_phase_1_regression(): void
    {
        $salesQuery = app(GetSalesAnalyticsQuery::class);
        $filter = new AnalyticsFilterSet(tenantId: 4);
        $res = $salesQuery->execute($filter);

        $this->assertInstanceOf(\App\Modules\Intelligence\DTOs\SalesAnalyticsDTO::class, $res);
        $this->assertIsArray($res->metrics);
        $this->assertIsArray($res->dimensions);
    }

    /**
     * INT-F3-30 — Legacy Phase 2 regression
     */
    public function test_int_f3_30_legacy_phase_2_regression(): void
    {
        $summaryQuery = app(GetOrdersSummaryQuery::class);
        $statusQuery = app(GetOrdersStatusQuery::class);
        $opsQuery = app(GetOrdersOperationsQuery::class);

        $filter = new AnalyticsFilterSet(tenantId: 4);
        $resSummary = $summaryQuery->execute($filter);
        $resStatus = $statusQuery->execute($filter);
        $resOps = $opsQuery->execute($filter);

        $this->assertIsArray($resSummary);
        $this->assertIsArray($resStatus);
        $this->assertInstanceOf(\App\Modules\Intelligence\DTOs\OrdersOperationsAnalyticsDTO::class, $resOps);
    }
}
