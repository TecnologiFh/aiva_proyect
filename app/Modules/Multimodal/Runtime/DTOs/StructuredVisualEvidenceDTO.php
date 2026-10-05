<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;

/**
 * DTO Inmutable con Identidad Criptográfica y Trazabilidad Estricta (v2.3).
 * 
 * Regla Constitucional:
 * La imagen puede aportar evidencia.
 * La evidencia puede aportar candidatos.
 * Los candidatos pueden ser reconciliados.
 * Pero solamente el dominio autorizado puede convertir esa evidencia en una decisión comercial.
 */
class StructuredVisualEvidenceDTO
{
    /**
     * @param int $tenantId ID oficial y verificado del Tenant
     * @param string $conversationId Identificador canónico de conversación
     * @param string $messageId Identificador único del mensaje de origen (inbound WhatsApp)
     * @param string $mediaId Identificador del recurso de medios en el gateway
     * @param string $assetHash Hash físico criptográfico SHA-256 del binario de la imagen
     * @param string $assetClassification Clasificación primaria del recurso
     * @param array<string> $detectedEvidence Observaciones múltiples no excluyentes
     * @param string $perceptionVersion Versión del contrato de percepción
     * @param string $source Origen del recurso
     * @param array<string, mixed> $observations Metadatos, bounding boxes, textos OCR y observaciones
     * @param float $confidence Confianza global ponderada de la percepción (0.0 a 1.0)
     * @param string $createdAt Marca de tiempo ISO-8601
     * @param string|null $correlationId Identificador de correlación para trazabilidad transaccional
     * @param array<VisualEntityDTO> $entities Entidades visuales detectadas
     * @param array<VisualRelationDTO> $relations Relaciones entre entidades visuales
     * @param VisualContextDTO|null $context Contexto visual persistente
     * @param VisualEvidenceEnvelopeDTO|null $envelope Envelope canónico asociado
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $conversationId,
        public readonly string $messageId,
        public readonly string $mediaId,
        public readonly string $assetHash,
        public readonly string $assetClassification,
        public readonly array $detectedEvidence,
        public readonly string $perceptionVersion,
        public readonly string $source,
        public readonly array $observations,
        public readonly float $confidence,
        public readonly string $createdAt,
        public readonly ?string $correlationId = null,
        public readonly array $entities = [],
        public readonly array $relations = [],
        public readonly ?VisualContextDTO $context = null,
        public readonly ?VisualEvidenceEnvelopeDTO $envelope = null
    ) {
    }

    /**
     * Verifica si una observación específica está presente en la evidencia detectada.
     */
    public function hasEvidence(string|CommercialVisualEvidenceType $evidenceType): bool
    {
        $val = $evidenceType instanceof CommercialVisualEvidenceType ? $evidenceType->value : $evidenceType;
        return in_array($val, $this->detectedEvidence, true);
    }

    /**
     * Retorna el monto observado normalizado como string decimal (dos decimales) o null.
     * Erradica el uso de floats en dinero.
     */
    public function getObservedAmount(): ?string
    {
        $amt = $this->observations['amount'] ?? null;
        if ($amt === null || $amt === '') {
            return null;
        }

        if (is_numeric($amt)) {
            return number_format((float)$amt, 2, '.', '');
        }

        return null;
    }

    /**
     * Obtiene una entidad visual por su nombre normalizado o ID.
     */
    public function getEntity(string $nameOrId): ?VisualEntityDTO
    {
        $clean = strtolower(trim($nameOrId));
        foreach ($this->entities as $entity) {
            if (strtolower($entity->id) === $clean || strtolower($entity->normalizedName) === $clean || strtolower($entity->name) === $clean) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * Retorna todas las entidades que coincidan con un tipo (ej: PRODUCT_ITEM, MENU_SECTION).
     *
     * @return array<VisualEntityDTO>
     */
    public function getEntitiesByType(string $type): array
    {
        $matched = [];
        foreach ($this->entities as $entity) {
            if ($entity->type === $type) {
                $matched[] = $entity;
            }
        }
        return $matched;
    }

    /**
     * Serialización determinista a Array para trazabilidad, outbox y logs.
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'media_id' => $this->mediaId,
            'asset_hash' => $this->assetHash,
            'asset_classification' => $this->assetClassification,
            'detected_evidence' => $this->detectedEvidence,
            'perception_version' => $this->perceptionVersion,
            'source' => $this->source,
            'observations' => $this->observations,
            'confidence' => $this->confidence,
            'created_at' => $this->createdAt,
            'correlation_id' => $this->correlationId,
            'entities' => array_map(fn($e) => $e instanceof VisualEntityDTO ? $e->toArray() : $e, $this->entities),
            'relations' => array_map(fn($r) => $r instanceof VisualRelationDTO ? $r->toArray() : $r, $this->relations),
            'context' => $this->context?->toArray(),
        ];
    }
}
