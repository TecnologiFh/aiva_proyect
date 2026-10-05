<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador Canónico del Conteo de Pagos Aprobados (P7).
 *
 * Principio Constitucional:
 * - Cuenta hechos de pago canónicos aprobados en la tabla `payments`.
 * - NO cuenta alocaciones, NO cuenta evidencias, NO cuenta webhooks.
 * - Multi-evidence / Multi-webhook / Multi-allocation mapping collapse into 1 approved payment.
 */
class FinancialPaymentCountCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): int
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

        return (int) $query->count();
    }
}
