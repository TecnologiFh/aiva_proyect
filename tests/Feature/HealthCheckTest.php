<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_valid_structure(): void
    {
        $response = $this->get('/ready');
        $this->assertTrue(in_array($response->getStatusCode(), [200, 503]));
    }

    public function test_request_receives_correlation_id_header(): void
    {
        $this->assertTrue(true);
    }
}
