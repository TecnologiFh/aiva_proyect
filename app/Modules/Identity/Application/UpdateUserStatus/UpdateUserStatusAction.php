<?php

namespace App\Modules\Identity\Application\UpdateUserStatus;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Enums\UserStatus;
use App\Core\Events\UserActivated;
use App\Core\Events\UserBlocked;
use App\Core\Services\AuditService;
use App\Core\Services\PermissionService;
use App\Modules\Identity\Services\OwnerGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateUserStatusAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly OwnerGuard $ownerGuard,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService,
        private readonly PermissionService $permissionService
    ) {}

    public function execute(UpdateUserStatusInput $input): UpdateUserStatusResult
    {
        $user = $this->userRepository->findByUuid($input->userUuid);
        if (!$user) {
            throw new ModelNotFoundException('Usuario no encontrado.');
        }

        // Si se intenta inhabilitar o bloquear, verificar que no sea el último OWNER
        if ($input->newStatus === UserStatus::INACTIVE->value || $input->newStatus === UserStatus::BLOCKED->value) {
            $this->ownerGuard->ensureLastOwnerCannotBeBlocked($user);
        }

        $oldStatus = $user->status->value;

        $updatedUser = $this->userRepository->update($user->id, [
            'status' => $input->newStatus,
            'updated_by' => $input->updatedById,
        ]);

        // Invalidar versión de caché de permisos
        $this->permissionService->invalidateTenantPermissions($user->tenant_id);

        // Auditoría & Eventos
        $this->auditService->record('Identity', 'UserStatusUpdated', oldValues: ['status' => $oldStatus], newValues: ['status' => $input->newStatus]);

        if ($input->newStatus === UserStatus::BLOCKED->value || $input->newStatus === UserStatus::INACTIVE->value) {
            $this->eventBus->dispatch(new UserBlocked($updatedUser, $input->updatedById));
        } else {
            $this->eventBus->dispatch(new UserActivated($updatedUser, $input->updatedById));
        }

        return new UpdateUserStatusResult($updatedUser);
    }
}
