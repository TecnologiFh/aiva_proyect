<?php

namespace App\Modules\Payments\Domain\ValueObjects;

class FraudAssessment
{
    public function __construct(
        public readonly string $evidenceId,
        public readonly int $tenantId,
        public readonly int $riskScore,
        public readonly string $riskLevel, // 'CLEAR' | 'SUSPICIOUS' | 'HIGH_RISK'
        public readonly string $decision, // 'CLEAR' | 'SUSPICIOUS' | 'BLOCKED'
        public readonly bool $replayDetected,
        public readonly bool $visualSimilarityDetected,
        public readonly array $flags = [],
        public readonly array $reasonCodes = [],
        public readonly array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'evidence_id' => $this->evidenceId,
            'tenant_id' => $this->tenantId,
            'risk_score' => $this->riskScore,
            'risk_level' => $this->riskLevel,
            'decision' => $this->decision,
            'replay_detected' => $this->replayDetected,
            'visual_similarity_detected' => $this->visualSimilarityDetected,
            'flags' => $this->flags,
            'reason_codes' => $this->reasonCodes,
            'metadata' => $this->metadata,
        ];
    }
}
