<?php

namespace App\Modules\Identity\Application\TransferOwnership;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Enums\RoleType;
use App\Core\Enums\UserStatus;
use App\Core\Events\OwnershipTransferred;
use App\Core\Services\AuditService;
use App\Core\Services\PermissionService;
use App\Modules\Identity\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferOwnershipAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService,
        private readonly PermissionService $permissionService
    ) {}

    public function execute(TransferOwnershipInput $input): TransferOwnershipResult
    {
        $previousOwner = $this->userRepository->findById($input->currentOwnerId);
        if (!$previousOwner) {
            throw new ModelNotFoundException('Propietario actual no encontrado.');
        }

        $newOwner = $this->userRepository->findByUuid($input->newOwnerUuid);
        if (!$newOwner || $newOwner->tenant_id !== $input->tenantId) {
            throw ValidationException::withMessages(['new_owner' => ['El nuevo propietario debe pertenecer a la misma empresa.']]);
        }

        if ($newOwner->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages(['new_owner' => ['El nuevo propietario debe ser un usuario activo.']]);
        }

        $ownerRole = $this->roleRepository->findByName(RoleType::OWNER->value);
        $newRoleForPrevious = $this->roleRepository->findByName($input->previousOwnerNewRole) ?: $this->roleRepository->findByName(RoleType::ADMIN->value);

        DB::transaction(function () use ($previousOwner, $newOwner, $ownerRole, $newRoleForPrevious, $input) {
            // Asignar rol OWNER al nuevo propietario
            $newOwner->roles()->sync([$ownerRole->id => ['tenant_id' => $input->tenantId]]);

            // Reasignar rol al propietario anterior
            $previousOwner->roles()->sync([$newRoleForPrevious->id => ['tenant_id' => $input->tenantId]]);

            // Historial de roles
            DB::table('user_role_history')->insert([
                [
                    'tenant_id' => $input->tenantId,
                    'user_id' => $newOwner->id,
                    'role_id' => $ownerRole->id,
                    'assigned_by' => $previousOwner->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $input->tenantId,
                    'user_id' => $previousOwner->id,
                    'role_id' => $newRoleForPrevious->id,
                    'assigned_by' => $previousOwner->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        });

        // Invalidar caché de permisos del tenant
        $this->permissionService->invalidateTenantPermissions($input->tenantId);

        // Auditoría & Eventos
        $this->auditService->record('Identity', 'OwnershipTransferred', oldValues: ['owner_id' => $previousOwner->id], newValues: ['owner_id' => $newOwner->id]);
        $this->eventBus->dispatch(new OwnershipTransferred($input->tenantId, $previousOwner, $newOwner));

        return new TransferOwnershipResult($previousOwner->load('roles'), $newOwner->load('roles'));
    }
}
