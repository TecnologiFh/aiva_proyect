<?php

namespace Tests\Feature;

use App\Modules\Tenant\Database\Seeders\MultiTenantTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MultiTenantTestSeeder())->run();
    }

    /** @test */
    public function test_gateway_with_secret_b_loads_only_tenant_5_boutique_catalog()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret_boutique_tenant_5'
        ])->getJson('/api/v1/messaging/tenant-brain');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(5, $data['tenant_id']);
        $this->assertEquals('Boutique Moda Elegante', $data['business_name']);
        $this->assertStringContainsString('Vestido de Noche Glamour', $data['system_prompt']);
        $this->assertStringNotContainsString('Dona Glaseada', $data['system_prompt']);
    }

    /** @test */
    public function test_gateway_with_secret_c_loads_only_tenant_6_dental_catalog()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret_odontosmile_tenant_6'
        ])->getJson('/api/v1/messaging/tenant-brain');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(6, $data['tenant_id']);
        $this->assertEquals('Clínica Dental OdontoSmile', $data['business_name']);
        $this->assertStringContainsString('Limpieza Dental Profunda', $data['system_prompt']);
        $this->assertStringNotContainsString('Vestido de Noche', $data['system_prompt']);
    }

    /** @test */
    public function test_unauthorized_gateway_secret_returns_403_forbidden()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_hacker_secret'
        ])->getJson('/api/v1/messaging/tenant-brain');

        $response->assertStatus(403);
    }

    /** @test */
    public function test_conversations_api_returns_only_tenant_isolated_conversations()
    {
        $responseTenant4 = $this->getJson('/api/v1/dashboard/conversations?tenant_id=4');
        $responseTenant4->assertStatus(200);

        $responseTenant5 = $this->getJson('/api/v1/dashboard/conversations?tenant_id=5');
        $responseTenant5->assertStatus(200);

        $data4 = $responseTenant4->json();
        $data5 = $responseTenant5->json();

        $this->assertEquals(4, $data4['tenant_id']);
        $this->assertEquals(5, $data5['tenant_id']);
    }

    /** @test */
    public function test_inbound_message_under_tenant_5_secret_does_not_bleed_into_tenant_4()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret_boutique_tenant_5'
        ])->postJson('/api/v1/messaging/qr-inbound', [
            'phone' => '51999888777',
            'name' => 'Cliente Boutique',
            'message' => '¿Tienen vestidos de noche disponibles?'
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(5, $data['tenant_id']);

        // Verificar que Tenant 4 no contenga la conversación del Tenant 5
        $t4Conversations = $this->getJson('/api/v1/dashboard/conversations?tenant_id=4')->json();
        $t4Phones = collect($t4Conversations['conversations'])->pluck('customer_phone')->toArray();
        $this->assertNotContains('51999888777', $t4Phones);
    }
}
