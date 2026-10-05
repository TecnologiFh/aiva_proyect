<?php

namespace App\Modules\Core\Services;

use App\Modules\Business\Services\BusinessHealthEngine;
use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantOnboardingService
{
    protected BusinessHealthEngine $healthEngine;
    protected UserTenantAccessService $tenantAccess;

    public function __construct()
    {
        $this->healthEngine = new BusinessHealthEngine();
        $this->tenantAccess = new UserTenantAccessService();
    }

    /**
     * Paso 1: Perfil Empresarial.
     */
    public function saveStepProfile(int $tenantId, array $data): array
    {
        $name = trim($data['commercial_name'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (empty($name)) {
            return ['success' => false, 'message' => 'El nombre comercial es obligatorio.'];
        }

        DB::table('tenants')->where('id', $tenantId)->update([
            'name' => $name,
            'status' => 'ACTIVE',
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('business_profiles')) {
            DB::table('business_profiles')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'commercial_name' => $name,
                    'phone' => $phone,
                    'address' => $data['address'] ?? 'Sin dirección configurada',
                    'business_category' => $data['business_category'] ?? 'GASTRONOMY',
                    'updated_at' => now(),
                ]
            );
        }

        return $this->formatStepResponse($tenantId, 1, 'Perfil empresarial registrado exitosamente.');
    }

    /**
     * Paso 2: Horarios Comerciales.
     */
    public function saveStepHours(int $tenantId, array $data): array
    {
        if (Schema::hasTable('business_hours')) {
            $hours = $data['hours'] ?? [];
            $bhCols = Schema::getColumnListing('business_hours');
            $dayMap = [
                'LUNES' => 1, '1' => 1,
                'MARTES' => 2, '2' => 2,
                'MIÉRCOLES' => 3, 'MIERCOLES' => 3, '3' => 3,
                'JUEVES' => 4, '4' => 4,
                'VIERNES' => 5, '5' => 5,
                'SÁBADO' => 6, 'SABADO' => 6, '6' => 6,
                'DOMINGO' => 7, '7' => 7,
            ];

            foreach ($hours as $day => $h) {
                $dayStr = strtoupper(trim((string)$day));
                $dayNum = $dayMap[$dayStr] ?? (is_numeric($day) ? (int)$day : 1);

                $record = [
                    'open_time' => $h['open_time'] ?? '09:00',
                    'close_time' => $h['close_time'] ?? '21:00',
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

        return $this->formatStepResponse($tenantId, 2, 'Horarios de atención guardados.');
    }

    /**
     * Paso 3: Catálogo de Productos.
     */
    public function saveStepProducts(int $tenantId, array $data): array
    {
        $name = trim($data['name'] ?? '');
        $price = (float)($data['price'] ?? 0);

        if (empty($name) || $price <= 0) {
            return ['success' => false, 'message' => 'Debe especificar un nombre y precio mayor a S/ 0.00.'];
        }

        DB::table('products')->insert([
            'tenant_id' => $tenantId,
            'name' => $name,
            'base_price' => $price,
            'stock' => (int)($data['stock'] ?? 10),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->formatStepResponse($tenantId, 3, 'Producto agregado al catálogo.');
    }

    /**
     * Paso 4: Control de Inventario.
     */
    public function saveStepStocks(int $tenantId, array $data): array
    {
        $productId = (int)($data['product_id'] ?? 0);
        $stock = (int)($data['stock'] ?? 0);

        if ($productId > 0) {
            DB::table('products')->where('id', $productId)->where('tenant_id', $tenantId)->update([
                'stock' => $stock,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('daily_stocks')) {
                DB::table('daily_stocks')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'product_id' => $productId, 'date' => date('Y-m-d')],
                    ['available_stock' => $stock, 'updated_at' => now()]
                );
            }
        }

        return $this->formatStepResponse($tenantId, 4, 'Stock de inventario actualizado.');
    }

    /**
     * Paso 5: Canal de WhatsApp.
     */
    public function saveStepChannels(int $tenantId, array $data): array
    {
        $phone = trim($data['phone'] ?? '');

        if (!empty($phone)) {
            DB::table('business_profiles')->where('tenant_id', $tenantId)->update([
                'phone' => $phone,
                'updated_at' => now(),
            ]);
        }

        return $this->formatStepResponse($tenantId, 5, 'Canal oficial de WhatsApp configurado.');
    }

    /**
     * Paso 6: Métodos de Pago (Yape / Plin / Transferencia).
     */
    public function saveStepPayments(int $tenantId, array $data): array
    {
        $provider = strtoupper(trim($data['provider'] ?? 'YAPE'));
        $account = trim($data['account_number'] ?? '');
        $holder = trim($data['account_holder'] ?? 'Comercio');

        if (Schema::hasTable('payment_destinations')) {
            DB::table('payment_destinations')->insert([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'account_number' => $account,
                'account_holder' => $holder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->formatStepResponse($tenantId, 6, 'Método de pago registrado exitosamente.');
    }

    /**
     * Paso 7: Zonas de Delivery.
     */
    public function saveStepDelivery(int $tenantId, array $data): array
    {
        $zoneName = trim($data['zone_name'] ?? '');
        $fee = (float)($data['delivery_fee'] ?? 0);

        if (!empty($zoneName)) {
            DB::table('delivery_zones')->insert([
                'tenant_id' => $tenantId,
                'zone_name' => $zoneName,
                'delivery_fee' => $fee,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->formatStepResponse($tenantId, 7, 'Zona de delivery guardada.');
    }

    /**
     * Paso 8: Configuración de IA.
     */
    public function saveStepAI(int $tenantId, array $data): array
    {
        $provider = strtoupper(trim($data['provider'] ?? 'GROQ'));

        if (Schema::hasTable('ai_configs')) {
            DB::table('ai_configs')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'provider' => $provider,
                    'model' => $data['model'] ?? 'llama-3.3-70b-versatile',
                    'is_enabled' => true,
                    'updated_at' => now(),
                ]
            );
        }

        return $this->formatStepResponse($tenantId, 8, 'Parámetros de IA configurados.');
    }

    /**
     * Paso 9: Gestión de Usuarios del Tenant.
     */
    public function saveStepUsers(int $tenantId, array $data): array
    {
        $email = trim($data['email'] ?? '');
        $role = strtoupper($data['role'] ?? 'OPERATOR');

        if (empty($email)) {
            return ['success' => false, 'message' => 'El correo del usuario es obligatorio.'];
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => $data['name'] ?? 'Operador AIVA',
                    'tenant_id' => $tenantId,
                    'role' => $role,
                    'updated_at' => now(),
                ]
            );
        }

        return $this->formatStepResponse($tenantId, 9, 'Usuario asignado al inquilino.');
    }

    /**
     * Paso 10: Revisión & Autodiagnóstico de Salud.
     */
    public function getStepReview(int $tenantId): array
    {
        $health = $this->healthEngine->evaluateTenantHealth($tenantId);
        return $this->formatStepResponse($tenantId, 10, 'Revisión final de autodiagnóstico completada.', $health);
    }

    /**
     * Formatear respuesta JSON unificada de avance de onboarding.
     */
    protected function formatStepResponse(int $tenantId, int $step, string $message, ?array $health = null): array
    {
        $healthReport = $health ?? $this->healthEngine->evaluateTenantHealth($tenantId);

        return [
            'success' => true,
            'step' => $step,
            'completed' => true,
            'message' => $message,
            'tenant_id' => $tenantId,
            'health' => [
                'passed' => $healthReport['score'],
                'total' => $healthReport['total'],
                'can_go_live' => ($healthReport['score'] === $healthReport['total']),
            ],
        ];
    }
}
