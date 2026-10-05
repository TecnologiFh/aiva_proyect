<?php

namespace App\Modules\AI\DTOs;

final class CatalogAmbiguityResolutionDTO
{
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';
    public const STATUS_NOT_FOUND = 'NOT_FOUND';

    public function __construct(
        public readonly string $status, // RESOLVED, AMBIGUOUS, NOT_FOUND (RF-18, RF-19)
        public readonly ?string $entityType = null, // menu, product
        public readonly ?int $entityId = null,
        public readonly ?string $entityName = null,
        public readonly ?float $price = null,
        public readonly array $candidates = [], // Lista de candidatos si es AMBIGUOUS
        public readonly ?string $reason = null,
        public readonly float $confidence = 1.0,
        public readonly array $additionalData = []
    ) {}

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === self::STATUS_AMBIGUOUS;
    }

    public function isNotFound(): bool
    {
        return $this->status === self::STATUS_NOT_FOUND;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'commercial_substitution_allowed') {
            return $this->additionalData['commercial_substitution_allowed'] ?? false;
        }
        if ($name === 'options') {
            return $this->candidates;
        }
        return $this->additionalData[$name] ?? null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'entity_name' => $this->entityName,
            'price' => $this->price,
            'candidates' => $this->candidates,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'additional_data' => $this->additionalData,
        ];
    }
}
