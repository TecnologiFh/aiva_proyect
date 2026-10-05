<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\DTOs\CanonicalProductDTO;
use App\Modules\Business\DTOs\CompatibilityDTO;
use App\Modules\Business\DTOs\CoreProductDTO;
use App\Modules\Business\DTOs\InventoryDTO;
use App\Modules\Business\DTOs\ProductAttributesDTO;
use App\Modules\Business\DTOs\ProvenanceDTO;
use Illuminate\Support\Str;

class CanonicalValueNormalizerService
{
    /**
     * Normaliza un rango o valor de año preservando siempre el valor original (RAW).
     *
     * Invariante C1: UNKNOWN compatibility ≠ UNIVERSAL
     * Invariante C2: year_raw preservado
     */
    public function normalizeYearRange(?string $rawYear): array
    {
        if ($rawYear === null || trim($rawYear) === '' || strtoupper(trim($rawYear)) === 'N/D') {
            return [
                'year_raw' => $rawYear !== null && trim($rawYear) !== '' ? trim($rawYear) : null,
                'year_start' => null,
                'year_end' => null,
                'universal' => false,
            ];
        }

        $trimmed = trim($rawYear);
        $upper = strtoupper($trimmed);

        // Caso Universal explícito
        if (str_contains($upper, 'UNIVERSAL') || str_contains($upper, 'TODAS') || str_contains($upper, 'CUALQUIERA')) {
            return [
                'year_raw' => $trimmed,
                'year_start' => null,
                'year_end' => null,
                'universal' => true,
            ];
        }

        // Caso "2018+" o "2018 a más" o "2018 en adelante"
        if (preg_match('/^(19|20\d{2})\s*(\+|\s*a\s*m[aá]s|\s*en\s*adelante)$/i', $trimmed, $m)) {
            return [
                'year_raw' => $trimmed,
                'year_start' => (int)$m[1],
                'year_end' => null,
                'universal' => false,
            ];
        }

        // Caso rango "2016-2020", "2016 - 2020", "2016/2020"
        if (preg_match('/^(19\d{2}|20\d{2})\s*[-–\/]\s*(19\d{2}|20\d{2})$/', $trimmed, $m)) {
            $yStart = (int)$m[1];
            $yEnd = (int)$m[2];
            return [
                'year_raw' => $trimmed,
                'year_start' => min($yStart, $yEnd),
                'year_end' => max($yStart, $yEnd),
                'universal' => false,
            ];
        }

        // Caso lista discreta de años: "2018 / 2019 / 2020" o "2018, 2019, 2020"
        if (preg_match_all('/\b(19\d{2}|20\d{2})\b/', $trimmed, $m)) {
            $years = array_map('intval', $m[1]);
            if (count($years) === 1) {
                return [
                    'year_raw' => $trimmed,
                    'year_start' => $years[0],
                    'year_end' => $years[0],
                    'universal' => false,
                ];
            }
            if (count($years) > 1) {
                return [
                    'year_raw' => $trimmed,
                    'year_start' => min($years),
                    'year_end' => max($years),
                    'universal' => false,
                ];
            }
        }

        // Caso año único exacto "2018"
        if (preg_match('/^(19\d{2}|20\d{2})$/', $trimmed, $m)) {
            $year = (int)$m[1];
            return [
                'year_raw' => $trimmed,
                'year_start' => $year,
                'year_end' => $year,
                'universal' => false,
            ];
        }

        // Si no se puede parsear numéricamente pero hay texto, se preserva RAW y compatibilidad es UNKNOWN
        return [
            'year_raw' => $trimmed,
            'year_start' => null,
            'year_end' => null,
            'universal' => false,
        ];
    }

    /**
     * Normaliza presentación y lado, desacoplando presentation de side.
     *
     * Ej: "Kit completo" -> presentation="Kit completo", side=null
     * Ej: "Lateral derecho" -> presentation="Lateral", side="RIGHT"
     * Ej: "Lado derecho" -> presentation="Lado derecho", side="RIGHT"
     */
    public function normalizePresentationAndSide(?string $rawPresentation, ?string $rawSide = null): array
    {
        $presTrimmed = $rawPresentation !== null ? trim($rawPresentation) : null;
        $sideTrimmed = $rawSide !== null ? trim($rawSide) : null;

        $side = null;
        $presentation = $presTrimmed;

        // 1. Si viene columna de lado explícita
        if ($sideTrimmed !== null && $sideTrimmed !== '') {
            $side = $this->parseSideValue($sideTrimmed);
        }

        // 2. Si side aún no se ha determinado, inferirlo de la presentación
        if ($side === null && $presTrimmed !== null) {
            $presLower = mb_strtolower($presTrimmed);

            if (str_contains($presLower, 'lateral derecho') || str_contains($presLower, 'lado derecho') || str_contains($presLower, 'tapa derecha')) {
                $side = 'RIGHT';
                $presentation = trim(str_ireplace(['derecho', 'derecha'], '', $presTrimmed));
            } elseif (str_contains($presLower, 'lateral izquierdo') || str_contains($presLower, 'lado izquierdo') || str_contains($presLower, 'tapa izquierda')) {
                $side = 'LEFT';
                $presentation = trim(str_ireplace(['izquierdo', 'izquierda'], '', $presTrimmed));
            } elseif (str_contains($presLower, 'frontal') || str_contains($presLower, 'delantero')) {
                $side = 'FRONT';
                $presentation = $presTrimmed;
            } elseif (str_contains($presLower, 'posterior') || str_contains($presLower, 'trasero') || str_contains($presLower, 'colin') || str_contains($presLower, 'colín')) {
                $side = 'REAR';
                $presentation = $presTrimmed;
            } elseif (str_contains($presLower, 'ambos') || str_contains($presLower, 'par') || str_contains($presLower, 'juego')) {
                $side = 'BOTH';
                $presentation = $presTrimmed;
            } elseif (str_contains($presLower, 'kit') || str_contains($presLower, 'completo')) {
                // "Kit completo" no tiene lado
                $side = null;
                $presentation = $presTrimmed;
            }
        }

        return [
            'presentation_raw' => $presTrimmed,
            'side_raw' => $sideTrimmed,
            'presentation' => !empty($presentation) ? $presentation : $presTrimmed,
            'side' => $side,
        ];
    }

    /**
     * Mapea un valor textual a enum canónico de lado
     */
    protected function parseSideValue(string $val): ?string
    {
        $v = strtoupper(trim($val));
        if ($v === 'DERECHO' || $v === 'DERECHA' || $v === 'DER' || $v === 'RH' || $v === 'R' || str_contains($v, 'DERECH')) {
            return 'RIGHT';
        }
        if ($v === 'IZQUIERDO' || $v === 'IZQUIERDA' || $v === 'IZQ' || $v === 'LH' || $v === 'L' || str_contains($v, 'IZQUIERD')) {
            return 'LEFT';
        }
        if ($v === 'FRONTAL' || $v === 'DELANTERO' || $v === 'FRONT' || $v === 'F') {
            return 'FRONT';
        }
        if ($v === 'POSTERIOR' || $v === 'TRASERO' || $v === 'REAR') {
            return 'REAR';
        }
        if ($v === 'AMBOS' || $v === 'PAR' || $v === 'JUEGO' || $v === 'BOTH') {
            return 'BOTH';
        }
        return null;
    }

    /**
     * Invariante C4: FLOAT prohibido para dinero; usar DECIMAL exacto con 2 decimales
     * Retorna string formateado "280.00" o null si no hay precio válido
     */
    public function normalizeMoney(?string $rawPrice): ?string
    {
        $res = $this->normalizePriceWithStatus($rawPrice);
        return $res['price'];
    }

    /**
     * D5: Invariante de Precio Ambiguo
     * Retorna [price, price_status, status]
     */
    public function normalizePriceWithStatus(?string $rawPrice): array
    {
        if ($rawPrice === null || trim($rawPrice) === '' || strtoupper(trim($rawPrice)) === 'N/D') {
            return [
                'price' => null,
                'price_status' => CoreProductDTO::PRICE_REVIEW_REQUIRED,
                'status' => 'REQUIRES_REVIEW',
            ];
        }

        $trimmed = trim($rawPrice);
        $upper = strtoupper($trimmed);

        // Textos ambiguos comunes ("Consultar", "Desde S/ 250", etc.)
        if (str_contains($upper, 'CONSULTAR') || str_contains($upper, 'DESDE') || str_contains($upper, 'COTIZAR') || str_contains($upper, 'PEDIR')) {
            return [
                'price' => null,
                'price_status' => CoreProductDTO::PRICE_REVIEW_REQUIRED,
                'status' => 'REQUIRES_REVIEW',
            ];
        }

        // Limpiar símbolos de moneda y espacios
        $cleaned = preg_replace('/[S\/\$€\s]/i', '', $trimmed);
        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $cleaned = str_replace(',', '', $cleaned);
        } elseif (str_contains($cleaned, ',') && !str_contains($cleaned, '.')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        if (is_numeric($cleaned)) {
            $num = (float)$cleaned;
            return [
                'price' => number_format($num, 2, '.', ''),
                'price_status' => CoreProductDTO::PRICE_CONFIRMED,
                'status' => 'ACTIVE',
            ];
        }

        return [
            'price' => null,
            'price_status' => CoreProductDTO::PRICE_REVIEW_REQUIRED,
            'status' => 'REQUIRES_REVIEW',
        ];
    }

    /**
     * Normaliza el inventario respetando stock=NULL cuando no hay evidencia
     */
    public function normalizeInventory(?string $rawStock): InventoryDTO
    {
        return InventoryDTO::fromRaw($rawStock);
    }

    /**
     * Transforma una fila sin procesar al agregador canónico inmutable CanonicalProductDTO
     */
    public function normalizeRowToCanonicalDTO(array $rawRow, array $columnMappings, ProvenanceDTO $provenance): CanonicalProductDTO
    {
        // 1. Extraer valores por target canónico
        $colByTarget = array_flip($columnMappings);

        $getVal = function(string $target) use ($rawRow, $colByTarget): ?string {
            $colName = $colByTarget[$target] ?? null;
            if ($colName === null) {
                return null;
            }
            $val = $rawRow[$colName] ?? null;
            return $val !== null ? trim((string)$val) : null;
        };

        // Core
        $rawName = $getVal('core.name') ?? 'Producto sin nombre';
        $rawSku = $getVal('core.sku');
        $rawCat = $getVal('core.category') ?? 'General';
        $rawDesc = $getVal('core.description');
        $rawPrice = $getVal('core.base_price');
        $defaultStatus = $getVal('core.status') ?? 'ACTIVE';

        $priceData = $this->normalizePriceWithStatus($rawPrice);
        $statusUpper = strtoupper(trim((string)$defaultStatus));
        $normalizedStatus = match ($statusUpper) {
            'ACTIVO', 'ACTIVE', 'DISPONIBLE' => 'ACTIVE',
            'INACTIVO', 'INACTIVE', 'DESACTIVADO' => 'INACTIVE',
            'AGOTADO', 'OUT_OF_STOCK', 'SIN_STOCK' => 'OUT_OF_STOCK',
            default => 'ACTIVE',
        };
        $finalStatus = ($priceData['status'] === 'REQUIRES_REVIEW') ? 'REQUIRES_REVIEW' : $normalizedStatus;

        $core = new CoreProductDTO(
            name: $rawName,
            sku: !empty($rawSku) ? $rawSku : null,
            category: !empty($rawCat) ? $rawCat : 'General',
            description: !empty($rawDesc) ? $rawDesc : null,
            basePrice: $priceData['price'],
            priceStatus: $priceData['price_status'],
            status: $finalStatus
        );

        // Compatibility
        $rawBrand = $getVal('compatibility.brand');
        $rawModel = $getVal('compatibility.model');
        $rawYear = $getVal('compatibility.year_raw');

        $yearNorm = $this->normalizeYearRange($rawYear);

        $compatibility = new CompatibilityDTO(
            brand: !empty($rawBrand) ? $rawBrand : null,
            model: !empty($rawModel) ? $rawModel : null,
            yearStart: $yearNorm['year_start'],
            yearEnd: $yearNorm['year_end'],
            yearRaw: $yearNorm['year_raw'],
            universal: $yearNorm['universal']
        );

        // Attributes
        $rawPresentation = $getVal('attributes.presentation_raw');
        $rawSide = $getVal('attributes.side_raw');
        $rawMaterial = $getVal('attributes.material');
        $rawColor = $getVal('attributes.color');

        $presSideNorm = $this->normalizePresentationAndSide($rawPresentation, $rawSide);

        $customSpecs = [];
        foreach ($columnMappings as $colName => $target) {
            if ($target === 'attributes.custom_specs' || str_starts_with($target, 'attributes.custom_specs.')) {
                $rawVal = $rawRow[$colName] ?? null;
                if ($rawVal !== null && trim((string)$rawVal) !== '') {
                    $specKey = str_starts_with($target, 'attributes.custom_specs.')
                        ? substr($target, strlen('attributes.custom_specs.'))
                        : \Illuminate\Support\Str::snake(\Illuminate\Support\Str::ascii($colName));
                    $customSpecs[$specKey] = trim((string)$rawVal);
                }
            }
        }

        $attributes = new ProductAttributesDTO(
            presentation: $presSideNorm['presentation'],
            side: $presSideNorm['side'],
            material: !empty($rawMaterial) ? $rawMaterial : null,
            color: !empty($rawColor) ? $rawColor : null,
            customSpecs: $customSpecs
        );

        // Inventory
        $rawStock = $getVal('inventory.stock_raw');
        $inventory = $this->normalizeInventory($rawStock);

        return new CanonicalProductDTO(
            core: $core,
            compatibility: $compatibility,
            attributes: $attributes,
            inventory: $inventory,
            provenance: $provenance
        );
    }
}
