<?php

namespace App\Modules\AI\DTOs;

final class RawMenuEvidenceDTO
{
    public function __construct(
        public readonly string $documentId,
        public readonly int $tenantId,
        public readonly string $extractedText,
        public readonly string $type = 'text', // text, heading, price, group
        public readonly array $bbox = ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 0],
        public readonly int $page = 1,
        public readonly ?string $region = null,
        public readonly float $confidence = 1.0,
        public readonly int $sourceOrder = 0,
        public readonly string $sourceType = 'WHATSAPP_IMAGE',
        public readonly ?string $sourceReference = null
    ) {}

    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'tenant_id' => $this->tenantId,
            'extracted_text' => $this->extractedText,
            'type' => $this->type,
            'bbox' => $this->bbox,
            'page' => $this->page,
            'region' => $this->region,
            'confidence' => $this->confidence,
            'source_order' => $this->sourceOrder,
            'source_type' => $this->sourceType,
            'source_reference' => $this->sourceReference,
        ];
    }
}
