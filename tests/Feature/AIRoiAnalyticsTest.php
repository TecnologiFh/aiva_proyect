<?php

namespace Tests\Feature;

use App\Modules\Analytics\DTOs\AIRoiMetricsDTO;
use Tests\TestCase;

class AIRoiAnalyticsTest extends TestCase
{
    public function test_ai_roi_dto_calculation(): void
    {
        $dto = new AIRoiMetricsDTO(
            aiCostUsd: 12.30,
            aiSalesGeneratedPen: 18500.00,
            roiPercentage: 1504.2
        );

        $array = $dto->toArray();

        $this->assertEquals(12.30, $array['ai_cost_usd']);
        $this->assertEquals(18500.00, $array['ai_sales_generated_pen']);
        $this->assertEquals(1504.2, $array['roi_percentage']);
        $this->assertEquals('1,504.2%', $array['formatted_roi']);
    }
}
