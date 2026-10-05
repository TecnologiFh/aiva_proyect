<?php

namespace App\Modules\Orders\Services;

use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\Product;
use App\Modules\Orders\DTOs\TechnicalResolutionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TechnicalCompatibilityResolverService
{
    /**
     * Caché en memoria por versión publicada para evitar queries redundantes en el mismo ciclo.
     */
    protected static array $publishedEntitiesCache = [];

    public static function clearEntitiesCache(): void
    {
        self::$publishedEntitiesCache = [];
    }

    /**
     * C-G2: Descubre dinámicamente las marcas, modelos y especificaciones técnicas
     * desde el Catálogo Canónico Publicado (canonical_catalog_items) para la versión activa.
     */
    public function getPublishedTechnicalEntities(int $tenantId, int $catalogVersionId): array
    {
        $cacheKey = "{$tenantId}_{$catalogVersionId}";
        if (isset(self::$publishedEntitiesCache[$cacheKey])) {
            return self::$publishedEntitiesCache[$cacheKey];
        }

        $canonicalItems = \Illuminate\Support\Facades\DB::table('canonical_catalog_items')
            ->where('tenant_id', $tenantId)
            ->where('catalog_version_id', $catalogVersionId)
            ->where('status', 'ACTIVE')
            ->get(['compatibility_json', 'attributes_json']);

        $brands = [];
        $models = [];
        $modelBrandMap = [];
        $presentations = [];
        $colors = [];
        $customSpecs = [];

        foreach ($canonicalItems as $item) {
            $compat = is_array($item->compatibility_json) ? $item->compatibility_json : json_decode($item->compatibility_json ?? '[]', true);
            $attrs = is_array($item->attributes_json) ? $item->attributes_json : json_decode($item->attributes_json ?? '[]', true);

            $brandName = !empty($compat['brand']) ? trim($compat['brand']) : null;
            $modelName = !empty($compat['model']) ? trim($compat['model']) : null;

            if ($brandName) {
                $bNorm = Str::of($brandName)->lower()->ascii()->squish()->toString();
                $brands[$bNorm] = $brandName;
            }

            if ($modelName) {
                $mNorm = Str::of($modelName)->lower()->ascii()->squish()->toString();
                $models[$mNorm] = $modelName;
                if ($brandName) {
                    $modelBrandMap[$mNorm] = $brandName;
                }

                // Indexar sub-tokens para lenguaje natural (ej. "Pulsar NS200" -> "pulsar")
                $parts = preg_split('/[\s\-_]+/', $mNorm);
                if (count($parts) > 1) {
                    $baseToken = $parts[0];
                    if (strlen($baseToken) >= 3 && !preg_match('/^[0-9]+$/', $baseToken)) {
                        if (!isset($models[$baseToken])) {
                            $models[$baseToken] = $modelName;
                            if ($brandName) {
                                $modelBrandMap[$baseToken] = $brandName;
                            }
                        }
                    }
                }
            }

            if (!empty($attrs['presentation'])) {
                $p = trim($attrs['presentation']);
                $pNorm = Str::of($p)->lower()->ascii()->squish()->toString();
                $presentations[$pNorm] = $p;
            }

            if (!empty($attrs['color'])) {
                $c = trim($attrs['color']);
                $cNorm = Str::of($c)->lower()->ascii()->squish()->toString();
                $colors[$cNorm] = $c;
            }

            $specs = $attrs['custom_specs'] ?? ($attrs['customSpecs'] ?? []);
            if (is_array($specs)) {
                foreach ($specs as $k => $v) {
                    $vStr = trim((string)$v);
                    if (!empty($vStr)) {
                        $vNorm = Str::of($vStr)->lower()->ascii()->squish()->toString();
                        $customSpecs[$vNorm] = [
                            'key' => $k,
                            'value' => $vStr,
                        ];
                    }
                }
            }
        }

        // Authority: Technical identity and entities are discovered exclusively from canonical_catalog_items

        uksort($models, fn($a, $b) => strlen($b) <=> strlen($a));
        uksort($brands, fn($a, $b) => strlen($b) <=> strlen($a));

        self::$publishedEntitiesCache[$cacheKey] = [
            'brands' => $brands,
            'models' => $models,
            'model_brand_map' => $modelBrandMap,
            'presentations' => $presentations,
            'colors' => $colors,
            'custom_specs' => $customSpecs,
        ];

        return self::$publishedEntitiesCache[$cacheKey];
    }

    /**
     * E-C5: Verifica si el tenant posee capacidad técnica estricta.
     * Se activa ÚNICAMENTE si la versión publicada cuenta con evidencia de compatibilidad estructurada.
     * Atributos aislados (ej. color en un restaurante) NO activan el resolver técnico.
     */
    public function hasTechnicalCapability(int $tenantId, ?int $catalogVersionId = null): bool
    {
        $versionId = $catalogVersionId ?? $this->resolveActivePublishedVersionId($tenantId);
        if ($versionId === null) {
            return false;
        }

        return Product::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $versionId)
            ->where('is_available', true)
            ->whereNotNull('compatibility')
            ->where(function ($q) {
                $q->whereNotNull('compatibility->brand')
                  ->orWhereNotNull('compatibility->model')
                  ->orWhereNotNull('compatibility->year_raw');
            })
            ->exists();
    }

    /**
     * E-C11: Secuencia de resolución previa obligatoria:
     * tenant_id -> resolve ACTIVE + PUBLISHED catalog_version -> obtain catalog_version_id.
     */
    public function resolveActivePublishedVersionId(int $tenantId): ?int
    {
        $activeVersion = CatalogVersion::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', CatalogVersion::STATUS_PUBLISHED)
            ->first();

        return $activeVersion?->id;
    }

    /**
     * Pipeline formal de 7 etapas para resolución determinista y arbitraje comercial.
     */
    public function resolve(int $tenantId, string $customerQuery, array $context = []): TechnicalResolutionResult
    {
        // 1. E-C11: Obtener catalog_version_id activa publicada de forma estricta
        $catalogVersionId = $context['catalog_version_id'] ?? $this->resolveActivePublishedVersionId($tenantId);
        if ($catalogVersionId === null) {
            return new TechnicalResolutionResult(
                status: TechnicalResolutionResult::STATUS_NO_MATCH,
                rawQuery: $customerQuery,
                normalizedEntities: [],
                evaluatedCandidatesCount: 0,
                ambiguityReason: "No existe una versión de catálogo activa y publicada para este negocio.",
                catalogVersionId: null
            );
        }

        // 2. Extracción de Entidades Técnicas y normalización canónica
        $entities = $this->extractTechnicalEntities($customerQuery, $context, $tenantId, $catalogVersionId);

        // 3. Obtener candidatos disponibles filtrados estrictamente por tenant_id y catalog_version_id activa
        $query = Product::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $catalogVersionId)
            ->where('is_available', true);

        // P3.14-02 & P3.14-05: Si hay candidate_scope activo y no hubo cambio de modelo ni marca
        $modelChanged = !empty($entities['model']) && !empty($context['model']) && $entities['model'] !== $context['model'];
        $brandChanged = !empty($entities['brand']) && !empty($context['brand']) && $entities['brand'] !== $context['brand'];
        if (!empty($context['candidate_scope']) && is_array($context['candidate_scope']) && !$modelChanged && !$brandChanged) {
            $scopedIds = collect($context['candidate_scope'])->pluck('id')->filter()->toArray();
            if (!empty($scopedIds)) {
                $query->whereIn('id', $scopedIds);
            }
        }

        // Si se pasa un conjunto preordenado o filtrado en tests de determinismo
        if (!empty($context['products_override'])) {
            $products = collect($context['products_override']);
        } else {
            $products = $query->get();
        }

        $evaluatedCount = 0;
        $compatibleCandidates = [];
        $unknownCandidates = [];
        $rejectedCandidates = [];

        foreach ($products as $prod) {
            $evaluatedCount++;
            $compat = is_array($prod->compatibility) ? $prod->compatibility : json_decode($prod->compatibility ?? '[]', true);
            $attrs = is_array($prod->attributes) ? $prod->attributes : json_decode($prod->attributes ?? '[]', true);

            $eval = $this->evaluateProductCompatibility($prod, $compat, $attrs, $entities);

            if ($eval['status'] === 'COMPATIBLE') {
                $compatibleCandidates[] = $this->formatCandidate($prod, $compat, $attrs);
            } elseif ($eval['status'] === 'UNKNOWN') {
                $unknownCandidates[] = $this->formatCandidate($prod, $compat, $attrs);
            } else {
                $rejectedCandidates[] = [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'name' => $prod->name,
                    'reason' => $eval['reason'] ?? 'INCOMPATIBLE',
                    'details' => $eval['details'] ?? [],
                ];
            }
        }

        // 4. Arbitraje de Candidatos (Regla E-C1: COMPATIBLE siempre vence a UNKNOWN)
        $compatibleCount = count($compatibleCandidates);

        // Caso 1: Exactamente 1 candidato compatible confirmado
        if ($compatibleCount === 1) {
            return new TechnicalResolutionResult(
                status: TechnicalResolutionResult::STATUS_RESOLVED,
                rawQuery: $customerQuery,
                normalizedEntities: $entities,
                compatibleCandidates: $compatibleCandidates,
                rejectedCandidates: $rejectedCandidates,
                evaluatedCandidatesCount: $evaluatedCount,
                authority: 'MYSQL_CANONICAL',
                catalogVersionId: $catalogVersionId
            );
        }

        // Caso 2: Múltiples candidatos compatibles confirmados (> 1)
        if ($compatibleCount > 1) {
            $disambig = $this->buildDisambiguationPrompt($compatibleCandidates, $entities);
            return new TechnicalResolutionResult(
                status: TechnicalResolutionResult::STATUS_AMBIGUOUS,
                rawQuery: $customerQuery,
                normalizedEntities: $entities,
                compatibleCandidates: $compatibleCandidates,
                rejectedCandidates: $rejectedCandidates,
                evaluatedCandidatesCount: $evaluatedCount,
                ambiguityReason: $disambig['reason'],
                requiredDisambiguation: $disambig['prompt'],
                authority: 'MYSQL_CANONICAL',
                catalogVersionId: $catalogVersionId
            );
        }

        // Caso 3: 0 compatibles confirmados, pero existen candidatos UNKNOWN (año N/D)
        if (!empty($unknownCandidates)) {
            $unknownNames = collect($unknownCandidates)->pluck('name')->unique()->implode(', ');
            return new TechnicalResolutionResult(
                status: TechnicalResolutionResult::STATUS_UNCONFIRMED_COMPATIBILITY,
                rawQuery: $customerQuery,
                normalizedEntities: $entities,
                compatibleCandidates: [],
                rejectedCandidates: $rejectedCandidates,
                evaluatedCandidatesCount: $evaluatedCount,
                ambiguityReason: "No puedo confirmar la compatibilidad porque el catálogo no especifica el año para ese modelo.",
                requiredDisambiguation: "¿Podrías confirmar las especificaciones físicas o número de parte de tu {$entities['model']}?",
                authority: 'MYSQL_CANONICAL',
                catalogVersionId: $catalogVersionId
            );
        }

        // Caso 4: 0 compatibles y 0 desconocidos -> NO_MATCH absoluto con trazabilidad forense
        $primaryReason = !empty($rejectedCandidates) ? $rejectedCandidates[0]['reason'] : 'NO_CANDIDATES_FOUND';
        return new TechnicalResolutionResult(
            status: TechnicalResolutionResult::STATUS_NO_MATCH,
            rawQuery: $customerQuery,
            normalizedEntities: $entities,
            compatibleCandidates: [],
            rejectedCandidates: $rejectedCandidates,
            evaluatedCandidatesCount: $evaluatedCount,
            ambiguityReason: $primaryReason,
            authority: 'MYSQL_CANONICAL',
            catalogVersionId: $catalogVersionId
        );
    }

    /**
     * Evalúa determinísticamente si un producto coincide con las entidades de la consulta.
     */
    protected function evaluateProductCompatibility($prod, array $compat, array $attrs, array $entities): array
    {
        $prodBrand = $this->normalizeText($compat['brand'] ?? '');
        $prodModel = $this->normalizeText($compat['model'] ?? '');
        $isUniversal = (bool)($compat['universal'] ?? false);
        $compatStatus = $compat['status'] ?? 'SPECIFIC';

        // 1. Identity Match: Marca y Modelo (No permisivo, anti-like laxo)
        // Regla E-C3: UNIVERSAL es estrictamente temporal (año). Jamás sustituye la identidad de marca o modelo.
        if (!empty($entities['brand'])) {
            $queryBrand = $this->normalizeText($entities['brand']);
            if ($prodBrand !== $queryBrand) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'BRAND_MISMATCH', 'details' => ['expected' => $queryBrand, 'actual' => $prodBrand]];
            }
        }

        if (!empty($entities['model'])) {
            $queryModel = $this->normalizeText($entities['model']);
            if (!$this->isModelMatch($prodModel, $queryModel)) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'MODEL_MISMATCH', 'details' => ['expected' => $queryModel, 'actual' => $prodModel]];
            }
        }

        // 2. Presentation Match (ej. Lateral, Frontal, Kit completo)
        if (!empty($entities['presentation'])) {
            $queryPres = $this->normalizeText($entities['presentation']);
            $prodPres = $this->normalizeText($attrs['presentation'] ?? $prod->name);
            if (!$this->isPresentationMatch($prodPres, $queryPres)) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'PRESENTATION_MISMATCH', 'details' => ['expected' => $queryPres, 'actual' => $prodPres]];
            }
        }

        // 2.1 Style Match (ej. deportivo vs urbano en nombre o atributos)
        if (!empty($entities['style'])) {
            $qStyle = $this->normalizeText($entities['style']);
            $prodNameNorm = $this->normalizeText($prod->name ?? '');
            $prodStyleNorm = $this->normalizeText($attrs['style'] ?? ($attrs['custom_specs']['style'] ?? ''));
            if (!str_contains($prodNameNorm, $qStyle) && !str_contains($prodStyleNorm, $qStyle)) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'STYLE_MISMATCH', 'details' => ['expected' => $qStyle, 'actual' => $prodNameNorm]];
            }
        }

        // 3. E-C12: Attribute Match con Precedencia Explícita
        // Lado (Side)
        if (!empty($entities['side'])) {
            $querySide = strtoupper($entities['side']);
            $prodSide = strtoupper($attrs['side'] ?? '');
            if (!empty($prodSide) && $prodSide !== 'BOTH' && $prodSide !== $querySide) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'ATTRIBUTE_MISMATCH', 'details' => ['attribute' => 'side', 'expected' => $querySide, 'actual' => $prodSide]];
            }
        }

        // Color
        if (!empty($entities['color'])) {
            $queryColor = $this->normalizeText($entities['color']);
            $prodColor = $this->normalizeText($attrs['color'] ?? '');
            if (!empty($prodColor) && !str_contains($prodColor, $queryColor)) {
                return ['status' => 'INCOMPATIBLE', 'reason' => 'ATTRIBUTE_MISMATCH', 'details' => ['attribute' => 'color', 'expected' => $queryColor, 'actual' => $prodColor]];
            }
        }

        // 4. Compatibility Evaluation: Año
        $yStart = $compat['year_start'] ?? null;
        $yEnd = $compat['year_end'] ?? null;
        $yRaw = trim((string)($compat['year_raw'] ?? ''));
        $isYearUnspecified = ($compatStatus === 'INCOMPLETE' || $yRaw === 'N/D' || ($yStart === null && $yEnd === null));

        if (!empty($entities['year'])) {
            $queryYear = (int)$entities['year'];

            // E-C3: Universal temporal
            if (!$isUniversal) {
                // Si el producto no tiene año especificado en catálogo (UNKNOWN / INCOMPLETE)
                if ($isYearUnspecified) {
                    return ['status' => 'UNKNOWN', 'reason' => 'YEAR_UNSPECIFIED_IN_CATALOG'];
                }

                // Verificación de rango
                $minYear = $yStart !== null ? (int)$yStart : 1900;
                $maxYear = $yEnd !== null ? (int)$yEnd : 2099;

                if ($queryYear < $minYear || $queryYear > $maxYear) {
                    return [
                        'status' => 'INCOMPATIBLE',
                        'reason' => 'YEAR_OUT_OF_RANGE',
                        'details' => ['query_year' => $queryYear, 'range' => "{$minYear}-{$maxYear}"],
                    ];
                }
            }
        } else {
            // Si el cliente no indicó año:
            // Si el producto no especifica año en catálogo y no es universal, su compatibilidad es desconocida (UNKNOWN ≠ UNIVERSAL)
            if ($isYearUnspecified && !$isUniversal) {
                return ['status' => 'UNKNOWN', 'reason' => 'YEAR_REQUIRED_UNKNOWN_IN_CATALOG'];
            }
        }

        // 5. Custom Specs Match (Universalidad E2E para atributos dinámicos como voltaje, potencia, etc.)
        if (!empty($entities['custom_specs']) && is_array($entities['custom_specs'])) {
            $prodSpecs = $attrs['custom_specs'] ?? ($attrs['customSpecs'] ?? []);
            foreach ($entities['custom_specs'] as $specKey => $reqVal) {
                $actualVal = $prodSpecs[$specKey] ?? null;
                if ($actualVal !== null && mb_strtolower(trim((string)$actualVal)) !== mb_strtolower(trim((string)$reqVal))) {
                    return [
                        'status' => 'INCOMPATIBLE',
                        'reason' => 'CUSTOM_SPEC_MISMATCH',
                        'details' => ['spec' => $specKey, 'expected' => $reqVal, 'actual' => $actualVal]
                    ];
                }
            }
        }

        return ['status' => 'COMPATIBLE'];
    }

    /**
     * Extrae entidades dinámicas a partir de un arreglo en memoria de productos (usado en tests unitarios con products_override)
     */
    protected function extractEntitiesFromProductOverrides(array $products): array
    {
        $brands = [];
        $models = [];
        $modelBrandMap = [];
        $presentations = [];
        $colors = [];
        $customSpecs = [];

        foreach ($products as $p) {
            $compat = is_array($p->compatibility ?? null) ? $p->compatibility : json_decode($p->compatibility ?? '[]', true);
            $attrs = is_array($p->attributes ?? null) ? $p->attributes : json_decode($p->attributes ?? '[]', true);

            $b = $compat['brand'] ?? null;
            $m = $compat['model'] ?? null;

            if ($b) {
                $bNorm = Str::of($b)->lower()->ascii()->squish()->toString();
                $brands[$bNorm] = $b;
            }
            if ($m) {
                $mNorm = Str::of($m)->lower()->ascii()->squish()->toString();
                $models[$mNorm] = $m;
                if ($b) {
                    $modelBrandMap[$mNorm] = $b;
                }

                $parts = preg_split('/[\s\-_]+/', $mNorm);
                if (count($parts) > 1) {
                    $baseToken = $parts[0];
                    if (strlen($baseToken) >= 3 && !preg_match('/^[0-9]+$/', $baseToken)) {
                        if (!isset($models[$baseToken])) {
                            $models[$baseToken] = $m;
                            if ($b) {
                                $modelBrandMap[$baseToken] = $b;
                            }
                        }
                    }
                }
            }
            if (!empty($attrs['presentation'])) {
                $pNorm = Str::of($attrs['presentation'])->lower()->ascii()->squish()->toString();
                $presentations[$pNorm] = $attrs['presentation'];
            }
            if (!empty($attrs['color'])) {
                $cNorm = Str::of($attrs['color'])->lower()->ascii()->squish()->toString();
                $colors[$cNorm] = $attrs['color'];
            }
            $specs = $attrs['custom_specs'] ?? ($attrs['customSpecs'] ?? []);
            if (is_array($specs)) {
                foreach ($specs as $k => $v) {
                    $vStr = trim((string)$v);
                    if (!empty($vStr)) {
                        $vNorm = Str::of($vStr)->lower()->ascii()->squish()->toString();
                        $customSpecs[$vNorm] = [
                            'key' => $k,
                            'value' => $vStr,
                        ];
                    }
                }
            }
        }

        uksort($models, fn($a, $b) => strlen($b) <=> strlen($a));
        uksort($brands, fn($a, $b) => strlen($b) <=> strlen($a));

        return [
            'brands' => $brands,
            'models' => $models,
            'model_brand_map' => $modelBrandMap,
            'presentations' => $presentations,
            'colors' => $colors,
            'custom_specs' => $customSpecs,
        ];
    }

    /**
     * Extracción no permisiva de entidades técnicas.
     * C-G2: Descubre marcas y modelos dinámicamente desde el Catálogo Canónico Publicado.
     * E-C12: Precedencia absoluta de atributos explícitos del usuario en el turno actual.
     */
    public function extractTechnicalEntities(string $query, array $context = [], ?int $tenantId = null, ?int $catalogVersionId = null): array
    {
        $norm = Str::of($query)->lower()->ascii()->trim()->squish()->toString();

        $published = ($tenantId !== null && $catalogVersionId !== null)
            ? $this->getPublishedTechnicalEntities($tenantId, $catalogVersionId)
            : [];

        if (empty($published['models']) && !empty($context['products_override'])) {
            $published = $this->extractEntitiesFromProductOverrides($context['products_override']);
        }

        $knownBrands = $published['brands'] ?? [];
        $knownModels = $published['models'] ?? [];
        $modelBrandMap = $published['model_brand_map'] ?? [];

        // 1. Marca dinámica desde catálogo publicado
        $brand = null;
        foreach ($knownBrands as $bNorm => $canonicalBrand) {
            if (preg_match('/\b' . preg_quote($bNorm, '/') . '\b/i', $norm)) {
                $brand = $canonicalBrand;
                break;
            }
        }

        // 2. Modelo dinámico desde catálogo publicado
        $model = null;
        $modelMatchedKey = null;
        foreach ($knownModels as $mNorm => $canonicalModel) {
            $pattern = preg_quote($mNorm, '/');
            $pattern = str_replace('\ ', '\s*', $pattern);
            $pattern = str_replace('\.', '\.?', $pattern);
            if (preg_match('/\b' . $pattern . '\b/i', $norm)) {
                $model = $canonicalModel;
                $modelMatchedKey = $mNorm;
                break;
            }
        }

        // Si se reconoció un modelo y tiene una marca asociada en catálogo:
        if ($model !== null && $modelMatchedKey !== null && isset($modelBrandMap[$modelMatchedKey])) {
            $modelBrand = $modelBrandMap[$modelMatchedKey];
            // Si no había marca o la marca de la consulta entra en conflicto con la marca exclusiva del modelo en catálogo (ej. "honda pulsar"),
            // la marca auténtica del modelo en catálogo prevalece sobre la confusión coloquial del usuario.
            if ($brand === null || strcasecmp($brand, $modelBrand) !== 0) {
                $brand = $modelBrand;
            }
        }

        // 3. Año (4 dígitos de 1980 a 2040)
        $year = null;
        if (preg_match('/\b(19[89][0-9]|20[0-3][0-9]|2040)\b/', $query, $yMatches)) {
            $year = (int)$yMatches[1];
        }

        // 4. Lado y Posición (Universal: RIGHT, LEFT, FRONT, REAR, BOTH)
        $side = null;
        if (preg_match('/\b(derecho|derecha|der|right)\b/i', $norm)) {
            $side = 'RIGHT';
        } elseif (preg_match('/\b(izquierdo|izquierda|izq|left)\b/i', $norm)) {
            $side = 'LEFT';
        } elseif (preg_match('/\b(ambos|ambas|par|juego|both)\b/i', $norm)) {
            $side = 'BOTH';
        } elseif (preg_match('/\b(delantero|delantera|delanteras|delanteros|frontal|front)\b/i', $norm)) {
            $side = 'FRONT';
        } elseif (preg_match('/\b(trasero|trasera|traseras|traseros|posterior|colin|rear)\b/i', $norm)) {
            $side = 'REAR';
        }

        // 5. Presentación (Universal)
        $presentation = null;
        if (!empty($published['presentations'])) {
            foreach ($published['presentations'] as $pNorm => $canonPres) {
                if (preg_match('/\b' . preg_quote($pNorm, '/') . '\b/i', $norm)) {
                    $presentation = $canonPres;
                    break;
                }
            }
        }
        if ($presentation === null) {
            if (preg_match('/\b(kit completo|kit deportivo|kit urbano|juego completo|completo|completa|todo completo|el completo|la completa|carenado completo|carenado deportivo|carenado urbano)\b/i', $norm)) {
                $presentation = 'Kit completo';
            } elseif (preg_match('/\b(lateral|laterales)\b/i', $norm)) {
                $presentation = 'Lateral';
            } elseif (preg_match('/\b(frontal superior)\b/i', $norm)) {
                $presentation = 'Frontal superior';
            } elseif (preg_match('/\b(frontal completo|frontal)\b/i', $norm)) {
                $presentation = 'Frontal';
            } elseif (preg_match('/\b(colin|colin trasero)\b/i', $norm)) {
                $presentation = 'Colín';
            } elseif (preg_match('/\b(parabrisas|cupula)\b/i', $norm)) {
                $presentation = 'Parabrisas';
            } elseif (preg_match('/\b(grapas|kit grapas)\b/i', $norm)) {
                $presentation = 'Kit grapas';
            } elseif (preg_match('/\b(montaje|kit montaje)\b/i', $norm)) {
                $presentation = 'Kit montaje';
            } elseif (preg_match('/\b(pastilla|pastillas|pastillas de freno)\b/i', $norm)) {
                $presentation = 'Pastillas';
            } elseif (preg_match('/\b(disco|discos|disco de freno)\b/i', $norm)) {
                $presentation = 'Disco';
            } elseif (preg_match('/\b(compresor|compresores)\b/i', $norm)) {
                $presentation = 'Compresor';
            }
        }

        // 5.1 Estilo dinámico (ej. Deportivo vs Urbano)
        $style = null;
        if (preg_match('/\b(deportivo|deportiva|sport)\b/i', $norm)) {
            $style = 'deportivo';
        } elseif (preg_match('/\b(urbano|urbana|city)\b/i', $norm)) {
            $style = 'urbano';
        }

        // 6. Color (Universal)
        $color = null;
        if (!empty($published['colors'])) {
            foreach ($published['colors'] as $cNorm => $canonColor) {
                if (preg_match('/\b' . preg_quote($cNorm, '/') . '\b/i', $norm)) {
                    $color = $canonColor;
                    break;
                }
            }
        }
        if ($color === null) {
            if (preg_match('/\b(negro mate)\b/i', $norm)) {
                $color = 'Negro mate';
            } elseif (preg_match('/\b(negro|negra)\b/i', $norm)) {
                $color = 'Negro';
            } elseif (preg_match('/\b(rojo|roja)\b/i', $norm)) {
                $color = 'Rojo';
            } elseif (preg_match('/\b(verde)\b/i', $norm)) {
                $color = 'Verde';
            } elseif (preg_match('/\b(ahumado)\b/i', $norm)) {
                $color = 'Ahumado';
            } elseif (preg_match('/\b(blanco|blanca)\b/i', $norm)) {
                $color = 'Blanco';
            } elseif (preg_match('/\b(azul)\b/i', $norm)) {
                $color = 'Azul';
            }
        }

        // 7. Custom Specs dinámicas (ej. 220V, 380V)
        $matchedCustomSpecs = [];
        if (!empty($published['custom_specs'])) {
            foreach ($published['custom_specs'] as $specValNorm => $specMeta) {
                if (preg_match('/\b' . preg_quote($specValNorm, '/') . '\b/i', $norm)) {
                    $matchedCustomSpecs[$specMeta['key']] = $specMeta['value'];
                }
            }
        }

        // E-C4 & E-C12: Integración con contexto anterior sin resurrección:
        $inheritedBrand = $brand ?? ($context['brand'] ?? null);
        $inheritedModel = $model ?? ($context['model'] ?? null);
        $inheritedPresentation = $presentation ?? ($context['presentation'] ?? null);
        $inheritedStyle = $style ?? ($context['style'] ?? null);

        // Si cambia el modelo o la marca, se limpian lado, año y color anteriores
        $modelChanged = !empty($model) && !empty($context['model']) && $model !== $context['model'];
        if ($modelChanged) {
            $inheritedSide = $side;
            $inheritedYear = $year;
            $inheritedColor = $color;
        } else {
            $inheritedSide = $side ?? ($context['side'] ?? null);
            $inheritedYear = $year ?? ($context['year'] ?? null);
            $inheritedColor = $color ?? ($context['color'] ?? null);
        }

        return [
            'brand' => $inheritedBrand,
            'model' => $inheritedModel,
            'year' => $inheritedYear,
            'side' => $inheritedSide,
            'presentation' => $inheritedPresentation,
            'style' => $inheritedStyle,
            'color' => $inheritedColor,
            'custom_specs' => $matchedCustomSpecs,
            'explicit_in_query' => [
                'brand' => $brand !== null,
                'model' => $model !== null,
                'year' => $year !== null,
                'side' => $side !== null,
                'presentation' => $presentation !== null,
                'style' => $style !== null,
                'color' => $color !== null,
                'custom_specs' => !empty($matchedCustomSpecs),
            ],
        ];
    }

    /**
     * Formatea un candidato asegurando la autoridad canónica de precio en MySQL (E-C8).
     */
    protected function formatCandidate($prod, array $compat, array $attrs): array
    {
        return [
            'id' => $prod->id,
            'sku' => $prod->sku,
            'name' => $prod->name,
            'base_price' => (string)$prod->base_price, // E-C8: Decimal exacto como string, nunca float
            'price_status' => $prod->price_status ?? 'CONFIRMED',
            'stock' => $prod->stock,
            'stock_status' => $prod->stock_status ?? 'UNKNOWN',
            'compatibility' => $compat,
            'attributes' => $attrs,
        ];
    }

    protected function buildDisambiguationPrompt(array $candidates, array $entities): array
    {
        $modelsInCandidates = collect($candidates)->pluck('compatibility.model')->filter()->unique();
        if (empty($entities['model']) && $modelsInCandidates->count() > 1) {
            $brandLabel = !empty($entities['brand']) ? " de {$entities['brand']}" : "";
            $modelList = $modelsInCandidates->implode(' o ');
            return [
                'reason' => 'MODEL_UNSPECIFIED',
                'prompt' => "¿Para qué modelo{$brandLabel} lo necesitas ({$modelList})?",
            ];
        }

        $sides = collect($candidates)->pluck('attributes.side')->filter()->unique();
        $colors = collect($candidates)->pluck('attributes.color')->filter()->unique();
        $years = collect($candidates)->pluck('compatibility.year_raw')->filter()->unique();

        $sideLabels = [
            'LEFT' => 'Izquierdo',
            'RIGHT' => 'Derecho',
            'FRONT' => 'Delantero',
            'REAR' => 'Trasero',
            'BOTH' => 'Ambos lados',
        ];

        $candsText = collect($candidates)->map(function ($c) use ($sideLabels) {
            $side = $c['attributes']['side'] ?? null;
            $sideLabel = $sideLabels[$side] ?? $side;
            $sideStr = "";
            if (!empty($sideLabel) && !str_contains(mb_strtolower($c['name']), mb_strtolower($sideLabel))) {
                $sideStr = " ({$sideLabel})";
            }
            $color = $c['attributes']['color'] ?? null;
            $colorStr = "";
            if (!empty($color) && !str_contains(mb_strtolower($c['name']), mb_strtolower($color))) {
                $colorStr = " [{$color}]";
            }
            $priceStr = number_format((float)$c['base_price'], 2);
            return "{$c['name']}{$sideStr}{$colorStr} - S/ {$priceStr}";
        })->implode('; ');

        if (empty($entities['side']) && $sides->count() > 1) {
            $hasFrontRear = $sides->contains('FRONT') || $sides->contains('REAR');
            $hasLeftRight = $sides->contains('LEFT') || $sides->contains('RIGHT');

            if ($hasFrontRear && !$hasLeftRight) {
                $promptText = "¿Para qué posición lo necesitas (delanteras o traseras)? Opciones disponibles: {$candsText}.";
            } elseif ($hasLeftRight && !$hasFrontRear) {
                $promptText = "¿Para qué lado lo necesitas (izquierdo o derecho)? Opciones disponibles: {$candsText}.";
            } else {
                $promptText = "¿Para qué posición o lado lo necesitas? Opciones disponibles: {$candsText}.";
            }

            return [
                'reason' => 'SIDE_UNSPECIFIED',
                'prompt' => $promptText,
            ];
        }

        if (empty($entities['color']) && $colors->count() > 1) {
            $colorOptions = $colors->implode(' o ');
            return [
                'reason' => 'COLOR_UNSPECIFIED',
                'prompt' => "¿Qué color o acabado prefieres ({$colorOptions})? Opciones disponibles: {$candsText}.",
            ];
        }

        if (empty($entities['year']) && $years->count() > 1) {
            return [
                'reason' => 'YEAR_UNSPECIFIED',
                'prompt' => "¿De qué año es tu moto para confirmar la versión compatible? Opciones disponibles: {$candsText}.",
            ];
        }

        return [
            'reason' => 'MULTIPLE_CANDIDATES',
            'prompt' => "Tenemos varias opciones compatibles: {$candsText}. ¿Cuál de ellas prefieres?",
        ];
    }

    protected function normalizeText(string $text): string
    {
        return Str::of($text)->lower()->ascii()->trim()->squish()->toString();
    }

    protected function isModelMatch(string $prodModel, string $queryModel): bool
    {
        if ($prodModel === $queryModel) {
            return true;
        }

        // Normalizaciones específicas sin sobre-aproximación
        $cleanProd = preg_replace('/[^a-z0-9]/', '', $prodModel);
        $cleanQuery = preg_replace('/[^a-z0-9]/', '', $queryModel);

        return $cleanProd === $cleanQuery;
    }

    protected function isPresentationMatch(string $prodPres, string $queryPres): bool
    {
        if ($prodPres === $queryPres || str_contains($prodPres, $queryPres) || str_contains($queryPres, $prodPres)) {
            return true;
        }

        if ($queryPres === 'lateral' && (str_contains($prodPres, 'lateral') || str_contains($prodPres, 'carenado lateral'))) {
            return true;
        }

        if ($queryPres === 'kit completo' && (str_contains($prodPres, 'kit completo') || str_contains($prodPres, 'completo'))) {
            return true;
        }

        return false;
    }
}
