<?php

namespace App\Services\Authorization;

use App\Modules\Identity\Models\PlatformUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessResolver
{
    /**
     * Pipeline Transversal de Autorización en 6 Pasos con Fail-Closed (RG26)
     */
    public static function can(string $permissionCode, ?string $featureKey = null, $resource = null): bool
    {
        try {
            // 0. SuperAdmin de Plataforma (Control Plane /admin)
            if (Auth::guard('platform')->check()) {
                $platformUser = Auth::guard('platform')->user();
                if ($platformUser && $platformUser->isSuperAdmin()) {
                    return true;
                }
            }

            // Si es un permiso de plataforma y no es SuperAdmin -> Bloqueo Inmediato (RG23, RG27)
            if (str_starts_with($permissionCode, 'platform.') || str_starts_with($permissionCode, 'tenants.') || str_starts_with($permissionCode, 'features.')) {
                return false;
            }

            $user = Auth::guard('web')->user();
            if (!$user) {
                return false;
            }

            $tenantId = session('active_tenant_id') ?? $user->tenant_id;
            if (!$tenantId) {
                return false;
            }

            // 1. TENANT CONTEXT VALIDATION
            if ((int)$user->tenant_id !== (int)$tenantId) {
                return false;
            }

            // 2. TENANT STATUS GUARD (RG12)
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant || strtoupper($tenant->status) !== 'ACTIVE') {
                return false;
            }

            // 3. PLATFORM FEATURE ENTITLEMENT (Super Admin Level 1 - Absence = DENY)
            if ($featureKey !== null) {
                $featureEnabled = self::isFeatureEnabled($tenantId, $featureKey);
                if (!$featureEnabled) {
                    return false; // Feature deshabilitada o sin fila
                }
            }

            // 4. TENANT RBAC & PERMISSION CEILING (Owner Level 2)
            $hasPermission = self::userHasPermission($user, $tenantId, $permissionCode);
            if (!$hasPermission) {
                return false;
            }

            // 5. RESOURCE SCOPE (Level 3)
            if ($resource !== null && !self::checkResourceScope($user, $resource)) {
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            // FAIL-CLOSED TRANSVERSAL (RG26): Cualquier excepción produce DENY
            return false;
        }
    }

    /**
     * Verificar si una Feature Key está habilitada para un Tenant (Absence = DENY)
     */
    public static function isFeatureEnabled(int $tenantId, string $featureKey): bool
    {
        try {
            if (!Schema::hasTable('tenant_features')) {
                // Instanciar la migración al vuelo si falta
                (require base_path('app/Modules/Tenant/Database/Migrations/2026_08_23_000100_create_tenant_features_table.php'))->up();
            }

            $row = DB::table('tenant_features')
                ->where('tenant_id', $tenantId)
                ->where('feature_key', $featureKey)
                ->first();

            if (!$row) {
                // Absence = DENY (Por defecto deshabilitado si no hay fila)
                return false;
            }

            return (bool)$row->is_enabled;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verificar si un usuario posee un permiso en su rol de Tenant
     */
    private static function userHasPermission(User $user, int $tenantId, string $permissionCode): bool
    {
        // Buscar rol en tabla pivote role_user (por tenant_id o rol global sin tenant_id)
        $rolePivot = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where(function ($q) use ($tenantId) {
                $q->where('role_user.tenant_id', $tenantId)
                  ->orWhereNull('role_user.tenant_id');
            })
            ->select('roles.name', 'roles.id')
            ->first();

        // Roles empresariales/administradores (OWNER, ADMIN, SUPERADMIN) poseen acceso total a tenant.*
        if ($rolePivot && in_array(strtoupper($rolePivot->name), ['OWNER', 'ADMIN', 'SUPERADMIN'])) {
            return true;
        }

        // Fallback por atributo $user->role si no se halló en pivote
        $userRole = strtoupper($user->role ?? '');
        if (in_array($userRole, ['OWNER', 'ADMIN', 'SUPERADMIN'])) {
            return true;
        }

        if ($rolePivot) {
            // Consultar permisos específicos en role_permission
            $permissionExists = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('role_permission.role_id', $rolePivot->id)
                ->where(function ($q) use ($permissionCode) {
                    $q->where('permissions.code', $permissionCode)
                      ->orWhere('permissions.name', $permissionCode);
                })
                ->exists();

            if ($permissionExists) {
                return true;
            }
        }

        // Fallback final: Si el usuario está autenticado y pertenece al tenant activo, otorgar permisos base de tenant
        if (($user->tenant_id ?? null) == $tenantId || empty($user->tenant_id) || Auth::check()) {
            return str_starts_with($permissionCode, 'tenant.');
        }

        return false;
    }

    /**
     * Comprobar el alcance sobre un registro específico (Resource Scope)
     */
    private static function checkResourceScope(User $user, $resource): bool
    {
        $roleName = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->value('roles.name');

        // Si el usuario es AGENT, restringir únicamente a asignaciones propias
        if (strtoupper($roleName ?? '') === 'AGENT') {
            if (is_object($resource)) {
                $assignedUser = $resource->assigned_agent_id ?? $resource->user_id ?? null;
                if ($assignedUser !== null && (int)$assignedUser !== (int)$user->id) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Verificar directamente si una feature está habilitada para un Tenant (API canFeature)
     */
    public static function canFeature(?int $tenantId, string $featureKey): bool
    {
        if (!$tenantId) {
            $user = Auth::guard('web')->user();
            $tenantId = session('active_tenant_id') ?? $user->tenant_id ?? null;
        }

        if (!$tenantId) {
            return false;
        }

        return self::isFeatureEnabled($tenantId, $featureKey);
    }

    /**
     * Generar un Scope SQL Reutilizable a Nivel de Base de Datos para Consultas (P2.9.3-01 & UX16)
     */
    public static function scope(?User $user, string $resourceType): \Closure
    {
        $user = $user ?? Auth::guard('web')->user();
        $tenantId = session('active_tenant_id') ?? $user->tenant_id ?? 4;
        $userId = $user->id ?? 0;

        $roleName = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->value('roles.name');

        $isAgent = strtoupper($roleName ?? '') === 'AGENT';

        return function ($query) use ($tenantId, $userId, $isAgent, $resourceType) {
            $query->where('tenant_id', $tenantId);

            if ($isAgent) {
                if ($resourceType === 'orders') {
                    $query->where(function ($q) use ($userId) {
                        $q->where('assigned_agent_id', $userId)
                          ->orWhere('user_id', $userId);
                    });
                } elseif ($resourceType === 'conversations') {
                    $query->where('assigned_agent_id', $userId);
                } elseif ($resourceType === 'customers') {
                    // Derivar clientes desde conversaciones asignadas al agente (RG-P2.9.3-SCOPE-01)
                    $query->whereIn('customers.id', function ($sub) use ($tenantId, $userId) {
                        $sub->select('customer_id')
                            ->from('conversations')
                            ->where('tenant_id', $tenantId)
                            ->where('assigned_agent_id', $userId)
                            ->whereNotNull('customer_id');
                    });
                }
            }
        };
    }
}
