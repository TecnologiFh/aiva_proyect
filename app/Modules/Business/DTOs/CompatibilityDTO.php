<?php

namespace App\Modules\Business\DTOs;

class CompatibilityDTO
{
    public function __construct(
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly ?int $yearStart = null,
        public readonly ?int $yearEnd = null,
        public readonly ?string $yearRaw = null,
        public readonly bool $universal = false
    ) {}

    public const STATUS_UNIVERSAL = 'UNIVERSAL';
    public const STATUS_SPECIFIC = 'SPECIFIC';
    public const STATUS_INCOMPLETE = 'INCOMPLETE';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    public function getCompatibilityStatus(): string
    {
        if ($this->universal) {
            return self::STATUS_UNIVERSAL;
        }
        if ($this->brand === null && $this->model === null && $this->yearStart === null && $this->yearEnd === null) {
            return self::STATUS_UNKNOWN;
        }
        // D6: Marca/modelo presente pero año no especificado -> INCOMPLETE
        if (($this->brand !== null || $this->model !== null) && ($this->yearStart === null && $this->yearEnd === null)) {
            return self::STATUS_INCOMPLETE;
        }
        return self::STATUS_SPECIFIC;
    }

    /**
     * Retorna true si es compatible, false si es incompatible, o null si es DESCONOCIDO (UNKNOWN ≠ UNIVERSAL).
     */
    public function isCompatibleWithYear(?int $year): ?bool
    {
        if ($this->universal) {
            return true;
        }
        if ($year === null) {
            return true;
        }
        // Si no tiene rango de años y no es universal, es UNKNOWN (no se asume compatibilidad)
        if ($this->yearStart === null && $this->yearEnd === null) {
            return null;
        }
        if ($this->yearStart !== null && $year < $this->yearStart) {
            return false;
        }
        if ($this->yearEnd !== null && $year > $this->yearEnd) {
            return false;
        }
        return true;
    }

    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'model' => $this->model,
            'year_start' => $this->yearStart,
            'year_end' => $this->yearEnd,
            'year_raw' => $this->yearRaw,
            'universal' => $this->universal,
            'status' => $this->getCompatibilityStatus(),
        ];
    }
}
