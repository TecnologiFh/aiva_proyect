<?php

namespace App\Modules\Identity\Services;

use App\Core\Enums\RoleType;
use App\Core\Enums\UserStatus;
use App\Modules\Identity\Models\User;

use Illuminate\Validation\ValidationException;

class OwnerGuard
{
    /**
     * Valida que no se elimine al último OWNER del tenant.
     */
    public function ensureLastOwnerCannotBeDeleted(User $user): void
    {
        if ($this->isLastOwner($user)) {
            throw ValidationException::withMessages([
                'user' => ['No es posible eliminar al único propietario (OWNER) activo del negocio. Debe transferir la propiedad primero.'],
            ]);
        }
    }

    /**
     * Valida que no se bloquee o inactive al último OWNER del tenant.
     */
    public function ensureLastOwnerCannotBeBlocked(User $user): void
    {
        if ($this->isLastOwner($user)) {
            throw ValidationException::withMessages([
                'user' => ['No es posible inhabilitar o bloquear al único propietario (OWNER) activo del negocio.'],
            ]);
        }
    }

    /**
     * Valida que no se degrade el rol del último OWNER del tenant.
     */
    public function ensureLastOwnerCannotBeDowngraded(User $user, string $newRole): void
    {
        if ($newRole !== RoleType::OWNER->value && $this->isLastOwner($user)) {
            throw ValidationException::withMessages([
                'role' => ['No es posible modificar el rol del único propietario (OWNER) activo. Utilice la función de Transferencia de Propiedad.'],
            ]);
        }
    }

    /**
     * Comprueba si el usuario es el único OWNER activo del tenant.
     */
    public function isLastOwner(User $user): bool
    {
        $hasOwnerRole = $user->roles()->where('name', RoleType::OWNER->value)->exists();
        if (!$hasOwnerRole) {
            return false;
        }

        $activeOwnersCount = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('roles', fn($q) => $q->where('name', RoleType::OWNER->value))
            ->count();

        return $activeOwnersCount <= 1;
    }
}
