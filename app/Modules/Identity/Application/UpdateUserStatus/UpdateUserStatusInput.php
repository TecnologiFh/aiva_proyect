<?php

namespace App\Modules\Identity\Application\UpdateUserStatus;

class UpdateUserStatusInput
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $newStatus,
        public readonly int $updatedById = 0
    ) {}
}
