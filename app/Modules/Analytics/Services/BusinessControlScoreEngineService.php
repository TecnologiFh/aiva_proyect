<?php

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Facades\DB;

class BusinessControlScoreEngineService
{
    protected FinancialAllocationTruthEngineService $truthEngine;

    public function __construct(FinancialAllocationTruthEngineService $truthEngine)
    {
        $this->truthEngine = $truthEngine;
    }

    /**
     * Calculate explainable Business Control Score (0 - 100) with deterministic math provenance.
     * Formula: Control Score = CLAMPMIN0_MAX100(Base Score + Bonus + Total Penalties)
     * Contract: Bonus = 0 in v1.0.
     */
    public function calculateControlScore(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        // 1. Fetch Financial Truth Metrics safely
        $financialTruth = $this->truthEngine->calculateFinancialMetrics($tenantId, $dateFrom, $dateTo);
        $summary = $financialTruth['financial_summary'] ?? [];
        $anomalies = $financialTruth['anomalies_detected'] ?? [];

        $baseScore = 100;
        $bonus = 0; // Explicitly frozen contract: Bonus = 0 in v1.0
        $penalties = [];

        // 2. Anomaly Penalty 1: Refund Overflow Anomaly
        if (!empty($anomalies['has_refund_overflow'])) {
            $overflowAmt = (float) ($anomalies['refund_overflow_amount'] ?? 0.00);
            $penalty = min(25, max(10, (int) ($overflowAmt / 10)));
            $penalties[] = [
                'rule_id' => 'REFUND_OVERFLOW_ANOMALY',
                'rule_version' => 'v1.0',
                'input_values' => ['refund_overflow_amount' => $overflowAmt],
                'threshold' => 'refund_overflow_amount > 0.00',
                'penalty' => -$penalty,
                'detail' => "Exceso de devoluciones detectado (S/ " . number_format($overflowAmt, 2) . ")",
            ];
        }

        // 3. Anomaly Penalty 2: Overpayment Anomaly
        if (!empty($anomalies['has_overpayment'])) {
            $overpayAmt = (float) ($anomalies['overpayment_amount'] ?? 0.00);
            $penalty = 5;
            $penalties[] = [
                'rule_id' => 'OVERPAYMENT_ANOMALY',
                'rule_version' => 'v1.0',
                'input_values' => ['overpayment_amount' => $overpayAmt],
                'threshold' => 'overpayment_amount > 0.00',
                'penalty' => -$penalty,
                'detail' => "Saldo de sobrepago no conciliado (S/ " . number_format($overpayAmt, 2) . ")",
            ];
        }

        // 4. Anomaly Penalty 3: Cash Differences in Daily Closings
        $closingsQuery = DB::table('daily_closings')->where('tenant_id', $tenantId);
        if ($dateFrom) $closingsQuery->where('closing_date', '>=', $dateFrom);
        if ($dateTo) $closingsQuery->where('closing_date', '<=', $dateTo);

        $cashDiffsSum = (float) ($closingsQuery->sum(DB::raw('ABS(cash_difference)')) ?? 0.00);
        if ($cashDiffsSum > 0.00) {
            $penalty = min(20, (int) ($cashDiffsSum / 10) + 5);
            $penalties[] = [
                'rule_id' => 'CASH_DIFFERENCE_PENALTY',
                'rule_version' => 'v1.0',
                'input_values' => ['cash_difference_sum' => $cashDiffsSum],
                'threshold' => 'cash_difference_sum > 0.00',
                'penalty' => -$penalty,
                'detail' => "Descalce de caja acumulado en cierres diarios (S/ " . number_format($cashDiffsSum, 2) . ")",
            ];
        }

        // 5. Anomaly Penalty 4: Pending Sales Ratio
        $netSales = (float) ($summary['net_sales'] ?? 0.00);
        $pendingSales = (float) ($summary['pending_sales'] ?? 0.00);
        if ($netSales > 0.00 && $pendingSales > 0.00) {
            $pendingRatio = round(($pendingSales / $netSales) * 100, 2);
            if ($pendingRatio > 50.00) {
                $penalty = 15;
                $penalties[] = [
                    'rule_id' => 'HIGH_PENDING_SALES_RATIO',
                    'rule_version' => 'v1.0',
                    'input_values' => ['pending_ratio' => $pendingRatio],
                    'threshold' => 'pending_ratio > 50.00%',
                    'penalty' => -$penalty,
                    'detail' => "Ratio de ventas pendientes excede 50% (" . number_format($pendingRatio, 1) . "%)",
                ];
            } elseif ($pendingRatio > 25.00) {
                $penalty = 8;
                $penalties[] = [
                    'rule_id' => 'MODERATE_PENDING_SALES_RATIO',
                    'rule_version' => 'v1.0',
                    'input_values' => ['pending_ratio' => $pendingRatio],
                    'threshold' => '25.00% < pending_ratio <= 50.00%',
                    'penalty' => -$penalty,
                    'detail' => "Ratio de ventas pendientes entre 25% y 50% (" . number_format($pendingRatio, 1) . "%)",
                ];
            }
        }

        // Calculate Final Score strictly clamped between 0 and 100
        $totalPenalty = array_sum(array_column($penalties, 'penalty'));
        $rawScore = $baseScore + $bonus + $totalPenalty;
        $finalScore = max(0, min(100, (int) $rawScore));

        $grade = ($finalScore >= 90) ? 'EXCELLENT' : (($finalScore >= 75) ? 'GOOD' : (($finalScore >= 60) ? 'FAIR' : 'CRITICAL'));

        return [
            'status' => 'CONTROL_SCORE_CALCULATED',
            'tenant_id' => $tenantId,
            'control_score' => $finalScore,
            'grade' => $grade,
            'formula' => 'CLAMPMIN0_MAX100(Base Score + Bonus + Total Penalties)',
            'base_score' => $baseScore,
            'bonus' => $bonus,
            'total_penalties' => $totalPenalty,
            'penalty_breakdown' => $penalties,
            'governance' => [
                'control_score_version' => 'v1.0',
                'bonus_contract' => 'BONUS_ZERO_FROZEN_v1.0',
                'no_ai_inference' => true,
                'gate_id' => 'CONTROL-SCORE-PROVENANCE-GATE-03',
                'calculated_at' => now()->toIso8601String(),
            ]
        ];
    }

    /**
     * Calculate versioned RFM Metrics for a Customer.
     * Handles NULL / Empty datasets safely without PHP warnings or undefined offsets.
     */
    public function calculateCustomerRfm(int $tenantId, int $customerId, string $policyVersion = 'v1.0'): array
    {
        $customerStats = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw("
                COUNT(id) as frequency_count,
                COALESCE(SUM(total_amount), 0) as monetary_total,
                MAX(created_at) as last_order_at
            ")
            ->first();

        $frequency = (int) ($customerStats->frequency_count ?? 0);
        $monetary = (float) ($customerStats->monetary_total ?? 0.00);
        $lastOrderAt = $customerStats->last_order_at ?? null;

        $recencyDays = $lastOrderAt ? (int) now()->diffInDays(\Carbon\Carbon::parse($lastOrderAt)) : 999;

        // Apply Policy Version v1.0 Rules safely
        if ($policyVersion === 'v1.0') {
            if ($frequency === 0 && $monetary === 0.00 && $lastOrderAt === null) {
                return [
                    'status' => 'RFM_CALCULATED_NO_ACTIVITY',
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'metrics' => [
                        'recency_days' => 999,
                        'frequency_count' => 0,
                        'monetary_total' => '0.00',
                    ],
                    'rfm_score' => '111',
                    'rfm_segment' => 'NO_ACTIVITY',
                    'governance' => [
                        'rfm_policy_version' => $policyVersion,
                        'no_ai_inference' => true,
                        'gate_id' => 'RFM-POLICY-PROVENANCE-GATE-01',
                        'calculated_at' => now()->toIso8601String(),
                    ]
                ];
            }

            $rScore = ($recencyDays <= 30) ? 5 : (($recencyDays <= 60) ? 4 : (($recencyDays <= 90) ? 3 : (($recencyDays <= 180) ? 2 : 1)));
            $fScore = ($frequency >= 10) ? 5 : (($frequency >= 5) ? 4 : (($frequency >= 3) ? 3 : (($frequency >= 2) ? 2 : 1)));
            $mScore = ($monetary >= 1000) ? 5 : (($monetary >= 500) ? 4 : (($monetary >= 200) ? 3 : (($monetary >= 50) ? 2 : 1)));

            $rfmScore = "{$rScore}{$fScore}{$mScore}";

            if ($rScore >= 4 && $fScore >= 4 && $mScore >= 4) {
                $segment = 'CHAMPION';
            } elseif ($rScore >= 3 && $fScore >= 3) {
                $segment = 'LOYAL_CUSTOMER';
            } elseif ($rScore >= 4 && $fScore == 1) {
                $segment = 'NEW_CUSTOMER';
            } elseif ($rScore <= 2 && $fScore >= 3) {
                $segment = 'AT_RISK';
            } elseif ($rScore == 1) {
                $segment = 'HIBERNATING';
            } else {
                $segment = 'POTENTIAL_LOYALIST';
            }
        } else {
            throw new \InvalidArgumentException("Unsupported RFM Policy Version {$policyVersion}");
        }

        return [
            'status' => 'RFM_CALCULATED',
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'metrics' => [
                'recency_days' => $recencyDays,
                'frequency_count' => $frequency,
                'monetary_total' => number_format($monetary, 2, '.', ''),
            ],
            'rfm_score' => $rfmScore,
            'rfm_segment' => $segment,
            'governance' => [
                'rfm_policy_version' => $policyVersion,
                'no_ai_inference' => true,
                'gate_id' => 'RFM-POLICY-PROVENANCE-GATE-01',
                'calculated_at' => now()->toIso8601String(),
            ]
        ];
    }
}
