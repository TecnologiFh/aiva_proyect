<?php

namespace App\Modules\Identity\Application\AssignUserRole;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Events\UserRoleChanged;
use App\Core\Services\AuditService;
use App\Core\Services\PermissionService;
use App\Modules\Identity\Repositories\RoleRepository;
use App\Modules\Identity\Services\OwnerGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignUserRoleAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly OwnerGuard $ownerGuard,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService,
        private readonly PermissionService $permissionService
    ) {}

    public function execute(AssignUserRoleInput $input): AssignUserRoleResult
    {
        $user = $this->userRepository->findByUuid($input->userUuid);
        if (!$user) {
            throw new ModelNotFoundException('Usuario no encontrado.');
        }

        $newRole = $this->roleRepository->findByName($input->roleName);
        if (!$newRole) {
            throw ValidationException::withMessages(['role' => ["El rol '{$input->roleName}' no existe."]]);
        }

        // Impedir asignación directa de OWNER mediante este endpoint
        if ($input->roleName === 'OWNER') {
            throw ValidationException::withMessages(['role' => ['Para transferir la propiedad del negocio utilice TransferOwnership.']]);
        }

        // Validar protección de OwnerGuard para evitar degradar al último OWNER
        $this->ownerGuard->ensureLastOwnerCannotBeDowngraded($user, $input->roleName);

        $oldRoles = $user->roles->pluck('name')->implode(', ');

        DB::transaction(function () use ($user, $newRole, $input) {
            // Reemplazar roles en pivote role_user
            $user->roles()->sync([$newRole->id => ['tenant_id' => $user->tenant_id]]);

            // Registrar en historial de auditoría de roles
            DB::table('user_role_history')->insert([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'role_id' => $newRole->id,
                'assigned_by' => $input->assignedById,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Invalidar versión de caché de permisos
        $this->permissionService->invalidateTenantPermissions($user->tenant_id);

        // Auditoría & Eventos
        $this->auditService->record('Identity', 'UserRoleChanged', oldValues: ['role' => $oldRoles], newValues: ['role' => $input->roleName]);
        $this->eventBus->dispatch(new UserRoleChanged($user, $oldRoles, $input->roleName, $input->assignedById));

        return new AssignUserRoleResult($user->load('roles'));
    }
}
