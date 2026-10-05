<?php

namespace App\Modules\AI\DTOs;

final class MenuGuardrailResultDTO
{
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_PARTIAL_REVIEW = 'PARTIAL_REVIEW';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    /**
     * @param VisionMenuCategoryDTO[] $acceptedCategories
     * @param array[] $rejectedItems
     * @param string[] $reasons
     */
    public function __construct(
        public readonly string $status,
        public readonly float $globalConfidence = 0.0,
        public readonly array $acceptedCategories = [],
        public readonly array $rejectedItems = [],
        public readonly array $reasons = []
    ) {}

    public function isIngestable(): bool
    {
        return ($this->status === self::STATUS_ACCEPTED || $this->status === self::STATUS_PARTIAL_REVIEW) 
            && !empty($this->acceptedCategories);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'global_confidence' => $this->globalConfidence,
            'accepted_categories' => array_map(fn(VisionMenuCategoryDTO $c) => $c->toArray(), $this->acceptedCategories),
            'rejected_items' => $this->rejectedItems,
            'reasons' => $this->reasons,
        ];
    }
}
