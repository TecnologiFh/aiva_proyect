<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador Canónico de Ingresos Efectivamente Cobrados (P7).
 *
 * Principio Constitucional de Autoridad de Pago (Model A):
 * - La tabla `payments` (App\Modules\Payments\Models\Payment) es la ÚNICA autoridad monetaria canónica del pago.
 * - `payment_allocations` representa exclusivamente la distribución o aplicación operativa del pago hacia pedidos,
 *   por lo que NO es una fuente de dinero independiente y JAMÁS se suma a `payments.amount`.
 * - ONE ECONOMIC PAYMENT = ONE FINANCIAL FACT = ONE COUNT = ONE MONETARY AMOUNT.
 */
class FinancialCollectedRevenueCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): float
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        $query = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'APPROVED');

        if ($startUtc) {
            $query->where(DB::raw('COALESCE(paid_at, created_at)'), '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where(DB::raw('COALESCE(paid_at, created_at)'), '<=', $endUtc);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return round((float) $query->sum('amount'), 2);
    }
}
