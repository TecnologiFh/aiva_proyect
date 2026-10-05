<?php

namespace App\Core\Services;

use App\Modules\Identity\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PermissionService
{
    /**
     * Comprueba si el usuario posee un permiso específico (acepta String o Enum).
     */
    public function userCan(User $user, string|BackedEnum $permission): bool
    {
        $permCode = $permission instanceof BackedEnum ? $permission->value : $permission;
        $userPermissions = $this->getUserPermissions($user);

        return in_array('*', $userPermissions) || in_array($permCode, $userPermissions);
    }

    /**
     * Comprueba si el usuario tiene TODOS los permisos indicados (modo AND).
     */
    public function userHasAll(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->userCan($user, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Comprueba si el usuario tiene AL MENOS UNO de los permisos indicados (modo OR).
     */
    public function userHasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userCan($user, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Precarga la caché Redis de permisos para un usuario (Cache Warm-up en login).
     */
    public function warmupCache(User $user): array
    {
        return $this->getUserPermissions($user, forceRefresh: true);
    }

    /**
     * Incrementa la versión de caché de permisos del tenant para invalidar la caché de todos sus usuarios atómicamente.
     */
    public function invalidateTenantPermissions(int $tenantId): void
    {
        $versionKey = "tenant:{$tenantId}:permissions:version";
        try {
            Redis::connection('cache')->incr($versionKey);
        } catch (\Throwable $e) {
            Cache::increment($versionKey);
        }
    }

    /**
     * Obtiene los códigos de permisos efectivos del usuario con estrategia de caché por versión.
     */
    public function getUserPermissions(User $user, bool $forceRefresh = false): array
    {
        $tenantId = $user->tenant_id;
        $version = $this->getTenantCacheVersion($tenantId);
        $cacheKey = "tenant:{$tenantId}:permissions:user:{$user->id}:v{$version}";

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Cargar permisos desde los roles asociados al usuario
        $permissions = [];
        foreach ($user->roles as $role) {
            if ($role->name === 'OWNER') {
                $permissions[] = '*';
                break;
            }

            foreach ($role->permissions ?? [] as $perm) {
                $permissions[] = $perm->code;
            }
        }

        $permissions = array_unique($permissions);

        // Guardar en caché Redis por 24 horas
        Cache::put($cacheKey, $permissions, now()->addDay());

        return $permissions;
    }

    private function getTenantCacheVersion(int $tenantId): int
    {
        $versionKey = "tenant:{$tenantId}:permissions:version";
        return (int) Cache::get($versionKey, 1);
    }
}
