<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calculador de Desglose de Motivos y Atribución de Cancelación (P8).
 */
class OperationsCancellationReasonsCalculator implements MetricCalculatorInterface
{
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('cancelled_at');

        $startUtc = $period->getStartUtcString();
        $endUtc = $period->getEndUtcString();

        if ($startUtc) {
            $query->where('cancelled_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $query->where('cancelled_at', '<=', $endUtc);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        $cancelledRecords = $query->select(['id', 'cancellation_reason', 'cancelled_by', 'cancellation_channel'])->get();
        $totalCancelled = count($cancelledRecords);

        if ($totalCancelled === 0) {
            return [
                'total_cancelled' => 0,
                'by_reason' => [],
                'by_actor' => [],
                'by_channel' => [],
            ];
        }

        $reasonsMap = [];
        $actorsMap = [];
        $channelsMap = [];

        foreach ($cancelledRecords as $row) {
            $reason = trim((string) ($row->cancellation_reason ?? ''));
            if ($reason === '') {
                $reason = 'UNSPECIFIED';
            }
            $reasonsMap[$reason] = ($reasonsMap[$reason] ?? 0) + 1;

            $actor = strtoupper(trim((string) ($row->cancelled_by ?? '')));
            if ($actor === '') {
                $actor = 'UNSPECIFIED';
            }
            $actorsMap[$actor] = ($actorsMap[$actor] ?? 0) + 1;

            $chan = strtoupper(trim((string) ($row->cancellation_channel ?? '')));
            if ($chan === '') {
                $chan = 'UNSPECIFIED';
            }
            $channelsMap[$chan] = ($channelsMap[$chan] ?? 0) + 1;
        }

        arsort($reasonsMap);
        arsort($actorsMap);
        arsort($channelsMap);

        $formatBreakdown = function (array $map) use ($totalCancelled, $reason): array {
            $list = [];
            foreach ($map as $key => $count) {
                $pct = round(($count / $totalCancelled) * 100, 2);
                $list[] = [
                    'label' => $key,
                    'count' => $count,
                    'percentage' => $pct,
                ];
            }
            return $list;
        };

        return [
            'total_cancelled' => $totalCancelled,
            'by_reason' => $formatBreakdown($reasonsMap),
            'by_actor' => $formatBreakdown($actorsMap),
            'by_channel' => $formatBreakdown($channelsMap),
        ];
    }
}
