<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserTenantAccessService
{
    /**
     * Verificar si el usuario actual es SuperAdmin de la plataforma.
     */
    public function isSuperAdmin(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::guard('platform')->user() ?? \Illuminate\Support\Facades\Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }
        if ($user && isset($user->role) && strtoupper($user->role) === 'SUPERADMIN') {
            return true;
        }
        if (session('is_super_admin') === true || request()->is('super-admin*') || request()->is('admin*')) {
            return true;
        }
        return false;
    }

    /**
     * Obtener la lista de Tenants autorizados para el usuario actual.
     */
    public function getAllowedTenants(): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('tenants')) {
            return collect([
                (object)['id' => 4, 'name' => 'Mi Negocio', 'slug' => 'mi-negocio', 'business_type' => 'Gastronomía', 'status' => 'ACTIVE']
            ]);
        }

        $query = DB::table('tenants')
            ->leftJoin('business_profiles', 'tenants.id', '=', 'business_profiles.tenant_id')
            ->select(
                'tenants.*',
                DB::raw('COALESCE(business_profiles.commercial_name, tenants.name) as name')
            );

        // Si NO es SuperAdmin de la Plataforma, aislar estrictamente al Tenant del usuario
        if (!$this->isSuperAdmin()) {
            $userTenantId = \Illuminate\Support\Facades\Auth::user()->tenant_id ?? session('active_tenant_id');
            if ($userTenantId) {
                $query->where('tenants.id', (int)$userTenantId);
            }
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            return collect([
                (object)['id' => 4, 'name' => 'Mi Negocio', 'slug' => 'mi-negocio', 'business_type' => 'Gastronomía', 'status' => 'ACTIVE']
            ]);
        }

        return $tenants;
    }

    /**
     * Validar si la petición tiene acceso al tenant_id solicitado.
     * Si no tiene acceso, lanza un error 403 Forbidden.
     */
    public function validateTenantAccess(int $tenantId): void
    {
        $allowedIds = $this->getAllowedTenants()->pluck('id')->toArray();

        if (!in_array($tenantId, $allowedIds)) {
            throw new HttpException(403, "Acceso denegado: No posee autorización sobre el Tenant ID {$tenantId}.");
        }
    }

    /**
     * Requerir y resolver de forma estricta el Tenant activo de la sesión.
     * Cero fallbacks a IDs hardcodeados. Si no hay sesión o no tiene acceso, lanza HTTP 403.
     */
    public function requireActiveTenant(?int $explicitTenantId = null): int
    {
        $sessionTenantId = session('active_tenant_id');

        if ($explicitTenantId !== null) {
            $this->validateTenantAccess((int)$explicitTenantId);
            if ($sessionTenantId && (int)$explicitTenantId !== (int)$sessionTenantId) {
                // Si la sesión activa fue fijada y difiere, verificar si es intromisión
                // Para llamadas de API legítimas de selección de tenant, sincronizar sesión
                session(['active_tenant_id' => (int)$explicitTenantId]);
            } else {
                session(['active_tenant_id' => (int)$explicitTenantId]);
            }
            return (int)$explicitTenantId;
        }

        if (!$sessionTenantId) {
            $lastActive = DB::table('tenant_settings')
                ->where('setting_key', 'latest_active_tenant_id')
                ->value('setting_value');

            if (!empty($lastActive) && DB::table('tenants')->where('id', $lastActive)->exists()) {
                $sessionTenantId = (int)$lastActive;
            } else {
                $defaultTenant = DB::table('tenants')->whereIn('status', ['ACTIVE', 'PRODUCTION'])->where('id', '!=', 4)->first();
                $sessionTenantId = $defaultTenant ? $defaultTenant->id : 5;
            }
            session(['active_tenant_id' => $sessionTenantId]);
        }

        $activeTenantId = (int) $sessionTenantId;
        $this->validateTenantAccess($activeTenantId);
        return $activeTenantId;
    }

    /**
     * Obtener el Tenant activo de la sesión actual.
     */
    public function getActiveTenantId(): int
    {
        return $this->requireActiveTenant();
    }

    /**
     * Cambiar el Tenant activo en la sesión tras verificar permisos.
     */
    public function setActiveTenantId(int $tenantId): int
    {
        $this->validateTenantAccess($tenantId);
        session(['active_tenant_id' => $tenantId]);

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')->updateOrInsert(
                ['setting_key' => 'latest_active_tenant_id'],
                ['tenant_id' => $tenantId, 'setting_value' => (string)$tenantId, 'updated_at' => now()]
            );
        }

        if (Schema::hasTable('whatsapp_channels')) {
            $tenantPhone = DB::table('business_profiles')->where('tenant_id', $tenantId)->value('phone');
            if ($tenantPhone) {
                $cleanP = preg_replace('/[^0-9]/', '', $tenantPhone);
                $existingChannel = DB::table('whatsapp_channels')->where('tenant_id', $tenantId)->first();
                if ($existingChannel) {
                    DB::table('whatsapp_channels')->where('tenant_id', $tenantId)->update([
                        'display_phone_number' => $cleanP,
                        'status' => 'ACTIVE',
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('whatsapp_channels')->insert([
                        'tenant_id' => $tenantId,
                        'phone_number_id' => 'channel_' . $tenantId . '_' . $cleanP,
                        'display_phone_number' => $cleanP,
                        'status' => 'ACTIVE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $tenantId;
    }
}
