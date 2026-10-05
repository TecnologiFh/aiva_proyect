<?php

namespace App\Modules\Analytics\Services;

use App\Modules\AI\Models\AIRequest;
use App\Modules\Analytics\DTOs\AIRoiMetricsDTO;
use App\Modules\Orders\Models\Order;

class AIRoiAnalyticsService
{
    public function getAIRoiMetrics(int $tenantId, float $exchangeRate = 3.80): AIRoiMetricsDTO
    {
        $aiCostUsd = (float) AIRequest::where('tenant_id', $tenantId)->sum('estimated_cost');

        // Ventas de pedidos generados desde la IA
        $aiSalesGeneratedPen = (float) Order::where('tenant_id', $tenantId)
            ->whereNotNull('conversation_id')
            ->whereIn('status', ['CONFIRMED', 'PREPARING', 'READY', 'DELIVERED'])
            ->sum('total_amount');

        $aiCostPen = $aiCostUsd * $exchangeRate;

        $roiPercentage = 0.0;
        if ($aiCostPen > 0) {
            $roiPercentage = (($aiSalesGeneratedPen - $aiCostPen) / $aiCostPen) * 100;
        } elseif ($aiSalesGeneratedPen > 0) {
            $roiPercentage = 1000.0; // Valor por defecto cuando el costo es muy bajo
        }

        return new AIRoiMetricsDTO(
            aiCostUsd: round($aiCostUsd, 4),
            aiSalesGeneratedPen: round($aiSalesGeneratedPen, 2),
            roiPercentage: round($roiPercentage, 1)
        );
    }
}
