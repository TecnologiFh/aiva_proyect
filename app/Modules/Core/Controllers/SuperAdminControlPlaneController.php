<?php

namespace App\Modules\Core\Controllers;

use App\Modules\Identity\Models\PlatformUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\AuditService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminControlPlaneController extends Controller
{
    /**
     * Dashboard Principal del Control Plane (/admin)
     */
    public function index()
    {
        $tenantsTotal = DB::table('tenants')->count();
        $tenantsActive = DB::table('tenants')->where('status', 'ACTIVE')->count();
        $tenantsSuspended = DB::table('tenants')->where('status', 'SUSPENDED')->count();

        $usersTotal = DB::table('users')->count();
        $usersActive = DB::table('users')->where('status', 'ACTIVE')->count();
        $usersSuspended = DB::table('users')->where('status', 'SUSPENDED')->count();

        $ordersToday = DB::table('orders')->whereDate('created_at', now()->today())->count();
        $totalRevenueToday = DB::table('orders')->whereDate('created_at', now()->today())->sum('total_amount');

        // Salud de Infraestructura y Servicios
        $dbStatus = 'HEALTHY';
        try { DB::select('SELECT 1'); } catch (\Throwable $e) { $dbStatus = 'FAILED'; }

        $redisGate = (new \App\Modules\Core\Services\RedisRuntimeGateService())->evaluateRuntime();

        return view('admin.dashboard', compact(
            'tenantsTotal', 'tenantsActive', 'tenantsSuspended',
            'usersTotal', 'usersActive', 'usersSuspended',
            'ordersToday', 'totalRevenueToday', 'dbStatus', 'redisGate'
        ));
    }

    /**
     * Gestión de Tenants (/admin/tenants)
     */
    public function tenants()
    {
        $tenants = DB::table('tenants')
            ->select('tenants.*', DB::raw('(SELECT COUNT(*) FROM users WHERE users.tenant_id = tenants.id) as users_count'))
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function createTenant()
    {
        return view('admin.tenants.create');
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'business_type' => 'required|string|max:100',
            'owner_name' => 'required|string|max:150',
            'owner_email' => 'required|email|max:150',
            'owner_password' => 'required|string|min:8',
        ]);

        DB::transaction(function () use ($data) {
            $slug = Str::slug($data['name']) . '-' . rand(100, 999);
            $tenantId = DB::table('tenants')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'slug' => $slug,
                'business_type' => $data['business_type'],
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Business Profile
            DB::table('business_profiles')->insert([
                'tenant_id' => $tenantId,
                'commercial_name' => $data['name'],
                'phone' => '9' . rand(10000000, 99999999),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Owner User
            $ownerUser = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'status' => 'ACTIVE',
            ]);

            $ownerRole = Role::where('name', 'OWNER')->first();
            if ($ownerRole) {
                DB::table('role_user')->insert([
                    'user_id' => $ownerUser->id,
                    'role_id' => $ownerRole->id,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            AuditService::logAction('TENANT_CREATED', auth('platform')->id() ?? 1, 'SUPERADMIN', $tenantId, 'tenants', (string)$tenantId, null, ['name' => $data['name'], 'owner' => $data['owner_email']]);
        });

        return redirect('/admin/tenants')->with('status', 'Tenant creado correctamente con perfil de negocio y Owner asignado.');
    }

    public function updateTenantStatus(Request $request, int $id)
    {
        $status = strtoupper($request->validate(['status' => 'required|in:ACTIVE,SUSPENDED,ARCHIVED'])['status']);
        
        $oldTenant = DB::table('tenants')->where('id', $id)->first();
        DB::table('tenants')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        AuditService::logAction("TENANT_{$status}", auth('platform')->id() ?? 1, 'SUPERADMIN', $id, 'tenants', (string)$id, ['status' => $oldTenant->status ?? 'UNKNOWN'], ['status' => $status]);

        return back()->with('status', "Estado del Tenant #{$id} actualizado a {$status}.");
    }

    /**
     * Ver y Gestionar Feature Entitlements (/admin/tenants/{id}/features)
     */
    public function tenantFeatures(int $id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->firstOrFail();
        
        $features = DB::table('tenant_features')
            ->where('tenant_id', $id)
            ->pluck('is_enabled', 'feature_key')
            ->toArray();

        return view('admin.tenants.features', compact('tenant', 'features'));
    }

    public function updateTenantFeatures(Request $request, int $id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->firstOrFail();
        $inputFeatures = $request->input('features', []);

        $availableKeys = [
            'dashboard_inbox', 'operations_center', 'catalog_inventory', 'customer_360',
            'business_settings', 'knowledge_hub', 'ai_sales', 'ai_playground',
            'business_health', 'advanced_analytics'
        ];

        DB::transaction(function () use ($id, $inputFeatures, $availableKeys) {
            foreach ($availableKeys as $key) {
                $isEnabled = isset($inputFeatures[$key]) && (bool)$inputFeatures[$key];

                DB::table('tenant_features')->updateOrInsert(
                    ['tenant_id' => $id, 'feature_key' => $key],
                    [
                        'is_enabled' => $isEnabled,
                        'updated_by' => auth('platform')->id() ?? 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            AuditService::logAction('FEATURE_ENTITLEMENT_UPDATED', auth('platform')->id() ?? 1, 'SUPERADMIN', $id, 'tenant_features', (string)$id, null, $inputFeatures);
        });

        return redirect("/admin/tenants/{$id}/features")->with('success', "Feature Entitlements del Tenant #{$id} actualizadas correctamente.");
    }

    /**
     * Gestión Global de Usuarios (/admin/users)
     */
    public function users()
    {
        $users = DB::table('users')
            ->join('tenants', 'users.tenant_id', '=', 'tenants.id')
            ->select('users.*', 'tenants.name as tenant_name')
            ->orderBy('users.id', 'desc')
            ->get();

        $platformUsers = DB::table('platform_users')->get();

        return view('admin.users.index', compact('users', 'platformUsers'));
    }

    public function updateUserStatus(Request $request, int $id)
    {
        $status = strtoupper($request->validate(['status' => 'required|in:ACTIVE,SUSPENDED'])['status']);
        
        $oldUser = DB::table('users')->where('id', $id)->first();
        DB::table('users')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        AuditService::logAction("USER_{$status}", auth('platform')->id() ?? 1, 'SUPERADMIN', $oldUser->tenant_id, 'users', (string)$id, ['status' => $oldUser->status], ['status' => $status]);

        return back()->with('status', "Estado del usuario #{$id} actualizado a {$status}.");
    }

    /**
     * Matriz RBAC (/admin/roles)
     */
    public function roles()
    {
        $roles = Role::with('permissions')->get();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Monitor de Sesiones (/admin/security/sessions)
     */
    public function sessions()
    {
        $activeSessions = DB::table('users')
            ->select('id', 'name', 'email', 'tenant_id', 'status', 'updated_at')
            ->get();

        return view('admin.security.sessions', compact('activeSessions'));
    }

    public function revokeSession(Request $request, int $userId)
    {
        // Invalidar tokens API y forzar regeneración de estado
        DB::table('personal_access_tokens')->where('tokenable_id', $userId)->delete();
        AuditService::logAction('SESSION_REVOKED', auth('platform')->id() ?? 1, 'SUPERADMIN', null, 'users', (string)$userId);

        return back()->with('status', "Sesión del usuario #{$userId} revocada server-side correctamente.");
    }

    /**
     * Visor Inmutable de Auditoría (/admin/audit)
     */
    public function audit()
    {
        $auditLogs = DB::table('audit_logs')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                if (isset($log->uuid) && is_string($log->uuid) && strlen($log->uuid) === 16) {
                    try {
                        $log->uuid = \Ramsey\Uuid\Uuid::fromBytes($log->uuid)->toString();
                    } catch (\Throwable $e) {
                        $log->uuid = bin2hex($log->uuid);
                    }
                }
                return $log;
            });

        return view('admin.audit.index', compact('auditLogs'));
    }

    /**
     * Consola de Certificación Visual (/admin/certification)
     */
    public function certification()
    {
        return view('admin.certification.index');
    }
}
