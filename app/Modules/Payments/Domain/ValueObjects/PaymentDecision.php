<?php

namespace App\Modules\Payments\Domain\ValueObjects;

class PaymentDecision
{
    public function __construct(
        public readonly string $decision, // 'AUTO_AUTHORIZE' | 'HUMAN_REVIEW' | 'REJECT' | 'BLOCK'
        public readonly array $reasonCodes,
        public readonly string $riskLevel, // 'CLEAR' | 'SUSPICIOUS' | 'HIGH_RISK'
        public readonly string $validationStatus, // 'VALIDATED' | 'VALIDATION_FAILED'
        public readonly string $reconciliationStatus, // 'EXACT' | 'PARTIAL_PAYMENT' | 'OVERPAYMENT' | 'UNVERIFIED'
        public readonly string $provider,
        public readonly int $tenantId,
        public readonly string $evidenceId,
        public readonly ?int $orderId = null,
        public readonly string $policyVersion = '1.0',
        public readonly ?string $createdAt = null
    ) {
        if ($this->decision !== 'AUTO_AUTHORIZE' && empty($this->reasonCodes)) {
            throw new \InvalidArgumentException("Cualquier decisión distinta a AUTO_AUTHORIZE debe tener al menos un código de razón explicativo.");
        }
    }

    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'reason_codes' => $this->reasonCodes,
            'risk_level' => $this->riskLevel,
            'validation_status' => $this->validationStatus,
            'reconciliation_status' => $this->reconciliationStatus,
            'provider' => $this->provider,
            'tenant_id' => $this->tenantId,
            'evidence_id' => $this->evidenceId,
            'order_id' => $this->orderId,
            'policy_version' => $this->policyVersion,
            'created_at' => $this->createdAt ?? date('Y-m-d H:i:s'),
        ];
    }
}
