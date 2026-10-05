<?php

namespace Tests\Feature;

use App\Modules\Tenant\Database\Seeders\MultiTenantTestSeeder;
use App\Modules\AI\Services\OperationalTools\OperationalToolGateway;
use App\Modules\Business\Services\BusinessProfileService;
use App\Modules\Orders\Models\DeliveryZone;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIOperationalToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MultiTenantTestSeeder())->run();
    }

    /** @test */
    public function test_ai_operational_tools_execute_safely_and_enforce_tenant_isolation()
    {
        $tenantId = 4; // Pastelería Donuts Fs
        $gateway = new OperationalToolGateway();

        // 1. Probar GetBusinessInfoTool
        $profileService = new BusinessProfileService();
        $profileService->updateProfile($tenantId, [
            'commercial_name' => 'Pastelería Donas Pepa Enterprise',
            'phone' => '+51 999 888 777',
            'address' => 'Av. Conquistadores 450, San Isidro',
        ]);

        $infoRes = $gateway->dispatchTool($tenantId, 'get_business_info');
        $this->assertEquals('Pastelería Donas Pepa Enterprise', $infoRes['business_name']);
        $this->assertEquals('+51 999 888 777', $infoRes['contact']['phone']);

        // 2. Probar GetDeliveryInfoTool
        DeliveryZone::create([
            'tenant_id' => $tenantId,
            'zone_name' => 'Miraflores',
            'delivery_fee' => 8.50,
            'estimated_time_mins' => 35,
            'min_order_amount' => 15.00,
            'is_active' => true,
        ]);

        $deliveryRes = $gateway->dispatchTool($tenantId, 'get_delivery_info', ['zone_name' => 'Miraflores']);
        $this->assertTrue($deliveryRes['found']);
        $this->assertEquals(8.50, $deliveryRes['zones'][0]['delivery_fee']);
        $this->assertEquals(35, $deliveryRes['zones'][0]['estimated_time_mins']);

        // 3. Probar GetOrderStatusTool
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-M9-999',
            'customer_name' => 'Cliente M9 Test',
            'customer_phone' => '51988776655',
            'delivery_address' => 'Av. Primavera 100',
            'delivery_zone' => 'Miraflores',
            'order_type' => 'DELIVERY',
            'status' => 'PREPARING',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'READY',
            'subtotal' => 30.00,
            'total_amount' => 38.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderStatusRes = $gateway->dispatchTool($tenantId, 'get_order_status', ['order_number' => 'ORD-M9-999']);
        $this->assertTrue($orderStatusRes['found']);
        $this->assertEquals('PREPARING', $orderStatusRes['order']['order_status']);
        $this->assertEquals('PAID', $orderStatusRes['order']['payment_status']);
        $this->assertEquals('READY', $orderStatusRes['order']['fulfillment_status']);
    }
}
