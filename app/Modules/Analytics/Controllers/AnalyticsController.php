<?php

namespace App\Modules\Analytics\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Analytics\Application\GetAIPerformance\GetAIPerformanceAction;
use App\Modules\Analytics\Application\GetAIPerformance\GetAIPerformanceInput;
use App\Modules\Analytics\Application\GetExecutiveDashboard\GetExecutiveDashboardAction;
use App\Modules\Analytics\Application\GetExecutiveDashboard\GetExecutiveDashboardInput;
use App\Modules\Analytics\Application\GetSalesAnalytics\GetSalesAnalyticsAction;
use App\Modules\Analytics\Application\GetSalesAnalytics\GetSalesAnalyticsInput;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AnalyticsController extends Controller
{
    public function dashboard(GetExecutiveDashboardAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $input = new GetExecutiveDashboardInput($tenantId);

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Dashboard Ejecutivo de Business Intelligence recuperado exitosamente'
        );
    }

    public function sales(Request $request, GetSalesAnalyticsAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new GetSalesAnalyticsInput(
            tenantId: $tenantId,
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Analítica detallada de ventas comerciales recuperada exitosamente'
        );
    }

    public function aiPerformance(GetAIPerformanceAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $input = new GetAIPerformanceInput($tenantId);

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Reporte de eficiencia, costos USD y ROI de la IA recuperado exitosamente'
        );
    }
}
