<?php

namespace Tests\Feature;

use App\Modules\Orders\Controllers\OperationsCenterController;
use App\Modules\CRM\Services\Customer360Service;
use App\Modules\AI\Services\OperationalTools\GetBusinessInfoTool;
use App\Modules\AI\Services\OperationalTools\GetOrderStatusTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndCoreCertificationTest extends TestCase
{
    public function test_operations_center_sql_alerts_calculation()
    {
        $tenantId = 4;
        $controller = new OperationsCenterController();
        $request = new Request(['tenant_id' => $tenantId]);

        $response = $controller->getOperationsData($request);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($tenantId, $data['tenant_id']);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertArrayHasKey('attention_required', $data['alerts']);
        $this->assertArrayHasKey('pending_payments', $data['alerts']);
        $this->assertArrayHasKey('ready_deliveries', $data['alerts']);
        $this->assertArrayHasKey('delivery_board', $data);
    }

    public function test_customer_360_ltv_aggregation_from_orders()
    {
        $tenantId = 4;
        $phone = '+51987654321';
        $crmService = new Customer360Service();

        // 1. Crear pedido en DB
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => 'ORD-CERT-' . time(),
            'customer_name' => 'Cliente Certificación',
            'customer_phone' => $phone,
            'channel' => 'WHATSAPP',
            'order_type' => 'DELIVERY',
            'delivery_zone' => 'Miraflores',
            'subtotal_amount' => 50.00,
            'tax_amount' => 0.00,
            'total_amount' => 50.00,
            'order_status' => 'PENDING',
            'payment_status' => 'PAID',
            'fulfillment_status' => 'READY',
            'attention_status' => 'AI_MANAGED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Recalcular Ficha Customer 360
        $customer = $crmService->getOrCreateCustomer($tenantId, $phone, 'Cliente Certificación');
        $updatedCustomer = $crmService->recalculateCustomerMetrics($tenantId, $customer->id);

        $this->assertGreaterThanOrEqual(1, $updatedCustomer->total_orders_count);
        $this->assertGreaterThanOrEqual(50.00, (float)$updatedCustomer->total_spent_amount);

        // Limpieza de prueba
        DB::table('orders')->where('id', $orderId)->delete();
    }

    public function test_ai_operational_tools_security_isolation()
    {
        $tenantId = 4;
        $businessTool = new GetBusinessInfoTool();
        $info = $businessTool->execute($tenantId);

        $this->assertEquals($tenantId, $info['tenant_id']);
        $this->assertArrayHasKey('conversational_media_resources', $info);
    }
}
