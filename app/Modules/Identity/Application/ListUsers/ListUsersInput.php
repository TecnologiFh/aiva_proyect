<?php

namespace App\Modules\Identity\Application\ListUsers;

class ListUsersInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?string $role = null,
        public readonly int $page = 1,
        public readonly int $limit = 15
    ) {}
}
