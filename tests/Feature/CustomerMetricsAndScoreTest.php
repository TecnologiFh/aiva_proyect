<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\CustomerProfile;
use Tests\TestCase;

class CustomerMetricsAndScoreTest extends TestCase
{
    public function test_customer_profile_metrics_casts(): void
    {
        $profile = new CustomerProfile([
            'tenant_id' => 1,
            'customer_id' => 5,
            'total_orders_count' => 12,
            'total_spent' => 1040.00,
            'average_ticket' => 86.66,
            'customer_score' => 95,
        ]);

        $this->assertEquals(12, $profile->total_orders_count);
        $this->assertEquals(1040.00, $profile->total_spent);
        $this->assertEquals(86.66, $profile->average_ticket);
        $this->assertEquals(95, $profile->customer_score);
    }
}
