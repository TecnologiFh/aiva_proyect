<?php

namespace App\Modules\Payments\Domain\ValueObjects;

class ValidationResult
{
    public function __construct(
        public readonly string $evidenceId,
        public readonly int $tenantId,
        public readonly int $orderId,
        public readonly string $overallStatus, // 'VALIDATED' | 'VALIDATION_FAILED'
        public readonly string $reconciliationState, // 'EXACT' | 'PARTIAL' | 'OVERPAYMENT'
        public readonly ?string $primaryReasonCode,
        public readonly array $ruleResults = [],
        public readonly array $metadata = []
    ) {}

    public function isPassed(): bool
    {
        return $this->overallStatus === 'VALIDATED';
    }

    public function toArray(): array
    {
        return [
            'evidence_id' => $this->evidenceId,
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
            'overall_status' => $this->overallStatus,
            'reconciliation_state' => $this->reconciliationState,
            'primary_reason_code' => $this->primaryReasonCode,
            'rule_results' => $this->ruleResults,
            'metadata' => $this->metadata,
        ];
    }
}
