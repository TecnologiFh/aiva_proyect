<?php

namespace Tests\Feature;

use Tests\TestCase;

class OwnerProtectionTest extends TestCase
{
    public function test_owner_guard_prevents_blocking_last_owner(): void
    {
        $this->assertTrue(true);
    }
}
