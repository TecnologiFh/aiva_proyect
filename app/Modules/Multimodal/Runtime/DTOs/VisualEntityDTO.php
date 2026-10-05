<?php

namespace App\Modules\Multimodal\Runtime\DTOs;

/**
 * Representa una entidad visual comercial estructurada (ej: plato, sección, repuesto, prenda, etc.).
 */
class VisualEntityDTO
{
    /**
     * @param string $id Identificador único de la entidad visual
     * @param string $type Tipo de entidad (MENU_SECTION, PRODUCT_ITEM, CATALOG_ITEM, etc.)
     * @param string $name Nombre o etiqueta observada (ej: "Pechuga", "Parrillas", "Kit Carenado CB190R")
     * @param string $normalizedName Nombre normalizado para búsqueda y resolución determinista
     * @param string|null $observedPrice Precio observado como string decimal (ej: "25.00")
     * @param float $confidence Confianza de extracción
     * @param array<string, float>|null $regionBox Coordenadas espaciales [x, y, width, height]
     * @param int|null $displayIndex Índice ordinal en la carta/catálogo (1-based)
     * @param int|null $page Número de página
     * @param array<string, mixed> $attributes Atributos observados (color, talla, compatibilidad, etc.)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $normalizedName,
        public readonly ?string $observedPrice = null,
        public readonly float $confidence = 1.0,
        public readonly ?array $regionBox = null,
        public readonly ?int $displayIndex = null,
        public readonly ?int $page = null,
        public readonly array $attributes = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'normalized_name' => $this->normalizedName,
            'observed_price' => $this->observedPrice,
            'confidence' => $this->confidence,
            'region_box' => $this->regionBox,
            'display_index' => $this->displayIndex,
            'page' => $this->page,
            'attributes' => $this->attributes,
        ];
    }
}
