<?php

namespace App\Modules\AI\DTOs;

final class MenuProductAssociationDTO
{
    public function __construct(
        public readonly ?int $productId,
        public readonly ?int $menuGroupId,
        public readonly string $productName,
        public readonly string $menuGroupName,
        public readonly string $offeringType = 'INCLUDED', // INCLUDED, SURCHARGE, STANDALONE (RF-28)
        public readonly float $surchargeAmount = 0.00,
        public readonly ?int $evidenceId = null, // Trazabilidad directa (RF-02)
        public readonly float $spatialScore = 0.0,
        public readonly float $semanticScore = 0.0,
        public readonly float $headingScore = 0.0,
        public readonly float $priceScore = 0.0,
        public readonly float $layoutScore = 0.0,
        public readonly float $globalConfidence = 0.0,
        public readonly string $associationStatus = 'CONFIRMED', // CONFIRMED, REVIEW_REQUIRED, AMBIGUOUS, REJECTED (RF-09)
        public readonly ?string $associationReason = null
    ) {}

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'menu_group_id' => $this->menuGroupId,
            'product_name' => $this->productName,
            'menu_group_name' => $this->menuGroupName,
            'offering_type' => $this->offeringType,
            'surcharge_amount' => $this->surchargeAmount,
            'evidence_id' => $this->evidenceId,
            'spatial_score' => $this->spatialScore,
            'semantic_score' => $this->semanticScore,
            'heading_score' => $this->headingScore,
            'price_score' => $this->priceScore,
            'layout_score' => $this->layoutScore,
            'global_confidence' => $this->globalConfidence,
            'association_status' => $this->associationStatus,
            'association_reason' => $this->associationReason,
        ];
    }
}
