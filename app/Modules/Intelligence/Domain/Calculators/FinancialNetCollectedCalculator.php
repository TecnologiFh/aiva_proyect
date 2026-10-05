<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;

/**
 * Calculador de Recaudación Neta Canónica (P7).
 *
 * Regla de Integridad de Reembolsos (Section 13/14):
 * - net_collected = collected - refunded.
 * - Prohibido usar MAX(0) silencioso que oculte anomalías cuando los reembolsos superan los cobros.
 * - Si refunded > collected, el valor neto resultante es negativo, exponiendo fielmente el desbalance financiero.
 */
class FinancialNetCollectedCalculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly FinancialCollectedRevenueCalculator $collectedRevenueCalculator,
        private readonly FinancialRefundedAmountCalculator $refundedAmountCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $collected = $this->collectedRevenueCalculator->calculate($tenantId, $period, $filters);
        $refunded = $this->refundedAmountCalculator->calculate($tenantId, $period, $filters);

        return round($collected - $refunded, 2);
    }
}
