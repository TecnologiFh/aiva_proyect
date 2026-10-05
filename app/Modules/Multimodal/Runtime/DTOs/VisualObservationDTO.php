<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

/**
 * Representa una observación atómica percibida físicamente en el asset.
 * No es una decisión comercial.
 */
class VisualObservationDTO
{
    /**
     * @param string $field Nombre del campo observado (ej: amount, operation_number, product_name, ruc, etc.)
     * @param string|null $observedValue Valor textual o numérico exacto observado
     * @param float $confidence Nivel de confianza de la observación (0.0 a 1.0)
     * @param string $source Origen (ej: ocr, visual_perception, barcode, qrcode)
     * @param array<string, float>|null $regionBox Coordenadas espaciales [x, y, width, height]
     * @param array<string, mixed> $metadata Metadatos complementarios
     */
    public function __construct(
        public readonly string $field,
        public readonly ?string $observedValue,
        public readonly float $confidence = 1.0,
        public readonly string $source = 'visual_perception',
        public readonly ?array $regionBox = null,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'observed_value' => $this->observedValue,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'region_box' => $this->regionBox,
            'metadata' => $this->metadata,
        ];
    }
}
