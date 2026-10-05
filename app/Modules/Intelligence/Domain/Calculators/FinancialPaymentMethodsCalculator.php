<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Desglose por Métodos de Pago Canónicos (P7).
 */
class FinancialPaymentMethodsCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
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

        $paymentsGrouped = $query
            ->selectRaw('payment_method, COUNT(*) as p_count, SUM(amount) as p_sum')
            ->groupBy('payment_method')
            ->get();

        $totalCollected = (float) $paymentsGrouped->sum('p_sum');

        $result = [];
        foreach ($paymentsGrouped as $row) {
            $method = strtoupper(trim((string) $row->payment_method));
            if ($method === '') {
                $method = 'OTHER';
            }
            $amt = round((float) $row->p_sum, 2);
            $share = $totalCollected > 0 ? round(($amt / $totalCollected) * 100, 2) : 0.00;
            $result[] = [
                'method' => $method,
                'payment_count' => (int) $row->p_count,
                'collected_amount' => $amt,
                'share' => $share,
            ];
        }

        usort($result, fn($a, $b) => $b['collected_amount'] <=> $a['collected_amount']);

        return $result;
    }
}
