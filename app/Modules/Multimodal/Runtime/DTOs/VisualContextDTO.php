<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

/**
 * Contexto visual persistente para resolución conversacional posterior.
 */
class VisualContextDTO
{
    /**
     * @param int $tenantId
     * @param string $conversationId
     * @param string $mediaId
     * @param string $assetHash
     * @param string $documentRole Rol del documento (MENU, PAYMENT, INVOICE, PRODUCT_IMAGE)
     * @param string|null $activeSection Sección visual activa (ej: "PARRILLAS")
     * @param int|null $page Página visual
     * @param int|null $catalogVersionId Versión de catálogo activa al momento de la percepción
     * @param array<string, mixed> $metadata Metadatos de contexto
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $conversationId,
        public readonly string $mediaId,
        public readonly string $assetHash,
        public readonly string $documentRole,
        public readonly ?string $activeSection = null,
        public readonly ?int $page = null,
        public readonly ?int $catalogVersionId = null,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'conversation_id' => $this->conversationId,
            'media_id' => $this->mediaId,
            'asset_hash' => $this->assetHash,
            'document_role' => $this->documentRole,
            'active_section' => $this->activeSection,
            'page' => $this->page,
            'catalog_version_id' => $this->catalogVersionId,
            'metadata' => $this->metadata,
        ];
    }
}
