<?php

namespace App\Modules\Identity\Application\TransferOwnership;

class TransferOwnershipInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $currentOwnerId,
        public readonly string $newOwnerUuid,
        public readonly string $previousOwnerNewRole = 'ADMIN'
    ) {}
}
