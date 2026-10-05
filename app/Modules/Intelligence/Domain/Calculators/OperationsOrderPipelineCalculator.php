<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Pipeline Operativo de Órdenes (P8).
 * Realiza agregaciones analíticas directas sobre orders preservando cardinalidad exacta (Anti-Cartesian).
 */
class OperationsOrderPipelineCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $query = DB::table('orders')->where('tenant_id', $tenantId);

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('created_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        $allOrders = $query->select(['id', 'status', 'fulfillment_status', 'payment_status', 'total_amount'])->get();

        $totalOrders = count($allOrders);
        $totalVolume = round((float) $allOrders->sum('total_amount'), 2);

        $activeCount = 0;
        $activeVolume = 0.0;
        $completedCount = 0;
        $completedVolume = 0.0;
        $cancelledCount = 0;
        $cancelledVolume = 0.0;

        $stages = [
            'AWAITING_CONFIRMATION' => ['count' => 0, 'volume' => 0.0],
            'AWAITING_PAYMENT'      => ['count' => 0, 'volume' => 0.0],
            'CONFIRMED_PENDING'     => ['count' => 0, 'volume' => 0.0],
            'PREPARING'             => ['count' => 0, 'volume' => 0.0],
            'READY'                 => ['count' => 0, 'volume' => 0.0],
            'DELIVERED'             => ['count' => 0, 'volume' => 0.0],
            'CANCELLED'             => ['count' => 0, 'volume' => 0.0],
        ];

        foreach ($allOrders as $ord) {
            $status = (string) $ord->status;
            $fStatus = (string) $ord->fulfillment_status;
            $pStatus = (string) $ord->payment_status;
            $amount = (float) $ord->total_amount;

            $isCancelled = ($status === 'CANCELLED' || $fStatus === 'CANCELLED');
            $isTerminal = ($isCancelled || $status === 'COMPLETED' || $fStatus === 'DELIVERED');

            if ($isCancelled) {
                $cancelledCount++;
                $cancelledVolume += $amount;
                $stages['CANCELLED']['count']++;
                $stages['CANCELLED']['volume'] += $amount;
            } elseif ($status === 'COMPLETED' || $fStatus === 'DELIVERED') {
                $completedCount++;
                $completedVolume += $amount;
                $stages['DELIVERED']['count']++;
                $stages['DELIVERED']['volume'] += $amount;
            } else {
                // Active order
                $activeCount++;
                $activeVolume += $amount;

                if ($fStatus === 'READY') {
                    $stages['READY']['count']++;
                    $stages['READY']['volume'] += $amount;
                } elseif ($fStatus === 'PREPARING') {
                    $stages['PREPARING']['count']++;
                    $stages['PREPARING']['volume'] += $amount;
                } elseif ($status === 'CONFIRMED') {
                    $stages['CONFIRMED_PENDING']['count']++;
                    $stages['CONFIRMED_PENDING']['volume'] += $amount;
                } elseif ($status === 'AWAITING_PAYMENT' || $pStatus === 'PENDING') {
                    $stages['AWAITING_PAYMENT']['count']++;
                    $stages['AWAITING_PAYMENT']['volume'] += $amount;
                } else {
                    $stages['AWAITING_CONFIRMATION']['count']++;
                    $stages['AWAITING_CONFIRMATION']['volume'] += $amount;
                }
            }
        }

        $stagesFormatted = [];
        foreach ($stages as $stageKey => $data) {
            $count = $data['count'];
            $vol = round($data['volume'], 2);
            $pct = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 2) : 0.0;

            $stagesFormatted[] = [
                'stage' => $stageKey,
                'count' => $count,
                'volume' => $vol,
                'percentage' => $pct,
            ];
        }

        return [
            'total_orders' => $totalOrders,
            'total_volume' => $totalVolume,
            'active_orders' => [
                'count' => $activeCount,
                'volume' => round($activeVolume, 2),
                'percentage' => $totalOrders > 0 ? round(($activeCount / $totalOrders) * 100, 2) : 0.0,
            ],
            'completed_orders' => [
                'count' => $completedCount,
                'volume' => round($completedVolume, 2),
                'percentage' => $totalOrders > 0 ? round(($completedCount / $totalOrders) * 100, 2) : 0.0,
            ],
            'cancelled_orders' => [
                'count' => $cancelledCount,
                'volume' => round($cancelledVolume, 2),
                'percentage' => $totalOrders > 0 ? round(($cancelledCount / $totalOrders) * 100, 2) : 0.0,
            ],
            'stages' => $stagesFormatted,
        ];
    }
}
