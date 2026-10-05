<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

/**
 * Representa una relación estructural o semántica entre entidades visuales.
 */
class VisualRelationDTO
{
    /**
     * @param string $sourceEntityId ID de la entidad origen (ej: "pechuga_01")
     * @param string $targetEntityId ID de la entidad destino (ej: "section_parrillas")
     * @param string $relationType Tipo de relación (belongs_to, contains, represents, has_variant, etc.)
     * @param float $confidence Confianza de la relación
     * @param array<string, mixed> $metadata Metadatos de la relación
     */
    public function __construct(
        public readonly string $sourceEntityId,
        public readonly string $targetEntityId,
        public readonly string $relationType,
        public readonly float $confidence = 1.0,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'source_entity_id' => $this->sourceEntityId,
            'target_entity_id' => $this->targetEntityId,
            'relation_type' => $this->relationType,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }
}
