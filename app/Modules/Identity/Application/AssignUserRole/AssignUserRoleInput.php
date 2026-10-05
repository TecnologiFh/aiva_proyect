<?php

namespace App\Modules\Identity\Application\AssignUserRole;

class AssignUserRoleInput
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $roleName,
        public readonly int $assignedById = 0
    ) {}
}
