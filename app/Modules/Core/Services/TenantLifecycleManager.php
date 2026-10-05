<?php

namespace App\Modules\Core\Services;

use App\Modules\Business\Services\BusinessHealthEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantLifecycleManager
{
    // ESTADOS DEL CICLO DE VIDA DEL TENANT (GOBIERNO EXCLUSIVO DE BACKEND)
    public const STATE_ONBOARDING   = 'ONBOARDING';
    public const STATE_CONFIGURING  = 'CONFIGURING';
    public const STATE_READY_TEST   = 'READY_TEST';
    public const STATE_CERTIFIED    = 'CERTIFIED';
    public const STATE_PRODUCTION   = 'PRODUCTION';
    public const STATE_PAUSED       = 'PAUSED';
    public const STATE_SUSPENDED    = 'SUSPENDED';

    public const ALLOWED_LIFECYCLE_STATES = [
        self::STATE_ONBOARDING,
        self::STATE_CONFIGURING,
        self::STATE_READY_TEST,
        self::STATE_CERTIFIED,
        self::STATE_PRODUCTION,
        self::STATE_PAUSED,
        self::STATE_SUSPENDED,
    ];

    /**
     * Obtener el estado actual del Lifecycle de un Tenant.
     */
    public function getLifecycleState(int $tenantId): string
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return self::STATE_ONBOARDING;
        }

        if ($tenant->status === 'SUSPENDED') {
            return self::STATE_SUSPENDED;
        }
        if ($tenant->status === 'PAUSED') {
            return self::STATE_PAUSED;
        }

        if (isset($tenant->lifecycle_state) && !empty($tenant->lifecycle_state)) {
            return $tenant->lifecycle_state;
        }

        return ($tenant->status === 'ACTIVE') ? self::STATE_PRODUCTION : self::STATE_CONFIGURING;
    }

    /**
     * Validar de forma estricta en Backend si la transición de estado es permitida.
     * Cero confianza en llamadas externas / cliente JS (Zero-Trust UI Contract).
     */
    public function evaluateTransition(int $tenantId, string $targetState): array
    {
        if (!in_array($targetState, self::ALLOWED_LIFECYCLE_STATES, true)) {
            return [
                'allowed' => false,
                'status' => $this->getLifecycleState($tenantId),
                'reason' => "Estado de destino no válido: '{$targetState}'",
                'blocking_checks' => ['INVALID_TARGET_STATE'],
            ];
        }

        $currentState = $this->getLifecycleState($tenantId);

        // Si se intenta pasar a PRODUCTION
        if ($targetState === self::STATE_PRODUCTION) {
            // Re-evaluar Business Health
            $healthService = app(BusinessHealthEngine::class);
            $healthReport = $healthService->evaluateTenantHealth($tenantId);

            if ($healthReport['score'] < $healthReport['total']) {
                $failingChecks = array_map(function ($chk) {
                    return $chk['check_id'];
                }, array_filter($healthReport['checks'], function ($c) {
                    return $c['status'] !== 'PASS';
                }));

                return [
                    'allowed' => false,
                    'status' => $currentState,
                    'reason' => 'Business Health incompleto (Score: ' . $healthReport['score'] . '/' . $healthReport['total'] . ').',
                    'blocking_checks' => $failingChecks,
                ];
            }
        }

        return [
            'allowed' => true,
            'status' => $targetState,
            'reason' => 'Transición permitida por el Backend Go-Live Guard.',
            'blocking_checks' => [],
        ];
    }

    /**
     * Transicionar el estado del Lifecycle y registrar auditoría inmutable.
     */
    public function transitionTo(int $tenantId, string $targetState, string $actor = 'BACKEND_GUARD'): array
    {
        $evaluation = $this->evaluateTransition($tenantId, $targetState);
        if (!$evaluation['allowed']) {
            return $evaluation;
        }

        $currentState = $this->getLifecycleState($tenantId);

        // Actualizar en BD
        $updateData = [
            'lifecycle_state' => $targetState,
            'updated_at' => now(),
        ];

        if ($targetState === self::STATE_PRODUCTION || $targetState === self::STATE_READY_TEST) {
            $updateData['status'] = 'ACTIVE';
        } elseif ($targetState === self::STATE_PAUSED) {
            $updateData['status'] = 'PAUSED';
        } elseif ($targetState === self::STATE_SUSPENDED) {
            $updateData['status'] = 'SUSPENDED';
        }

        // Si la columna lifecycle_state no existe en tenants, se actualiza status
        try {
            DB::table('tenants')->where('id', $tenantId)->update($updateData);
        } catch (\Throwable $e) {
            DB::table('tenants')->where('id', $tenantId)->update([
                'status' => ($targetState === self::STATE_SUSPENDED) ? 'SUSPENDED' : (($targetState === self::STATE_PAUSED) ? 'PAUSED' : 'ACTIVE'),
                'updated_at' => now(),
            ]);
        }

        // Audit Log
        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'module' => 'CORE_LIFECYCLE',
            'action' => 'TENANT_LIFECYCLE_TRANSITION',
            'new_values' => json_encode([
                'previous_state' => $currentState,
                'new_state' => $targetState,
                'actor' => $actor,
            ]),
            'created_at' => now(),
        ]);

        return [
            'allowed' => true,
            'status' => $targetState,
            'reason' => "Lifecycle de Tenant #{$tenantId} transicionado a '{$targetState}' con éxito.",
            'blocking_checks' => [],
        ];
    }
}
