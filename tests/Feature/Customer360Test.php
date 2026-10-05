<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Services\Customer360Service;
use App\Modules\AI\Services\OperationalTools\GetCustomerHistoryTool;
use Tests\TestCase;

class Customer360Test extends TestCase
{
    public function test_customer_360_creation_and_metrics_calculation()
    {
        $tenantId = 4;
        $service = new Customer360Service();

        // 1. Crear o recuperar cliente
        $customer = $service->getOrCreateCustomer($tenantId, '+51999888777', 'María Mercedes');
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('+51999888777', $customer->phone);

        // 2. Obtener Perfil 360
        $profile = $service->getCustomer360Profile($tenantId, $customer->id);
        $this->assertArrayHasKey('customer', $profile);
        $this->assertArrayHasKey('ltv_metrics', $profile);
        $this->assertEquals($customer->id, $profile['customer']->id);

        // 3. Herramienta AI Gateway GetCustomerHistoryTool
        $aiTool = new GetCustomerHistoryTool();
        $history = $aiTool->execute($tenantId, '+51999888777');
        $this->assertEquals('María Mercedes', $history['customer_name']);
        $this->assertArrayHasKey('greeting_suggestion', $history);
    }
}
