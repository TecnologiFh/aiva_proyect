<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Comparativa entre Valor Comercial y Efectivo Cobrado (P9).
 * Grain: ORDER.
 * Anti-Cartesian: Preagregación independiente de pagos por orden para evitar duplicación.
 */
class CrossOrderValueVsCollectedCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        // Subquery de pagos aprobados preagregados por orden (Model A P7)
        $paymentsSub = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'APPROVED')
            ->selectRaw('order_id, SUM(amount) as collected_amount')
            ->groupBy('order_id');

        $query = DB::table('orders as o')
            ->leftJoinSub($paymentsSub, 'p', function ($join) {
                $join->on('p.order_id', '=', 'o.id');
            })
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED');

        if ($startUtc) {
            $query->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('o.created_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }
        if (!empty($filters['payment_method'])) {
            $query->where('o.payment_method', $filters['payment_method']);
        }

        $records = $query->selectRaw("
            o.id,
            o.total_amount,
            COALESCE(p.collected_amount, 0) as paid_amount,
            o.payment_status
        ")->get();

        $totalOrderValue = 0.0;
        $totalCollectedCash = 0.0;
        $fullyPaidCount = 0;
        $partiallyPaidCount = 0;
        $unpaidCount = 0;

        foreach ($records as $r) {
            $orderVal = (float) $r->total_amount;
            $paidVal = (float) $r->paid_amount;

            $totalOrderValue += $orderVal;
            $totalCollectedCash += $paidVal;

            if ($paidVal >= ($orderVal - 0.01) && $orderVal > 0) {
                $fullyPaidCount++;
            } elseif ($paidVal > 0.001) {
                $partiallyPaidCount++;
            } else {
                $unpaidCount++;
            }
        }

        $totalOrderValue = round($totalOrderValue, 2);
        $totalCollectedCash = round($totalCollectedCash, 2);
        $uncollectedExposure = round(max(0.00, $totalOrderValue - $totalCollectedCash), 2);
        $coverageRate = $totalOrderValue > 0 ? round(($totalCollectedCash / $totalOrderValue) * 100, 2) : 0.00;

        return [
            'total_order_value' => $totalOrderValue,
            'total_collected_cash' => $totalCollectedCash,
            'uncollected_exposure' => $uncollectedExposure,
            'collection_coverage_rate' => $coverageRate,
            'orders_count' => count($records),
            'fully_paid_orders_count' => $fullyPaidCount,
            'partially_paid_orders_count' => $partiallyPaidCount,
            'unpaid_orders_count' => $unpaidCount,
        ];
    }
}
