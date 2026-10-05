<?php

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyClosingCryptographicEngineService
{
    protected FinancialAllocationTruthEngineService $truthEngine;

    public function __construct(FinancialAllocationTruthEngineService $truthEngine)
    {
        $this->truthEngine = $truthEngine;
    }

    /**
     * Generate or recalculate an immutable cryptographic daily closing for a specific tenant and date.
     * Consumes ONLY FinancialAllocationTruthEngineService (Single Source of Financial Truth).
     */
    public function generateDailyClosing(
        int $tenantId,
        string $closingDate,
        float $declaredCash = 0.00,
        string $closedBy = 'SYSTEM',
        ?string $correlationId = null
    ): array {
        $correlationId = $correlationId ?? (string) Str::uuid();

        // 1. Consume Financial Truth Engine (Zero parallel formula calculation)
        $financialTruth = $this->truthEngine->calculateFinancialMetrics($tenantId, $closingDate, $closingDate);
        $summary = $financialTruth['financial_summary'];
        $orderCounts = $financialTruth['order_counts'];

        $grossSales = (float) $summary['gross_sales'];
        $discounts = (float) $summary['discounts'];
        $netSales = (float) $summary['net_sales'];
        $paidSales = (float) $summary['paid_sales'];
        $pendingSales = (float) $summary['pending_sales'];
        $refundsTotal = (float) $summary['refunds'];
        $netCollected = (float) $summary['net_collected'];

        // 2. Compute Cash Balances
        // Expected Cash = Cash portion of allocations (or net collected for cash orders)
        $expectedCash = $netCollected; 
        $cashDifference = round($declaredCash - $expectedCash, 2);

        // 3. Fetch Tenant-Isolated Previous Closing Hash
        $prevClosing = DB::table('daily_closings')
            ->where('tenant_id', $tenantId)
            ->where('closing_date', '<', $closingDate)
            ->orderByDesc('closing_date')
            ->first();

        $previousClosingHash = $prevClosing ? $prevClosing->closing_hash : str_repeat('0', 64);

        // 4. Construct Deterministic SHA-256 Payload & Hash
        $payloadToHash = json_encode([
            'tenant_id' => $tenantId,
            'closing_date' => $closingDate,
            'gross_sales' => number_format($grossSales, 2, '.', ''),
            'discounts' => number_format($discounts, 2, '.', ''),
            'net_sales' => number_format($netSales, 2, '.', ''),
            'paid_sales' => number_format($paidSales, 2, '.', ''),
            'pending_sales' => number_format($pendingSales, 2, '.', ''),
            'refunds_total' => number_format($refundsTotal, 2, '.', ''),
            'expected_cash' => number_format($expectedCash, 2, '.', ''),
            'declared_cash' => number_format($declaredCash, 2, '.', ''),
            'cash_difference' => number_format($cashDifference, 2, '.', ''),
            'orders_count' => $orderCounts['total_orders'],
            'cancelled_orders_count' => $orderCounts['cancelled_orders'],
            'previous_closing_hash' => $previousClosingHash,
            'correlation_id' => $correlationId,
            'closed_by' => $closedBy,
        ]);

        $closingHash = hash('sha256', $payloadToHash);

        // 5. Store / Update in daily_closings Table
        $existing = DB::table('daily_closings')
            ->where('tenant_id', $tenantId)
            ->where('closing_date', $closingDate)
            ->first();

        $uuid = $existing ? $existing->uuid : (string) Str::uuid();

        DB::table('daily_closings')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'closing_date' => $closingDate,
            ],
            [
                'uuid' => $uuid,
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'net_sales' => $netSales,
                'paid_sales' => $paidSales,
                'pending_sales' => $pendingSales,
                'refunds_total' => $refundsTotal,
                'expected_cash' => $expectedCash,
                'declared_cash' => $declaredCash,
                'cash_difference' => $cashDifference,
                'orders_count' => $orderCounts['total_orders'],
                'cancelled_orders_count' => $orderCounts['cancelled_orders'],
                'previous_closing_hash' => $previousClosingHash,
                'closing_hash' => $closingHash,
                'correlation_id' => $correlationId,
                'closed_by' => $closedBy,
                'closed_at' => now(),
                'updated_at' => now(),
                'created_at' => $existing ? $existing->created_at : now(),
            ]
        );

        return [
            'status' => 'DAILY_CLOSING_GENERATED',
            'tenant_id' => $tenantId,
            'closing_date' => $closingDate,
            'uuid' => $uuid,
            'financial_snapshot' => [
                'gross_sales' => number_format($grossSales, 2, '.', ''),
                'discounts' => number_format($discounts, 2, '.', ''),
                'net_sales' => number_format($netSales, 2, '.', ''),
                'paid_sales' => number_format($paidSales, 2, '.', ''),
                'pending_sales' => number_format($pendingSales, 2, '.', ''),
                'refunds_total' => number_format($refundsTotal, 2, '.', ''),
                'expected_cash' => number_format($expectedCash, 2, '.', ''),
                'declared_cash' => number_format($declaredCash, 2, '.', ''),
                'cash_difference' => number_format($cashDifference, 2, '.', ''),
            ],
            'cryptography' => [
                'previous_closing_hash' => $previousClosingHash,
                'closing_hash' => $closingHash,
                'tenant_chain_isolated' => true,
                'algorithm' => 'SHA-256',
            ],
            'governance' => [
                'single_financial_engine_consumed' => true,
                'gate_id' => 'DAILY-CLOSING-CHAIN-GATE-01',
                'rule' => 'Daily closing consumed FinancialAllocationTruthEngineService directly with zero parallel formulas.',
            ]
        ];
    }

    /**
     * Record an audited post-closing adjustment.
     */
    public function recordAdjustment(
        int $dailyClosingId,
        int $tenantId,
        float $adjustmentAmount,
        string $reason,
        string $adjustedBy,
        ?string $approvedBy = null
    ): array {
        $closing = DB::table('daily_closings')
            ->where('id', $dailyClosingId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$closing) {
            throw new \InvalidArgumentException("Daily closing ID {$dailyClosingId} not found for tenant {$tenantId}.");
        }

        $adjustmentHash = hash('sha256', json_encode([
            'daily_closing_id' => $dailyClosingId,
            'tenant_id' => $tenantId,
            'adjustment_amount' => number_format($adjustmentAmount, 2, '.', ''),
            'reason' => $reason,
            'adjusted_by' => $adjustedBy,
            'approved_by' => $approvedBy,
            'timestamp' => now()->toIso8601String(),
        ]));

        $adjustmentId = DB::table('daily_closing_adjustments')->insertGetId([
            'daily_closing_id' => $dailyClosingId,
            'tenant_id' => $tenantId,
            'adjustment_amount' => $adjustmentAmount,
            'reason' => $reason,
            'adjusted_by' => $adjustedBy,
            'approved_by' => $approvedBy,
            'adjustment_hash' => $adjustmentHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'status' => 'ADJUSTMENT_RECORDED',
            'adjustment_id' => $adjustmentId,
            'daily_closing_id' => $dailyClosingId,
            'tenant_id' => $tenantId,
            'adjustment_amount' => number_format($adjustmentAmount, 2, '.', ''),
            'reason' => $reason,
            'adjustment_hash' => $adjustmentHash,
        ];
    }

    /**
     * CLOSING-IMMUTABILITY-GATE-02: Verify the cryptographic integrity of a stored daily closing record.
     * Detects any unauthorized direct database mutations or tampering.
     */
    public function verifyClosingIntegrity(int $dailyClosingId): array
    {
        $closing = DB::table('daily_closings')->where('id', $dailyClosingId)->first();

        if (!$closing) {
            return [
                'status' => 'RECORD_NOT_FOUND',
                'integrity_valid' => false,
                'daily_closing_id' => $dailyClosingId,
            ];
        }

        $payloadToHash = json_encode([
            'tenant_id' => (int) $closing->tenant_id,
            'closing_date' => $closing->closing_date,
            'gross_sales' => number_format((float) $closing->gross_sales, 2, '.', ''),
            'discounts' => number_format((float) $closing->discounts, 2, '.', ''),
            'net_sales' => number_format((float) $closing->net_sales, 2, '.', ''),
            'paid_sales' => number_format((float) $closing->paid_sales, 2, '.', ''),
            'pending_sales' => number_format((float) $closing->pending_sales, 2, '.', ''),
            'refunds_total' => number_format((float) $closing->refunds_total, 2, '.', ''),
            'expected_cash' => number_format((float) $closing->expected_cash, 2, '.', ''),
            'declared_cash' => number_format((float) $closing->declared_cash, 2, '.', ''),
            'cash_difference' => number_format((float) $closing->cash_difference, 2, '.', ''),
            'orders_count' => (int) $closing->orders_count,
            'cancelled_orders_count' => (int) $closing->cancelled_orders_count,
            'previous_closing_hash' => $closing->previous_closing_hash,
            'correlation_id' => $closing->correlation_id,
            'closed_by' => $closing->closed_by,
        ]);

        $recalculatedHash = hash('sha256', $payloadToHash);
        $storedHash = $closing->closing_hash;

        $isValid = hash_equals($storedHash, $recalculatedHash);

        return [
            'status' => $isValid ? 'INTEGRITY_VERIFIED' : 'TAMPERING_DETECTED',
            'integrity_valid' => $isValid,
            'gate_id' => 'CLOSING-IMMUTABILITY-GATE-02',
            'daily_closing_id' => $dailyClosingId,
            'tenant_id' => (int) $closing->tenant_id,
            'closing_date' => $closing->closing_date,
            'stored_hash' => $storedHash,
            'recalculated_hash' => $recalculatedHash,
            'tampering_detected' => !$isValid,
        ];
    }
}
