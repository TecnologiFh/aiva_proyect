<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

interface MetricCalculatorInterface
{
    /**
     * Calcula determinísticamente el valor de la métrica en modo estricto READ-ONLY.
     *
     * @param int $tenantId
     * @param TimePeriod $period
     * @param array $filters
     * @return mixed
     */
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): mixed;
}
