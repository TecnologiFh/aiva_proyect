<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

/**
 * Envelope Canónico de Evidencia Visual Comercial (v2.3).
 * Garantiza identidad criptográfica, procedencia y trazabilidad estricta.
 */
class VisualEvidenceEnvelopeDTO
{
    /**
     * @param int $tenantId
     * @param string $conversationId
     * @param string $messageId
     * @param string $mediaId
     * @param string $assetHash Hash físico SHA-256 inmutable de la imagen
     * @param string $correlationId ID de trazabilidad
     * @param string $assetClassification Clasificación primaria
     * @param array<string> $detectedEvidence Observaciones múltiples no excluyentes
     * @param array<VisualObservationDTO> $observations
     * @param array<VisualEntityDTO> $entities
     * @param array<VisualRelationDTO> $relations
     * @param VisualContextDTO|null $context
     * @param string $source
     * @param string $perceptionVersion
     * @param string $createdAt
     * @param float $confidence
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $conversationId,
        public readonly string $messageId,
        public readonly string $mediaId,
        public readonly string $assetHash,
        public readonly string $correlationId,
        public readonly string $assetClassification,
        public readonly array $detectedEvidence,
        public readonly array $observations,
        public readonly array $entities,
        public readonly array $relations,
        public readonly ?VisualContextDTO $context,
        public readonly string $source,
        public readonly string $perceptionVersion,
        public readonly string $createdAt,
        public readonly float $confidence = 1.0
    ) {
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'media_id' => $this->mediaId,
            'asset_hash' => $this->assetHash,
            'correlation_id' => $this->correlationId,
            'asset_classification' => $this->assetClassification,
            'detected_evidence' => $this->detectedEvidence,
            'observations' => array_map(fn($o) => $o instanceof VisualObservationDTO ? $o->toArray() : $o, $this->observations),
            'entities' => array_map(fn($e) => $e instanceof VisualEntityDTO ? $e->toArray() : $e, $this->entities),
            'relations' => array_map(fn($r) => $r instanceof VisualRelationDTO ? $r->toArray() : $r, $this->relations),
            'context' => $this->context?->toArray(),
            'source' => $this->source,
            'perception_version' => $this->perceptionVersion,
            'created_at' => $this->createdAt,
            'confidence' => $this->confidence,
        ];
    }
}
