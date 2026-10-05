<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class EmployeeCreated
{
    public function __construct(
        public readonly User $employee,
        public readonly int $assignedBy
    ) {}
}
