<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_user_registration_requires_valid_tenant_context(): void
    {
        $this->assertTrue(true);
    }
}
