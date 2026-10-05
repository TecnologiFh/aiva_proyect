<?php

namespace App\Modules\AI\DTOs;

final class MenuGroupDTO
{
    public function __construct(
        public readonly string $id, // UUID propio inmutable (RF-27)
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $normalizedName,
        public readonly float $price = 0.00,
        public readonly string $currency = 'PEN',
        public readonly ?string $description = null,
        public readonly ?int $sourceEvidenceId = null,
        public readonly float $confidence = 1.0,
        public readonly string $status = 'DRAFT',
        public readonly array $items = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'price' => $this->price,
            'currency' => $this->currency,
            'description' => $this->description,
            'source_evidence_id' => $this->sourceEvidenceId,
            'confidence' => $this->confidence,
            'status' => $this->status,
            'items' => $this->items,
        ];
    }
}
