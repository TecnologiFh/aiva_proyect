<?php

namespace App\Modules\Business\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Services\BusinessHealthEngine;
use App\Modules\Core\Services\TenantLifecycleManager;
use Illuminate\Http\Request;

class BusinessHealthController extends Controller
{
    protected BusinessHealthEngine $healthEngine;
    protected TenantLifecycleManager $lifecycleManager;

    public function __construct(BusinessHealthEngine $healthEngine, TenantLifecycleManager $lifecycleManager)
    {
        $this->healthEngine = $healthEngine;
        $this->lifecycleManager = $lifecycleManager;
    }

    /**
     * Vista del Tablero Autodiagnóstico Go-Live (/business-health).
     */
    public function index()
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $health = $this->healthEngine->evaluateTenantHealth($tenantId);
        $lifecycle = $this->lifecycleManager->getLifecycleState($tenantId);

        return view('business_health', compact('tenantId', 'health', 'lifecycle'));
    }

    /**
     * API JSON: Obtener evaluación de salud en tiempo real.
     */
    public function getHealthData()
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $health = $this->healthEngine->evaluateTenantHealth($tenantId);
        $health['lifecycle'] = $this->lifecycleManager->getLifecycleState($tenantId);

        return response()->json($health);
    }

    /**
     * API JSON: Intentar activar producción (Go-Live Guard en Backend).
     * Zero-Trust UI Contract: La activación se valida 100% en servidor.
     */
    public function activateProduction(Request $request)
    {
        $tenantId = (new \App\Modules\Tenant\Services\UserTenantAccessService())->requireActiveTenant();
        $result = $this->lifecycleManager->transitionTo($tenantId, TenantLifecycleManager::STATE_PRODUCTION, 'MERCHANT_ADMIN');

        if (!$result['allowed']) {
            return response()->json([
                'status' => 'BLOCKED',
                'message' => $result['reason'],
                'blocking_checks' => $result['blocking_checks'],
            ], 403);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => '¡Atención automática de AIVA en WhatsApp activada oficialmente en PRODUCCIÓN!',
            'lifecycle' => 'PRODUCTION',
        ]);
    }
}
