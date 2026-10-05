<?php

namespace App\Modules\Orders\DTOs;

final class TechnicalResolutionResult
{
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';
    public const STATUS_NO_MATCH = 'NO_MATCH';
    public const STATUS_UNCONFIRMED_COMPATIBILITY = 'UNCONFIRMED_COMPATIBILITY';

    public function __construct(
        public readonly string $status,
        public readonly string $rawQuery,
        public readonly array $normalizedEntities,
        public readonly array $compatibleCandidates = [],
        public readonly array $rejectedCandidates = [],
        public readonly int $evaluatedCandidatesCount = 0,
        public readonly ?string $ambiguityReason = null,
        public readonly ?string $requiredDisambiguation = null,
        public readonly string $authority = 'MYSQL_CANONICAL',
        public readonly ?int $catalogVersionId = null,
        public readonly string $resolutionTrace = 'AG46-E'
    ) {}

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === self::STATUS_AMBIGUOUS;
    }

    public function isNoMatch(): bool
    {
        return $this->status === self::STATUS_NO_MATCH;
    }

    public function isUnconfirmedCompatibility(): bool
    {
        return $this->status === self::STATUS_UNCONFIRMED_COMPATIBILITY;
    }

    public function getResolvedCandidate(): ?array
    {
        if ($this->isResolved() && !empty($this->compatibleCandidates)) {
            return $this->compatibleCandidates[0];
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'raw_query' => $this->rawQuery,
            'normalized_entities' => $this->normalizedEntities,
            'compatible_candidates' => $this->compatibleCandidates,
            'rejected_candidates' => $this->rejectedCandidates,
            'evaluated_candidates_count' => $this->evaluatedCandidatesCount,
            'ambiguity_reason' => $this->ambiguityReason,
            'required_disambiguation' => $this->requiredDisambiguation,
            'authority' => $this->authority,
            'catalog_version_id' => $this->catalogVersionId,
            'resolution_trace' => $this->resolutionTrace,
        ];
    }
}
