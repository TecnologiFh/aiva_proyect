<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Services\BusinessHealthEngine;
use App\Modules\Core\Services\TenantLifecycleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConfigurationCenterController extends Controller
{
    protected TenantLifecycleManager $lifecycleManager;
    protected BusinessHealthEngine $healthEngine;

    public function __construct(TenantLifecycleManager $lifecycleManager, BusinessHealthEngine $healthEngine)
    {
        $this->lifecycleManager = $lifecycleManager;
        $this->healthEngine = $healthEngine;
    }

    /**
     * Vista principal del Configuration Center (/settings o /settings/{tab}).
     */
    public function index(Request $request, ?string $tab = null)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $tab = $tab ?? $request->query('tab', 'profile');

        $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        if ($profile && (int)$tenantId !== 4) {
            $updates = [];
            if ($profile->yape_info && str_contains($profile->yape_info, 'Donas Pepa')) {
                $profile->yape_info = null;
                $updates['yape_info'] = null;
            }
            if ($profile->plin_info && str_contains($profile->plin_info, 'Donas Pepa')) {
                $profile->plin_info = null;
                $updates['plin_info'] = null;
            }
            if ($profile->bank_account_info && $profile->bank_account_info === '002-191-002938481023-54') {
                $profile->bank_account_info = null;
                $updates['bank_account_info'] = null;
            }
            if (!empty($updates)) {
                DB::table('business_profiles')->where('id', $profile->id)->update($updates);
            }
        }
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $zones = DB::table('delivery_zones')->where('tenant_id', $tenantId)->get();
        $products = DB::table('products')->where('tenant_id', $tenantId)->get();
        $sources = DB::table('business_sources')->where('tenant_id', $tenantId)->get();

        $hasUserRoleCol = Schema::hasColumn('users', 'role');
        $roleExpr = $hasUserRoleCol ? 'COALESCE(roles.name, users.role, "OPERATOR")' : 'COALESCE(roles.name, "OPERATOR")';

        $teamUsers = DB::table('users')
            ->leftJoin('role_user', function($join) use ($tenantId) {
                $join->on('role_user.user_id', '=', 'users.id')
                     ->where('role_user.tenant_id', '=', $tenantId);
            })
            ->leftJoin('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('users.tenant_id', $tenantId)
            ->select('users.*', DB::raw("{$roleExpr} as role_name"))
            ->get();

        $numToDay = [
            1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
            4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'
        ];

        $businessHoursRaw = DB::table('business_hours')->where('tenant_id', $tenantId)->get();
        $businessHours = [];
        foreach ($businessHoursRaw as $bh) {
            $key = $numToDay[$bh->day_of_week] ?? strtolower((string)$bh->day_of_week);
            $businessHours[$key] = $bh;
        }

        $rolesCatalog = DB::table('roles')->get();

        $lifecycle = $this->lifecycleManager->getLifecycleState($tenantId);
        $health = $this->healthEngine->evaluateTenantHealth($tenantId);

        return view('configuration_center', compact('tenantId', 'tab', 'profile', 'tenant', 'zones', 'products', 'sources', 'teamUsers', 'businessHours', 'rolesCatalog', 'lifecycle', 'health'));
    }

    /**
     * API JSON: Guardar ajustes del Perfil Comercial desde /settings.
     */
    public function updateProfile(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $name = $request->input('commercial_name');
        $phone = $request->input('phone');
        $address = $request->input('address');
        $category = $request->input('business_category', 'COMMERCE');

        if (empty($name)) {
            return response()->json(['status' => 'ERROR', 'message' => 'El nombre comercial es obligatorio.'], 400);
        }

        DB::table('tenants')->where('id', $tenantId)->update([
            'name' => $name,
            'updated_at' => now(),
        ]);

        DB::table('business_profiles')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'commercial_name' => $name,
                'phone' => $phone,
                'address' => $address,
                'business_category' => $category,
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')->updateOrInsert(
                ['setting_key' => 'latest_active_tenant_id'],
                ['tenant_id' => $tenantId, 'setting_value' => (string)$tenantId, 'updated_at' => now()]
            );
        }

        if (!empty($phone) && Schema::hasTable('whatsapp_channels')) {
            $bindingService = new \App\Modules\Messaging\Services\WhatsAppChannelBindingService();
            $bindingService->bindChannel($tenantId, $phone);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Perfil empresarial actualizado correctamente.']);
    }

    /**
     * API JSON: Crear o Actualizar Producto del Catálogo Transaccional con Especificaciones de IA.
     */
    public function saveProduct(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $productId = $request->input('id');
        $name = $request->input('name');
        $price = (float) $request->input('price', 0.00);
        $stock = (int) $request->input('stock', 10);
        $sku = $request->input('sku');
        $advanceDays = (int) $request->input('advance_days', 0);
        $advancePercentage = (float) $request->input('advance_percentage', 0.00);
        $aiInstructions = trim($request->input('ai_instructions', ''));

        if (empty($name)) {
            return response()->json(['status' => 'ERROR', 'message' => 'El nombre del producto es obligatorio.'], 400);
        }

        $cols = Schema::getColumnListing('products');

        if ($productId) {
            $update = ['updated_at' => now()];
            if (in_array('name', $cols)) $update['name'] = $name;
            if (in_array('slug', $cols)) $update['slug'] = Str::slug($name);
            if (in_array('base_price', $cols)) $update['base_price'] = $price;
            if (in_array('price', $cols)) $update['price'] = $price;
            if (in_array('stock', $cols)) $update['stock'] = $stock;
            if (!empty($sku) && in_array('sku', $cols)) $update['sku'] = $sku;
            if (in_array('advance_days', $cols)) $update['advance_days'] = $advanceDays;
            if (in_array('advance_percentage', $cols)) $update['advance_percentage'] = $advancePercentage;
            if (in_array('ai_instructions', $cols)) $update['ai_instructions'] = $aiInstructions;

            DB::table('products')->where('id', $productId)->where('tenant_id', $tenantId)->update($update);
            return response()->json(['status' => 'SUCCESS', 'message' => 'Producto y reglas de IA actualizadas exitosamente.']);
        }

        // Crear producto nuevo
        $catId = DB::table('categories')->where('tenant_id', $tenantId)->value('id');
        if (!$catId && Schema::hasTable('categories')) {
            $catData = [
                'tenant_id' => $tenantId,
                'name' => 'General',
                'slug' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('categories', 'uuid')) {
                $catData['uuid'] = (string) Str::uuid();
            }
            $catId = DB::table('categories')->insertGetId($catData);
        }

        $insert = [
            'tenant_id' => $tenantId,
            'category_id' => $catId ?? 1,
            'name' => $name,
            'slug' => Str::slug($name),
            'uuid' => (string) Str::uuid(),
            'base_price' => $price,
            'sku' => $sku ?: 'SKU-' . strtoupper(Str::random(6)),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (in_array('stock', $cols)) $insert['stock'] = $stock;
        if (in_array('advance_days', $cols)) $insert['advance_days'] = $advanceDays;
        if (in_array('advance_percentage', $cols)) $insert['advance_percentage'] = $advancePercentage;
        if (in_array('ai_instructions', $cols)) $insert['ai_instructions'] = $aiInstructions;

        DB::table('products')->insert($insert);

        return response()->json(['status' => 'SUCCESS', 'message' => 'Producto creado con reglas de IA transaccionales.']);
    }

    /**
     * API JSON: Guardar Horarios Comerciales Flexibles por Día.
     */
    public function saveHours(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $hours = $request->input('hours', []);
        $prepMins = (int) $request->input('preparation_time_mins', 25);
        $genDays = (int) $request->input('general_advance_days', 1);
        $genPct = (float) $request->input('general_advance_percentage', 50.00);

        if (Schema::hasTable('business_profiles')) {
            $bpCols = Schema::getColumnListing('business_profiles');
            $updateBp = [];
            if (in_array('preparation_time_mins', $bpCols)) $updateBp['preparation_time_mins'] = $prepMins;
            if (in_array('general_advance_days', $bpCols)) $updateBp['general_advance_days'] = $genDays;
            if (in_array('general_advance_percentage', $bpCols)) $updateBp['general_advance_percentage'] = $genPct;

            if (!empty($updateBp)) {
                DB::table('business_profiles')->updateOrInsert(
                    ['tenant_id' => $tenantId],
                    array_merge($updateBp, ['updated_at' => now()])
                );
            }
        }

        if (Schema::hasTable('business_hours') && !empty($hours)) {
            $bhCols = Schema::getColumnListing('business_hours');
            $dayMap = [
                'LUNES' => 1, '1' => 1, 'lunes' => 1,
                'MARTES' => 2, '2' => 2, 'martes' => 2,
                'MIÉRCOLES' => 3, 'MIERCOLES' => 3, '3' => 3, 'miercoles' => 3,
                'JUEVES' => 4, '4' => 4, 'jueves' => 4,
                'VIERNES' => 5, '5' => 5, 'viernes' => 5,
                'SÁBADO' => 6, 'SABADO' => 6, '6' => 6, 'sabado' => 6,
                'DOMINGO' => 7, '7' => 7, 'domingo' => 7,
            ];

            foreach ($hours as $day => $h) {
                $dayStr = strtoupper(trim((string)$day));
                $dayNum = $dayMap[$dayStr] ?? (is_numeric($day) ? (int)$day : 1);

                $record = [
                    'open_time' => $h['open_time'] ?? '08:00',
                    'close_time' => $h['close_time'] ?? '20:00',
                    'updated_at' => now(),
                ];

                $isOpen = !empty($h['is_open']);

                if (in_array('is_open', $bhCols)) {
                    $record['is_open'] = $isOpen;
                }
                if (in_array('is_closed', $bhCols)) {
                    $record['is_closed'] = !$isOpen;
                }

                DB::table('business_hours')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'day_of_week' => $dayNum],
                    $record
                );
            }
        }

        if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Horarios por día y reglas de anticipación guardadas correctamente.']);
        }

        return redirect('/configuration?tab=hours')->with('success', 'Horarios por día y reglas de anticipación guardadas correctamente.');
    }

    /**
     * API JSON: Guardar Configuración de Pago (Yape, Plin, BCP, BBVA, Interbank, CCI).
     */
    public function savePayments(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $yapeInfo = trim($request->input('yape_info', ''));
        $plinInfo = trim($request->input('plin_info', ''));
        $bcpInfo = trim($request->input('bcp_info', ''));
        $bbvaInfo = trim($request->input('bbva_info', ''));
        $interbankInfo = trim($request->input('interbank_info', ''));
        $bankAccountInfo = trim($request->input('bank_account_info', ''));

        if (Schema::hasTable('business_profiles')) {
            if (!Schema::hasColumn('business_profiles', 'yape_info')) {
                try {
                    Schema::table('business_profiles', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('yape_info', 255)->nullable();
                        $table->string('plin_info', 255)->nullable();
                        $table->string('bank_account_info', 255)->nullable();
                    });
                } catch (\Throwable $e) {}
            }

            if (!Schema::hasColumn('business_profiles', 'bcp_info')) {
                try {
                    Schema::table('business_profiles', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('bcp_info', 255)->nullable();
                        $table->string('bbva_info', 255)->nullable();
                        $table->string('interbank_info', 255)->nullable();
                    });
                } catch (\Throwable $e) {}
            }

            DB::table('business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'yape_info' => $yapeInfo,
                    'plin_info' => $plinInfo,
                    'bcp_info' => $bcpInfo,
                    'bbva_info' => $bbvaInfo,
                    'interbank_info' => $interbankInfo,
                    'bank_account_info' => $bankAccountInfo,
                    'updated_at' => now(),
                ]
            );
        }

        if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Cuentas y métodos de pago oficiales guardados correctamente.',
                'data' => [
                    'yape_info' => $yapeInfo,
                    'plin_info' => $plinInfo,
                    'bcp_info' => $bcpInfo,
                    'bbva_info' => $bbvaInfo,
                    'interbank_info' => $interbankInfo,
                    'bank_account_info' => $bankAccountInfo,
                ]
            ]);
        }

        return redirect('/configuration?tab=payments')->with('success', 'Cuentas y métodos de pago oficiales guardados correctamente.');
    }

    /**
     * API JSON: Invitar o Crear Usuario de Equipo con Rol.
     */
    public function saveUsers(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $name = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $password = trim($request->input('password', ''));
        $roleName = strtoupper(trim($request->input('role', 'OPERATOR')));

        if (empty($name) || empty($email)) {
            return response()->json(['status' => 'ERROR', 'message' => 'El nombre y correo son obligatorios.'], 400);
        }

        $userId = DB::table('users')->where('email', $email)->value('id');

        $userCols = Schema::getColumnListing('users');

        if (!$userId) {
            $insertData = [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make($password ?: 'Pass123!'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (in_array('role', $userCols)) $insertData['role'] = $roleName;
            if (in_array('status', $userCols)) $insertData['status'] = 'ACTIVE';

            $userId = DB::table('users')->insertGetId($insertData);
        } else {
            $updateData = [
                'name' => $name,
                'email' => $email,
                'tenant_id' => $tenantId,
                'updated_at' => now(),
            ];
            if (in_array('role', $userCols)) $updateData['role'] = $roleName;
            if (!empty($password)) {
                $updateData['password'] = \Illuminate\Support\Facades\Hash::make($password);
            }

            DB::table('users')->where('id', $userId)->update($updateData);
        }

        // Asignar rol en pivote role_user
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        if ($roleId && Schema::hasTable('role_user')) {
            DB::table('role_user')->updateOrInsert(
                ['user_id' => $userId, 'tenant_id' => $tenantId],
                ['role_id' => $roleId, 'updated_at' => now()]
            );
        }

        return response()->json(['status' => 'SUCCESS', 'message' => "Usuario {$name} asignado como {$roleName} exitosamente."]);
    }

    /**
     * API JSON: Modificar Rol de Usuario del Tenant.
     */
    public function updateUserRole(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $targetUserId = (int) $request->input('user_id');
        $newRole = strtoupper($request->input('role'));

        $action = new \App\Modules\Identity\Actions\UpdateTenantRoleAction();
        return response()->json($action->execute(auth()->id() ?? 1, $targetUserId, $tenantId, $newRole));
    }

    /**
     * API JSON: Guardar Tono de Conversación de la IA (AI Guardian).
     */
    public function updateAiTone(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $aiTone = strtoupper(trim($request->input('ai_tone', 'FRIENDLY_EMPATHETIC')));

        if (Schema::hasTable('business_profiles') && !Schema::hasColumn('business_profiles', 'ai_tone')) {
            Schema::table('business_profiles', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('ai_tone', 50)->default('FRIENDLY_EMPATHETIC')->nullable();
            });
        }

        DB::table('business_profiles')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'ai_tone' => $aiTone,
                'updated_at' => now()
            ]
        );

        $toneLabels = [
            'FRIENDLY_EMPATHETIC' => 'Amigable, Cálido y Comercial',
            'FORMAL_CORPORATE' => 'Formal y Corporativo',
            'FAST_EXPRESS' => 'Rápido y Conciso (Inmediatez)',
        ];
        $toneName = $toneLabels[$aiTone] ?? $aiTone;

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Tono de conversación de AIVA actualizado a '{$toneName}' correctamente.",
            'ai_tone' => $aiTone
        ]);
    }

    /**
     * API JSON: Crear o Actualizar Zona de Delivery.
     */
    public function saveZone(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $zoneId = $request->input('id');
        $name = trim($request->input('zone_name', ''));
        $fee = (float) $request->input('delivery_fee', 0.00);
        $min = (float) $request->input('min_order_amount', 0.00);
        $mins = (int) $request->input('estimated_time_mins', 45);

        if (empty($name)) {
            return response()->json(['status' => 'ERROR', 'message' => 'El nombre del distrito o zona es obligatorio.'], 400);
        }

        if (!Schema::hasTable('delivery_zones')) {
            try {
                (require base_path('app/Modules/Orders/Database/Migrations/2026_08_19_000081_create_delivery_zones_table.php'))->up();
            } catch (\Throwable $e) {}
        }

        if ($zoneId) {
            DB::table('delivery_zones')
                ->where('id', $zoneId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'zone_name' => $name,
                    'delivery_fee' => $fee,
                    'min_order_amount' => $min,
                    'estimated_time_mins' => $mins,
                    'updated_at' => now(),
                ]);

            return response()->json(['status' => 'SUCCESS', 'message' => "Zona de delivery '{$name}' actualizada exitosamente."]);
        }

        DB::table('delivery_zones')->updateOrInsert(
            ['tenant_id' => $tenantId, 'zone_name' => $name],
            [
                'delivery_fee' => $fee,
                'min_order_amount' => $min,
                'estimated_time_mins' => $mins,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['status' => 'SUCCESS', 'message' => "Zona de delivery '{$name}' agregada exitosamente."]);
    }

    /**
     * API JSON: Guardar Canales Oficiales de Comunicación.
     */
    public function saveChannels(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $whatsappPhone = trim($request->input('whatsapp_phone', ''));

        if (Schema::hasTable('business_profiles')) {
            DB::table('business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                ['phone' => $whatsappPhone, 'updated_at' => now()]
            );
        }

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')->updateOrInsert(
                ['setting_key' => 'latest_active_tenant_id'],
                ['tenant_id' => $tenantId, 'setting_value' => (string)$tenantId, 'updated_at' => now()]
            );
        }

        if (!empty($whatsappPhone) && Schema::hasTable('whatsapp_channels')) {
            $bindingService = new \App\Modules\Messaging\Services\WhatsAppChannelBindingService();
            $bindingService->bindChannel($tenantId, $whatsappPhone);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Canales de comunicación guardados correctamente.']);
    }

    /**
     * API JSON: Guardar o Ajustar Inventario de Productos.
     */
    public function saveInventory(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $items = $request->input('items', []);

        if (!empty($items) && Schema::hasTable('products')) {
            foreach ($items as $item) {
                if (isset($item['id'], $item['stock'])) {
                    DB::table('products')
                        ->where('id', $item['id'])
                        ->where('tenant_id', $tenantId)
                        ->update(['stock' => (int)$item['stock'], 'updated_at' => now()]);
                }
            }
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Inventario actualizado correctamente.']);
    }
}
