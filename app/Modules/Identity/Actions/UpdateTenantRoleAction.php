<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Services\AuditService;
use App\Services\Authorization\AccessResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateTenantRoleAction
{
    /**
     * Ejecutar la actualización segura de roles dentro del Tenant Plane
     */
    public function execute(int $targetUserId, int $newRoleId, int $actorId, int $tenantId): array
    {
        // 1. Autorización de Backend
        if (!AccessResolver::can('tenant.roles.manage', 'tenant_rbac')) {
            throw new HttpException(403, 'Acceso denegado: No posee permisos para administrar roles.');
        }

        // 2. Proteger la cuenta del OWNER (RG24)
        $targetUserRole = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $targetUserId)
            ->where('role_user.tenant_id', $tenantId)
            ->value('roles.name');

        if (strtoupper($targetUserRole ?? '') === 'OWNER' && $targetUserId === $actorId) {
            throw new HttpException(403, 'Seguridad: El Propietario no puede degradar ni modificar su propia cuenta.');
        }

        // 3. Validar Techo de Permisos (Permission Ceiling - RG23)
        $newRole = DB::table('roles')->where('id', $newRoleId)->first();
        if (!$newRole || str_starts_with(strtolower($newRole->name), 'platform') || strtoupper($newRole->name) === 'SUPERADMIN') {
            throw new HttpException(403, 'Seguridad: Intento de asignación de rol de plataforma denegado por Permission Ceiling.');
        }

        // 4. Aplicar mutación
        DB::table('role_user')->updateOrInsert(
            ['user_id' => $targetUserId, 'tenant_id' => $tenantId],
            ['role_id' => $newRoleId]
        );

        // 5. Registrar auditoría inmutable
        AuditService::logAction(
            'ROLE_CHANGED',
            $actorId,
            'TENANT_USER',
            $tenantId,
            'users',
            (string)$targetUserId,
            ['old_role' => $targetUserRole, 'new_role' => $newRole->name],
            ['correlation_id' => 'role-change-' . uniqid()]
        );

        return [
            'status' => 'SUCCESS',
            'message' => "Rol de usuario actualizado a {$newRole->name} correctamente.",
            'user_id' => $targetUserId,
            'new_role' => $newRole->name,
        ];
    }
}
