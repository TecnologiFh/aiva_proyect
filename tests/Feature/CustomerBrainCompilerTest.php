<?php

namespace Tests\Feature;

use App\Core\Enums\CustomerStage;
use App\Modules\CRM\DTOs\CustomerBrainSnapshotDTO;
use Tests\TestCase;

class CustomerBrainCompilerTest extends TestCase
{
    public function test_customer_brain_dto_structure(): void
    {
        $dto = new CustomerBrainSnapshotDTO(
            customerId: 1,
            customerUuid: 'cust-uuid-1234',
            name: 'Luis Fiestas',
            stage: CustomerStage::VIP->value,
            score: 95,
            averageTicket: 86.50,
            totalSpent: 865.00,
            totalOrdersCount: 10,
            favoriteCategory: 'Pizzas',
            preferredPayment: 'YAPE_PLIN',
            aiSummary: 'Cliente VIP recurrente. Pide sin cebolla.',
            memories: [['key' => 'favorite_product', 'value' => 'Pizza Familiar']],
            tags: ['VIP', 'Cliente Frecuente']
        );

        $array = $dto->toArray();

        $this->assertEquals('cust-uuid-1234', $array['customer_uuid']);
        $this->assertEquals('VIP', $array['stage']);
        $this->assertEquals(95, $array['score']);
        $this->assertEquals(86.50, $array['average_ticket']);
        $this->assertCount(1, $array['memories']);
        $this->assertCount(2, $array['tags']);
    }
}
