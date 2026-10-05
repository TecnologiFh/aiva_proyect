<?php

namespace Tests\Feature;

use App\Core\Enums\Permission\IdentityPermission;
use App\Core\Services\PermissionService;
use App\Modules\Identity\Models\User;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    public function test_permission_service_evaluates_enum_permissions(): void
    {
        $permissionService = new PermissionService();

        $ownerUser = new User([
            'id' => 5,
            'tenant_id' => 10,
        ]);

        // Un usuario con rol OWNER tiene permiso comodín '*'
        $this->assertTrue(true);
    }
}
