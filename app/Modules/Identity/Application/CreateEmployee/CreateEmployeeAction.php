<?php

namespace App\Modules\Identity\Application\CreateEmployee;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Enums\UserStatus;
use App\Core\Events\EmployeeCreated;
use App\Core\Services\AuditService;
use App\Modules\Identity\Repositories\RoleRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateEmployeeAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(CreateEmployeeInput $input): CreateEmployeeResult
    {
        $role = $this->roleRepository->findByName($input->role);
        if (!$role) {
            throw ValidationException::withMessages(['role' => ["El rol '{$input->role}' no existe."]]);
        }

        // Impedir que mediante este endpoint se cree un OWNER directamente
        if ($input->role === 'OWNER') {
            throw ValidationException::withMessages(['role' => ['Para transferir la propiedad del negocio utilice TransferOwnership.']]);
        }

        $employee = $this->userRepository->create([
            'tenant_id' => $input->tenantId,
            'name' => $input->name,
            'email' => strtolower($input->email),
            'password' => Hash::make($input->password),
            'status' => UserStatus::ACTIVE->value,
            'created_by' => $input->createdById,
        ]);

        // Vincular rol
        $employee->roles()->attach($role->id, ['tenant_id' => $input->tenantId]);

        // Registrar auditoría y evento
        $this->auditService->record('Identity', 'EmployeeCreated', newValues: ['uuid' => $employee->uuid, 'role' => $input->role]);
        $this->eventBus->dispatch(new EmployeeCreated($employee, $input->createdById));

        return new CreateEmployeeResult($employee->load('roles'));
    }
}
