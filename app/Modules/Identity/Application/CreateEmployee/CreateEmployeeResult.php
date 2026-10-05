<?php

namespace App\Modules\Identity\Application\CreateEmployee;

use App\Modules\Identity\Models\User;

class CreateEmployeeResult
{
    public function __construct(
        public readonly User $employee
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->employee->uuid,
            'name' => $this->employee->name,
            'email' => $this->employee->email,
            'status' => $this->employee->status->value,
            'roles' => $this->employee->roles->pluck('name')->toArray(),
            'created_at' => $this->employee->created_at?->toIso8601String(),
        ];
    }
}
