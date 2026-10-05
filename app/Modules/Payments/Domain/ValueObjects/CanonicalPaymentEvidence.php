<?php

namespace App\Modules\Payments\Domain\ValueObjects;

class CanonicalPaymentEvidence
{
    public function __construct(
        public readonly string $evidenceId,
        public readonly int $tenantId,
        public readonly string $provider,
        public readonly string $documentType,
        public readonly ?float $amount,
        public readonly string $currency,
        public readonly ?string $operationId,
        public readonly ?string $transactionDate,
        public readonly ?string $transactionTime,
        public readonly ?string $senderName,
        public readonly ?string $recipientName,
        public readonly ?string $recipientPhone,
        public readonly string $sha256,
        public readonly ?string $phash,
        public readonly array $fieldConfidences = [],
        public readonly array $boundingBoxes = [],
        public readonly array $rawOcr = []
    ) {}

    public function toArray(): array
    {
        return [
            'evidence_id' => $this->evidenceId,
            'tenant_id' => $this->tenantId,
            'provider' => $this->provider,
            'document_type' => $this->documentType,
            'amount' => $this->amount !== null ? number_format($this->amount, 2, '.', '') : null,
            'currency' => $this->currency,
            'operation_id' => $this->operationId,
            'transaction_date' => $this->transactionDate,
            'transaction_time' => $this->transactionTime,
            'sender_name' => $this->senderName,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'sha256' => $this->sha256,
            'phash' => $this->phash,
            'field_confidences' => $this->fieldConfidences,
            'bounding_boxes' => $this->boundingBoxes,
        ];
    }
}
