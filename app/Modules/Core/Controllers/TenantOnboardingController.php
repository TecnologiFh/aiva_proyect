<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Services\BusinessHealthEngine;
use App\Modules\Core\Services\TenantLifecycleManager;
use App\Modules\Core\Services\TenantOnboardingService;
use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantOnboardingController extends Controller
{
    protected TenantLifecycleManager $lifecycleManager;
    protected BusinessHealthEngine $healthEngine;
    protected TenantOnboardingService $onboardingService;
    protected UserTenantAccessService $tenantAccess;

    public function __construct(TenantLifecycleManager $lifecycleManager, BusinessHealthEngine $healthEngine)
    {
        $this->lifecycleManager = $lifecycleManager;
        $this->healthEngine = $healthEngine;
        $this->onboardingService = new TenantOnboardingService();
        $this->tenantAccess = new UserTenantAccessService();
    }

    /**
     * Vista del Asistente de Onboarding Autoservicio (/onboarding).
     */
    public function index()
    {
        $tenantId = $this->tenantAccess->requireActiveTenant();
        $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $health = $this->healthEngine->evaluateTenantHealth($tenantId);
        $lifecycle = $this->lifecycleManager->getLifecycleState($tenantId);

        return view('tenant_onboarding', compact('tenantId', 'profile', 'tenant', 'health', 'lifecycle'));
    }

    /**
     * API JSON: Guardar dinámicamente cualquier paso del Onboarding (1 a 10).
     */
    public function saveStep(Request $request, int $step)
    {
        $tenantId = $this->tenantAccess->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $data = $request->all();

        switch ($step) {
            case 1:
                $result = $this->onboardingService->saveStepProfile($tenantId, $data);
                break;
            case 2:
                $result = $this->onboardingService->saveStepHours($tenantId, $data);
                break;
            case 3:
                $result = $this->onboardingService->saveStepProducts($tenantId, $data);
                break;
            case 4:
                $result = $this->onboardingService->saveStepStocks($tenantId, $data);
                break;
            case 5:
                $result = $this->onboardingService->saveStepChannels($tenantId, $data);
                break;
            case 6:
                $result = $this->onboardingService->saveStepPayments($tenantId, $data);
                break;
            case 7:
                $result = $this->onboardingService->saveStepDelivery($tenantId, $data);
                break;
            case 8:
                $result = $this->onboardingService->saveStepAI($tenantId, $data);
                break;
            case 9:
                $result = $this->onboardingService->saveStepUsers($tenantId, $data);
                break;
            case 10:
                $result = $this->onboardingService->getStepReview($tenantId);
                break;
            default:
                return response()->json(['success' => false, 'message' => 'Paso inválido'], 400);
        }

        return response()->json($result);
    }
}
