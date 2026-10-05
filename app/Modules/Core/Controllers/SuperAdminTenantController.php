<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAdminTenantController extends Controller
{
    /**
     * Vista principal del Panel de Propietario del SaaS (Super-Admin).
     */
    public function index()
    {
        return view('super_admin_tenants');
    }

    /**
     * API JSON: Obtener todas las empresas/tenants con métricas agregadas.
     */
    public function getTenantsData()
    {
        $tenants = DB::table('tenants')->get();

        $tenantsData = [];
        foreach ($tenants as $t) {
            $profile = DB::table('business_profiles')->where('tenant_id', $t->id)->first();
            $ordersCount = DB::table('orders')->where('tenant_id', $t->id)->count();
            $conversationsCount = DB::table('conversations')->where('tenant_id', $t->id)->count();
            $productsCount = DB::table('products')->where('tenant_id', $t->id)->count();

            $hoursRaw = $profile->opening_hours ?? null;
            $hoursStr = 'Lunes a Domingo 09:00 - 20:00';
            if ($hoursRaw) {
                $decoded = json_decode($hoursRaw, true);
                if (is_array($decoded) && isset($decoded['schedule'])) {
                    $hoursStr = $decoded['schedule'];
                } elseif (is_string($hoursRaw)) {
                    $hoursStr = $hoursRaw;
                }
            }

            $tenantsData[] = [
                'id' => $t->id,
                'name' => $t->name ?? ($profile->commercial_name ?? 'Empresa Tenant #' . $t->id),
                'commercial_name' => $profile->commercial_name ?? ($t->name ?? 'Empresa Tenant #' . $t->id),
                'category' => $profile->business_category ?? 'COMMERCE',
                'phone' => $profile->phone ?? 'Sin Teléfono',
                'address' => $profile->address ?? 'Sin Dirección',
                'opening_hours' => $hoursStr,
                'website_url' => $profile->website_url ?? '',
                'description' => $profile->description ?? '',
                'status' => $t->status ?? 'ACTIVE',
                'orders_count' => $ordersCount,
                'conversations_count' => $conversationsCount,
                'products_count' => $productsCount,
                'created_at' => $t->created_at ?? now()->toDateString(),
            ];
        }

        $activeCount = 0;
        $suspendedCount = 0;
        foreach ($tenantsData as $td) {
            if (strtoupper((string)($td['status'] ?? '')) === 'SUSPENDED') {
                $suspendedCount++;
            } else {
                $activeCount++;
            }
        }

        return response()->json([
            'status' => 'OK',
            'global_metrics' => [
                'total_tenants' => count($tenantsData),
                'active_tenants' => $activeCount,
                'suspended_tenants' => $suspendedCount,
                'total_platform_orders' => DB::table('orders')->count(),
                'total_platform_conversations' => DB::table('conversations')->count(),
            ],
            'tenants' => $tenantsData,
        ]);
    }

    /**
     * API JSON: Crear una nueva empresa/tenant en el SaaS.
     */
    public function createTenant(Request $request)
    {
        $name = $request->input('name');
        $category = $request->input('category', 'COMMERCE');
        $phone = $request->input('phone', '+51900000000');
        $address = $request->input('address', 'Lima, Perú');

        if (empty($name)) {
            return response()->json(['status' => 'ERROR', 'message' => 'El nombre de la empresa es obligatorio.'], 400);
        }

        $tenantId = DB::table('tenants')->insertGetId([
            'name' => $name,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('business_profiles')->insert([
            'tenant_id' => $tenantId,
            'commercial_name' => $name,
            'business_category' => $category,
            'phone' => $phone,
            'address' => $address,
            'opening_hours' => 'Lunes a Domingo 09:00 - 20:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Empresa '{$name}' creada exitosamente con Tenant ID #{$tenantId}.",
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * API JSON: Actualizar datos comerciales de una empresa/tenant existente.
     */
    public function updateTenant(Request $request, $id)
    {
        try {
            $tenant = DB::table('tenants')->where('id', $id)->first();
            if (!$tenant) {
                return response()->json(['status' => 'ERROR', 'message' => 'Tenant no encontrado.'], 404);
            }

            $name = $request->input('name');
            $category = $request->input('category');
            $phone = $request->input('phone');
            $address = $request->input('address');
            $openingHours = $request->input('opening_hours');
            $websiteUrl = $request->input('website_url');

            if (empty($name)) {
                return response()->json(['status' => 'ERROR', 'message' => 'El nombre comercial es obligatorio.'], 400);
            }

            // Actualizar nombre en tenants
            DB::table('tenants')->where('id', $id)->update([
                'name' => $name,
                'updated_at' => now(),
            ]);

            // Formatear opening_hours para tipo de columna JSON de MySQL
            $formattedOpeningHours = json_encode(['schedule' => $openingHours ?? 'Lunes a Domingo 09:00 - 20:00']);

            // Actualizar o crear perfil empresarial
            $profileExists = DB::table('business_profiles')->where('tenant_id', $id)->exists();
            if ($profileExists) {
                DB::table('business_profiles')->where('tenant_id', $id)->update([
                    'commercial_name' => $name,
                    'business_category' => $category,
                    'phone' => $phone,
                    'address' => $address,
                    'opening_hours' => $formattedOpeningHours,
                    'website_url' => $websiteUrl,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('business_profiles')->insert([
                    'tenant_id' => $id,
                    'commercial_name' => $name,
                    'business_category' => $category,
                    'phone' => $phone,
                    'address' => $address,
                    'opening_hours' => $formattedOpeningHours,
                    'website_url' => $websiteUrl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Registrar en Audit Log
            DB::table('audit_logs')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $id,
                'module' => 'CORE_TENANTS',
                'action' => 'TENANT_PROFILE_UPDATED',
                'new_values' => json_encode(['updated_name' => $name, 'category' => $category, 'updated_by' => 'SUPER_ADMIN']),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Empresa '{$name}' (Tenant #{$id}) actualizada correctamente.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Error en base de datos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API JSON: Activar o Inactivar un negocio (Control de Licencia/Suscripción).
     */
    public function toggleStatus(Request $request, $id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            return response()->json(['status' => 'ERROR', 'message' => 'Tenant no encontrado.'], 404);
        }

        $newStatus = ($tenant->status === 'ACTIVE') ? 'SUSPENDED' : 'ACTIVE';

        DB::table('tenants')->where('id', $id)->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

        // Registrar en Audit Log
        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $id,
            'module' => 'CORE_TENANTS',
            'action' => 'TENANT_STATUS_TOGGLED',
            'new_values' => json_encode(['previous_status' => $tenant->status, 'new_status' => $newStatus, 'changed_by' => 'SUPER_ADMIN']),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Estado de Tenant #{$id} cambiado a '{$newStatus}'.",
            'new_status' => $newStatus,
        ]);
    }
}
