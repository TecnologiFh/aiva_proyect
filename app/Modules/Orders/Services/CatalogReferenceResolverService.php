<?php

namespace App\Modules\Orders\Services;

use App\Modules\AI\DTOs\CatalogAmbiguityResolutionDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogReferenceResolverService
{
    /**
     * Alias de conveniencia para controladores y servicios externos.
     */
    public function resolveReference(int $tenantId, string $customerInput, array $context = []): CatalogAmbiguityResolutionDTO
    {
        return $this->resolveCustomerReference($tenantId, $customerInput, $context);
    }

    /**
     * Resuelve semánticamente las referencias del cliente ("el de 17", "el marino", "el común").
     * Reglas Constitucionales: RF-05, RF-13, RF-14, RF-15, RF-18, RF-19, AG-35, AG-46-F.
     */
    public function resolveCustomerReference(int $tenantId, string $customerInput, array $context = []): CatalogAmbiguityResolutionDTO
    {
        $inputTrimmed = trim($customerInput);
        // Normalizar a ASCII y limpiar prefijos conversacionales ("quiero una ", "¿cuánto cuesta la?", etc.)
        $asciiFull = Str::of($inputTrimmed)->lower()->ascii()->trim()->squish()->toString();
        $asciiPunctRemoved = trim(preg_replace('/[¿?¡!.,;:]+/', ' ', $asciiFull));
        $asciiPunctRemoved = preg_replace('/\s+/', ' ', $asciiPunctRemoved);

        // AG-42: Quitar preámbulos conversacionales multilínea o introductorios
        $asciiPunctRemoved = preg_replace('/^(?:ahora\s+)?(?:quiero|deseo|anota|apunta|pido|voy\s+a\s+pedir)\s*(?:lo\s+siguiente|esto)?\s*/iu', '', $asciiPunctRemoved);
        $asciiPunctRemoved = preg_replace('/^(?:lo\s+siguiente|mi\s+pedido|mi\s+orden)\s*/iu', '', $asciiPunctRemoved);

        $cleanInput = preg_replace('/^(?:(?:ya\s+)?(?:me\s+da[sn]?|me\s+trae[sn]?|dame|deme|traeme|sirveme|anotame|pido|quiero|deseo|quisiera|voy\s+a\s+(?:pedir|querer|ordenar)|entonces(?:\s+(?:dame|deme|un|una|el|la))?|favor\s+de\s+(?:darme|traerme)|por\s+favor(?:\s+dame)?|cuanto\s+cuesta(?:\s+(?:la|el|los|las))?|precio\s+de(?:\s+(?:la|el|los|las))?|a\s+cuanto(?:\s+(?:la|el))?|por\s+favor|y\s+su|y\s+la|y\s+el)\s*(?:un|una|el|la|los|las|unos|unas)?)\s+/i', '', $asciiPunctRemoved);

        // Quitar cuantificadores y presentaciones comunes ("1 porción de", "una porción de", "1 orden de")
        $cleanInput = preg_replace('/^(?:(?:1|una?)\s+)?(?:porcion|orden|plato)\s+(?:de\s+)?/iu', '', $cleanInput);

        $normInput = trim(preg_replace('/\b(?:acomo|como)\s+(?:esta|sale|cuesta)\b/i', '', $cleanInput));
        $normInput = preg_replace('/\b(bisteck|bisteq)\b/i', 'bistec', $normInput);
        $normInput = preg_replace('/\b(churasco)\b/i', 'churrasco', $normInput);

        // Si es una consulta general de categoría o disponibilidad amplia ("algo para tomar", "tienes algo de tomar"), NO es referencia de producto individual
        if (preg_match('/\b(?:algo|que\s+hay|tienen\s+algo|opciones|carta|menu)\s+(?:para|de)\s+(?:tomar|beber|comer|cenar|almorzar)\b/iu', $normInput)) {
            return new CatalogAmbiguityResolutionDTO(
                status: 'NOT_APPLICABLE',
                reason: "Consulta general de categoría, no es referencia directa a un producto específico.",
                additionalData: ['commercial_substitution_allowed' => true]
            );
        }

        // 1. Detectar si el cliente hace referencia por precio ("el de 17", "el de 12", "17 soles")
        if (preg_match('/(?:el de|de|precio|soles)\s*([0-9]+(?:\.[0-9]{1,2})?)/i', $inputTrimmed, $matches) || is_numeric($inputTrimmed)) {
            $requestedPrice = (float) ($matches[1] ?? $inputTrimmed);

            $matchingMenuGroups = DB::table('menu_groups')
                ->where('tenant_id', $tenantId)
                ->where('price', $requestedPrice)
                ->where('status', '!=', 'REJECTED')
                ->get();

            // 0 Coincidencias -> NOT_FOUND (RF-19)
            if ($matchingMenuGroups->count() === 0) {
                return new CatalogAmbiguityResolutionDTO(
                    status: 'NOT_FOUND',
                    price: $requestedPrice,
                    reason: "No existe ningún menú registrado con precio S/ " . number_format($requestedPrice, 2)
                );
            }

            // >1 Coincidencias -> AMBIGUOUS (RF-13: Prohibido first() o escoger ciega)
            if ($matchingMenuGroups->count() > 1) {
                $candidateNames = $matchingMenuGroups->pluck('name')->toArray();
                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                    price: $requestedPrice,
                    candidates: $candidateNames,
                    reason: "Tenemos dos opciones a S/ " . number_format($requestedPrice, 2) . ": " . implode(" y ", $candidateNames) . ". ¿Cuál deseas?"
                );
            }

            // 1 Coincidencia -> RESOLVED
            $resolvedGroup = $matchingMenuGroups->first();
            return new CatalogAmbiguityResolutionDTO(
                status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                entityType: 'menu',
                entityId: $resolvedGroup->id,
                entityName: $resolvedGroup->name,
                price: (float) $resolvedGroup->price,
                confidence: 1.0
            );
        }

        // 1.5. Resolución Técnica Determinista para Catálogos Multi-Dominio (AG-46-E & AG-46-F)
        $techResolver = new TechnicalCompatibilityResolverService();
        if ($techResolver->hasTechnicalCapability($tenantId)) {
            $techResult = $techResolver->resolve($tenantId, $customerInput, $context);
            if ($techResult->isResolved()) {
                $cand = $techResult->getResolvedCandidate();
                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                    entityType: 'product',
                    entityId: $cand['id'],
                    entityName: $cand['name'],
                    price: (float) $cand['base_price'],
                    confidence: 1.0,
                    additionalData: [
                        'sku' => $cand['sku'],
                        'stock' => $cand['stock'],
                        'stock_status' => $cand['stock_status'],
                        'compatibility' => $cand['compatibility'],
                        'attributes' => $cand['attributes'],
                        'authority' => $techResult->authority,
                        'catalog_version_id' => $techResult->catalogVersionId,
                        'resolution_trace' => $techResult->resolutionTrace,
                        'normalized_entities' => $techResult->normalizedEntities,
                        'compatible_candidates' => $techResult->compatibleCandidates,
                        'evaluated_candidates_count' => $techResult->evaluatedCandidatesCount,
                    ]
                );
            } elseif ($techResult->isAmbiguous()) {
                $sideLabels = [
                    'LEFT' => 'Izquierdo',
                    'RIGHT' => 'Derecho',
                    'FRONT' => 'Delantero',
                    'REAR' => 'Trasero',
                    'BOTH' => 'Ambos lados',
                ];
                $candsList = collect($techResult->compatibleCandidates)->map(function ($c) use ($sideLabels) {
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
                    return "{$c['name']}{$sideStr}{$colorStr} (S/ {$priceStr})";
                })->toArray();
                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                    candidates: $candsList,
                    reason: $techResult->requiredDisambiguation ?? $techResult->ambiguityReason,
                    additionalData: [
                        'ambiguity_reason' => $techResult->ambiguityReason,
                        'required_disambiguation' => $techResult->requiredDisambiguation,
                        'authority' => $techResult->authority,
                        'catalog_version_id' => $techResult->catalogVersionId,
                        'normalized_entities' => $techResult->normalizedEntities,
                        'compatible_candidates' => $techResult->compatibleCandidates,
                        'evaluated_candidates_count' => $techResult->evaluatedCandidatesCount,
                    ]
                );
            } elseif ($techResult->isUnconfirmedCompatibility()) {
                return new CatalogAmbiguityResolutionDTO(
                    status: 'UNCONFIRMED_COMPATIBILITY',
                    reason: $techResult->ambiguityReason,
                    additionalData: [
                        'ambiguity_reason' => $techResult->ambiguityReason,
                        'required_disambiguation' => $techResult->requiredDisambiguation,
                        'authority' => $techResult->authority,
                        'catalog_version_id' => $techResult->catalogVersionId,
                        'commercial_substitution_allowed' => false,
                        'normalized_entities' => $techResult->normalizedEntities,
                        'evaluated_candidates_count' => $techResult->evaluatedCandidatesCount,
                    ]
                );
            } elseif ($techResult->isNoMatch()) {
                return new CatalogAmbiguityResolutionDTO(
                    status: 'NOT_FOUND',
                    reason: "El producto consultado no es compatible con las especificaciones solicitadas.",
                    additionalData: [
                        'ambiguity_reason' => $techResult->ambiguityReason,
                        'rejected_candidates' => $techResult->rejectedCandidates,
                        'evaluated_candidates_count' => $techResult->evaluatedCandidatesCount,
                        'commercial_substitution_allowed' => false,
                        'catalog_version_id' => $techResult->catalogVersionId,
                        'normalized_entities' => $techResult->normalizedEntities,
                    ]
                );
            }
        }

        // 2. Resolución Canónica P3.11 con Calificadores Estructurados (AG-35)
        $p311Resolution = $this->resolveP311ProductOrVariant($tenantId, $customerInput, $normInput);
        if ($p311Resolution !== null) {
            return $p311Resolution;
        }

        // 3. Resolución por Grupos de Menú Legados (RF-14, RF-15)
        $aliasMap = [
            'marino' => 'menu marino',
            'el marino' => 'menu marino',
            'comun' => 'menu comun',
            'el comun' => 'menu comun',
            'especial' => 'menu especial',
            'el especial' => 'menu especial',
            'super' => 'super especial',
            'el super' => 'super especial',
            'el super especial' => 'super especial',
        ];

        $targetNormName = $aliasMap[$normInput] ?? $normInput;

        $matchingGroupByName = DB::table('menu_groups')
            ->where('tenant_id', $tenantId)
            ->where(function($q) use ($targetNormName, $normInput) {
                $q->where('normalized_name', $targetNormName)
                  ->orWhere('normalized_name', 'like', "%{$normInput}%");
            })
            ->where('status', '!=', 'REJECTED')
            ->get();

        if ($matchingGroupByName->count() === 1) {
            $group = $matchingGroupByName->first();
            return new CatalogAmbiguityResolutionDTO(
                status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                entityType: 'menu',
                entityId: $group->id,
                entityName: $group->name,
                price: (float) $group->price,
                confidence: 1.0
            );
        }

        if ($matchingGroupByName->count() > 1) {
            return new CatalogAmbiguityResolutionDTO(
                status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                candidates: $matchingGroupByName->pluck('name')->toArray(),
                reason: "Existen múltiples opciones que coinciden con su búsqueda. Por favor especifique."
            );
        }

        return new CatalogAmbiguityResolutionDTO(
            status: 'NOT_FOUND',
            reason: "No disponemos de '{$customerInput}' en nuestra carta oficial.",
            additionalData: ['commercial_substitution_allowed' => false]
        );
    }

    /**
     * Extractor de Calificadores Estructurados para Cumplimiento de AG-35.
     * Descompone en: SERVING_SIZE, PORTION_FRACTION, WEIGHT, PIECES.
     */
    public function extractStructuredQualifiers(string $text): array
    {
        $norm = Str::of($text)->lower()->ascii()->trim()->squish()->toString();
        $qualifiers = [];

        // 1. SERVING_SIZE (Detectar todas las opciones para prevenir pérdida de identidad en multi-ítem)
        $detectedSizes = [];
        if (preg_match('/\b(familiar|familiares)\b/i', $norm)) {
            $detectedSizes[] = 'FAMILIAR';
        }
        if (preg_match('/\b(para dos|para 2|dos personas|duo|duos)\b/i', $norm)) {
            $detectedSizes[] = 'DOS';
        }
        if (preg_match('/\b(personal|personales|individual)\b/i', $norm)) {
            $detectedSizes[] = 'PERSONAL';
        }
        if (preg_match('/\b(gigante|mega|super|grande)\b/i', $norm)) {
            $detectedSizes[] = 'GIGANTE';
        }
        if (preg_match('/\b(bravazo)\b/i', $norm)) {
            $detectedSizes[] = 'BRAVAZO';
        }
        if (preg_match('/\b(wallpa|especial wallpa)\b/i', $norm)) {
            $detectedSizes[] = 'WALLPA';
        }

        if (count($detectedSizes) === 1) {
            $qualifiers['SERVING_SIZE'] = $detectedSizes[0];
        } elseif (count($detectedSizes) > 1) {
            $qualifiers['SERVING_SIZE'] = 'CONFLICT';
            $qualifiers['MULTIPLE_SERVING_SIZES'] = $detectedSizes;
        }

        // 2. PORTION_FRACTION
        $detectedFractions = [];
        if (preg_match('/\b(1\/4|un cuarto|cuarto)\b/i', $norm)) {
            $detectedFractions[] = '1/4';
        }
        if (preg_match('/\b(1\/2|medio pollo|medio|media)\b/i', $norm)) {
            $detectedFractions[] = '1/2';
        }
        if (preg_match('/\b(1\/8|un octavo|octavo)\b/i', $norm)) {
            $detectedFractions[] = '1/8';
        }
        if (preg_match('/\b(1 pollo|un pollo|entero)\b/i', $norm)) {
            $detectedFractions[] = '1';
        }
        if (preg_match('/\b(2 pollos|dos pollos)\b/i', $norm)) {
            $detectedFractions[] = '2';
        }

        if (count($detectedFractions) === 1) {
            $qualifiers['PORTION_FRACTION'] = $detectedFractions[0];
        } elseif (count($detectedFractions) > 1) {
            $qualifiers['PORTION_FRACTION'] = 'CONFLICT';
            $qualifiers['MULTIPLE_PORTION_FRACTIONS'] = $detectedFractions;
        }

        // 3. WEIGHT
        if (preg_match('/\b(250\s*g|250\s*gr|250\s*gramos)\b/i', $norm)) {
            $qualifiers['WEIGHT'] = '250G';
        } elseif (preg_match('/\b(500\s*g|500\s*gr|500\s*gramos)\b/i', $norm)) {
            $qualifiers['WEIGHT'] = '500G';
        } elseif (preg_match('/\b(1\s*kg|1\s*kilo|un kilo|1000\s*g)\b/i', $norm)) {
            $qualifiers['WEIGHT'] = '1KG';
        }

        // 4. PIECES
        if (preg_match('/\b(8\s*piezas|8\s*alitas|8\s*und)\b/i', $norm)) {
            $qualifiers['PIECES'] = '8_PIEZAS';
        } elseif (preg_match('/\b(12\s*piezas|12\s*alitas|12\s*und)\b/i', $norm)) {
            $qualifiers['PIECES'] = '12_PIEZAS';
        }

        return $qualifiers;
    }

    /**
     * Resolución de Productos Canónicos y Variantes bajo el contrato P3.11 con AG-35.
     */
    public function resolveP311ProductOrVariant(int $tenantId, string $rawInput, string $normInput, ?int $activeMediaId = null): ?CatalogAmbiguityResolutionDTO
    {
        // 1. Detectar si es una búsqueda ordinal con atributos ("la segunda blusa azul", "la segunda", etc.)
        $ordinal = null;
        if (preg_match('/\b(?:la\s+)?(primera|segunda|tercera|cuarta|quinta)\b/i', $rawInput, $ordMatches)) {
            $ordinalMap = ['primera' => 1, 'segunda' => 2, 'tercera' => 3, 'cuarta' => 4, 'quinta' => 5];
            $ordinal = $ordinalMap[strtolower($ordMatches[1])] ?? null;
        }

        // Detectar color u otros atributos visuales ("azul", "rojo", "negra", "m", "l")
        $attributeColor = null;
        if (preg_match('/\b(azul|rojo|roja|negro|negra|blanco|blanca|verde)\b/i', $rawInput, $colMatches)) {
            $attributeColor = strtolower($colMatches[1]);
        }

        // 2. Si hay ordinal o atributo visual, buscar en ProductVisualReference
        if ($ordinal !== null || $attributeColor !== null) {
            $pvrQuery = \App\Modules\Business\Models\ProductVisualReference::where('tenant_id', $tenantId)
                ->where('status', 'PUBLISHED')
                ->when($activeMediaId, fn($q) => $q->where('commercial_media_id', $activeMediaId))
                ->when($ordinal !== null, fn($q) => $q->where('display_index', $ordinal));

            $pvrList = $pvrQuery->with([
                'product' => fn($q) => $q->withoutGlobalScopes(),
                'variant.product' => fn($q) => $q->withoutGlobalScopes()
            ])->get();

            if ($attributeColor) {
                $filtered = $pvrList->filter(function($pvr) use ($attributeColor) {
                    if ($pvr->variant) {
                        return str_contains(strtolower($pvr->variant->color ?? ''), $attributeColor)
                            || str_contains(strtolower($pvr->variant->sku ?? ''), $attributeColor);
                    }
                    if ($pvr->product) {
                        return str_contains(strtolower($pvr->product->name), $attributeColor);
                    }
                    return false;
                });

                if ($filtered->count() > 1) {
                    return new CatalogAmbiguityResolutionDTO(
                        status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                        candidates: $filtered->map(fn($item) => $item->variant ? $item->variant->sku : $item->product->name)->toArray(),
                        reason: "Existen múltiples opciones disponibles en color {$attributeColor}. ¿Cuál modelo prefieres?"
                    );
                }

                if ($filtered->count() === 1) {
                    $pvr = $filtered->first();
                    $variant = $pvr->variant;
                    $product = $variant ? $variant->product : $pvr->product;
                    $effectivePrice = $variant ? $variant->effective_price : (float) $product->base_price;

                    return new CatalogAmbiguityResolutionDTO(
                        status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                        entityType: 'variant',
                        entityId: $variant ? $variant->id : $product->id,
                        entityName: ($product ? $product->name : '') . ' - ' . ($variant ? ($variant->color . ' ' . $variant->talla) : ''),
                        price: $effectivePrice,
                        confidence: 1.0,
                        additionalData: [
                            'sku' => $variant ? $variant->sku : null,
                            'pvr_id' => $pvr->id,
                            'display_index' => $pvr->display_index,
                            'commercial_media_id' => $pvr->commercial_media_id,
                            'evidence_id' => $pvr->evidence_id,
                            'bbox' => ['x' => $pvr->bbox_x, 'y' => $pvr->bbox_y, 'w' => $pvr->bbox_w, 'h' => $pvr->bbox_h],
                        ]
                    );
                }
            } elseif ($ordinal !== null && $pvrList->count() === 1) {
                $pvr = $pvrList->first();
                $variant = $pvr->variant;
                $product = $variant ? $variant->product : $pvr->product;
                $effectivePrice = $variant ? $variant->effective_price : (float) $product->base_price;

                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
                    entityType: $variant ? 'variant' : 'product',
                    entityId: $variant ? $variant->id : $product->id,
                    entityName: $product ? $product->name : '',
                    price: $effectivePrice,
                    confidence: 1.0,
                    additionalData: [
                        'sku' => $variant ? $variant->sku : null,
                        'display_index' => $pvr->display_index,
                        'commercial_media_id' => $pvr->commercial_media_id,
                        'evidence_id' => $pvr->evidence_id,
                    ]
                );
            }
        }

        // 3. Resolución Canónica de Alta Precisión con Calificadores Estructurados (Gate AG-35)
        $inputQualifiers = $this->extractStructuredQualifiers($rawInput . ' ' . $normInput);
        $strippedNorm = preg_replace('/^(?:el|la|los|las|un|una|unos|unas)\s+/iu', '', $normInput);

        // Nivel 1: Coincidencia Exacta de Nombre (Exact Match)
        $exactProduct = \App\Modules\Business\Models\Product::where('tenant_id', $tenantId)
            ->where(function($q) use ($normInput, $strippedNorm) {
                $q->where('normalized_name', $normInput)
                  ->orWhere('normalized_name', $strippedNorm)
                  ->orWhereRaw('LOWER(name) = ?', [$normInput])
                  ->orWhereRaw('LOWER(name) = ?', [$strippedNorm]);
            })
            ->first();

        if ($exactProduct) {
            return $this->buildResolvedProductDTO($tenantId, $exactProduct);
        }

        // Nivel 2: Coincidencia Exacta por Alias Comercial
        $aliasMatch = DB::table('commercial_media_aliases')
            ->where('tenant_id', $tenantId)
            ->where('alias', $normInput)
            ->first();

        if ($aliasMatch) {
            $aliasPvr = \App\Modules\Business\Models\ProductVisualReference::where('tenant_id', $tenantId)
                ->where('commercial_media_id', $aliasMatch->commercial_media_id)
                ->where('status', 'PUBLISHED')
                ->first();

            if ($aliasPvr && $aliasPvr->product) {
                return $this->buildResolvedProductDTO($tenantId, $aliasPvr->product);
            }
        }

        // Nivel 2.5: Coincidencia Semántica por Conectores Culinarios (AG-42)
        // Ejemplo: "alitas con salsa de maracuya" == "Alitas en maracuyá"
        $inputConnectorNorm = $this->normalizeCulinaryConnectors($strippedNorm);
        if (!empty($inputConnectorNorm)) {
            $allPublishedProducts = \App\Modules\Business\Models\Product::where('tenant_id', $tenantId)->get();
            $connectorExactMatches = [];
            foreach ($allPublishedProducts as $prod) {
                $candNormConn = $this->normalizeCulinaryConnectors($prod->name);
                if ($candNormConn === $inputConnectorNorm) {
                    $candQualifiers = $this->extractStructuredQualifiers($prod->name);
                    $hasQualConflict = false;
                    $qualKeys = array_filter(array_keys($inputQualifiers), fn($k) => !str_starts_with($k, 'MULTIPLE_'));
                    foreach ($qualKeys as $qk) {
                        if (isset($candQualifiers[$qk]) && $candQualifiers[$qk] !== $inputQualifiers[$qk]) {
                            $hasQualConflict = true;
                            break;
                        }
                    }
                    if (!$hasQualConflict) {
                        $connectorExactMatches[] = $prod;
                    }
                }
            }

            if (count($connectorExactMatches) === 1) {
                return $this->buildResolvedProductDTO($tenantId, $connectorExactMatches[0]);
            } elseif (count($connectorExactMatches) > 1) {
                $candsList = collect($connectorExactMatches)->map(function($p) {
                    return "{$p->name} (S/ " . number_format((float)$p->base_price, 2) . ")";
                })->toArray();
                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                    candidates: $candsList,
                    reason: "Tenemos varias opciones para esa preparación: " . implode(" y ", $candsList) . ". ¿Cuál de ellas prefieres?",
                    additionalData: ['commercial_substitution_allowed' => false]
                );
            }
        }

        // Nivel 3: Búsqueda Semántica con Filtrado Estricto de Calificadores (AG-35 Enforced)
        $stopwords = [
            'dame', 'quiero', 'tienes', 'pasa', 'pasame', 'precio', 'costo', 'como', 'esta',
            'cuanto', 'cuesta', 'opcion', 'opciones', 'familiar', 'familiares', 'personal', 'personales',
            'bravazo', 'cuarto', 'medio', 'entero', 'kilo', 'gramos', 'piezas', 'plato', 'platos',
            'con', 'por', 'del', 'los', 'las', 'que', 'son', 'hay', 'unos', 'unas', 'para', 'favor',
            'salsa', 'salsas', 'crema', 'cremas', 'banadas', 'banados', 'porcion', 'porciones', 'orden', 'estilo'
        ];
        $allWords = array_filter(explode(' ', $strippedNorm), fn($w) => strlen($w) >= 3);
        $coreWords = array_values(array_filter($allWords, fn($w) => !in_array($w, $stopwords)));

        // Si existen sustantivos núcleo (ej: "parrilla", "especial"), buscar OBLIGATORIAMENTE por ellos
        $searchWords = !empty($coreWords) ? $coreWords : $allWords;

        $baseCandidates = \App\Modules\Business\Models\Product::where('tenant_id', $tenantId)
            ->where(function($q) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $q->orWhere('name', 'like', "%{$word}%")
                      ->orWhere('normalized_name', 'like', "%{$word}%");
                }
            })
            ->get();

        if ($baseCandidates->isEmpty()) {
            return null;
        }

        // Detección de Múltiples Calificadores Competidores en la Misma Frase (AG-35: Conflicto / Multi-Ítem)
        if (!empty($inputQualifiers['MULTIPLE_SERVING_SIZES'])) {
            $conflictItems = [];
            foreach ($inputQualifiers['MULTIPLE_SERVING_SIZES'] as $sz) {
                $matchedCand = $baseCandidates->first(function($cand) use ($sz) {
                    $cq = $this->extractStructuredQualifiers($cand->name);
                    return ($cq['SERVING_SIZE'] ?? null) === $sz;
                });
                if ($matchedCand) {
                    $conflictItems[] = "{$matchedCand->name} (S/ " . number_format((float)$matchedCand->base_price, 2) . ")";
                }
            }
            if (count($conflictItems) > 1) {
                return new CatalogAmbiguityResolutionDTO(
                    status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                    candidates: $conflictItems,
                    reason: "Mencionaste varias opciones: " . implode(" y ", $conflictItems) . ". ¿Deseas pedir ambas o cuál de ellas prefieres?",
                    additionalData: [
                        'multiple_items_detected' => true,
                        'commercial_substitution_allowed' => false
                    ]
                );
            }
        }

        // Filtrar candidatos evaluando compatibilidad de modificadores y calificadores
        $compatibleCandidates = [];
        $conflictingCandidates = [];

        foreach ($baseCandidates as $candidate) {
            $candNorm = Str::of($candidate->name)->lower()->ascii()->trim()->squish()->toString();
            $candConnectorNorm = $this->normalizeCulinaryConnectors($candidate->name);
            $candQualifiers = $this->extractStructuredQualifiers($candidate->name);
            $hasConflict = false;

            // 1. REGLA DE MODIFICADORES DISTINTIVOS OBLIGATORIOS (AG-35):
            // Si el cliente especificó palabras distintivas (ej: "especial", "imperial", "suprema")
            // el candidato DEBE poseerlas en su nombre o normalized_name.
            if (!empty($coreWords)) {
                $missingCoreWords = [];
                foreach ($coreWords as $cw) {
                    if (!str_contains($candNorm, $cw) && !str_contains($candConnectorNorm, $cw)) {
                        $missingCoreWords[] = $cw;
                    }
                }
                if (!empty($missingCoreWords)) {
                    $conflictingCandidates[] = $candidate;
                    continue;
                }
            }

            // 2. Verificar compatibilidad de dimensiones de calificadores estructurados
            $qualifierKeys = array_filter(array_keys($inputQualifiers), fn($k) => !str_starts_with($k, 'MULTIPLE_'));
            foreach ($qualifierKeys as $dim) {
                $val = $inputQualifiers[$dim];
                if (isset($candQualifiers[$dim]) && $candQualifiers[$dim] !== $val) {
                    $hasConflict = true;
                    break;
                }
            }

            if ($hasConflict) {
                $conflictingCandidates[] = $candidate;
            } else {
                // Si el cliente especificó un calificador clave (ej: FAMILIAR), el candidato DEBE poseerlo
                if (!empty($qualifierKeys)) {
                    $matchesAllInputQualifiers = true;
                    foreach ($qualifierKeys as $dim) {
                        $val = $inputQualifiers[$dim];
                        if (!isset($candQualifiers[$dim]) || $candQualifiers[$dim] !== $val) {
                            $matchesAllInputQualifiers = false;
                            break;
                        }
                    }
                    if ($matchesAllInputQualifiers) {
                        $compatibleCandidates[] = $candidate;
                    } else {
                        $conflictingCandidates[] = $candidate;
                    }
                } else {
                    $compatibleCandidates[] = $candidate;
                }
            }
        }

        // REGLA AG-35: Si el usuario solicitó un calificador o modificador específico y no hay candidatos compatibles,
        // Queda ESTRICTAMENTE PROHIBIDO emparejar con un candidato incompatible o sustituto (ej: Parrilla Familiar -> Parrilla para Dos).
        if (empty($compatibleCandidates)) {
            return new CatalogAmbiguityResolutionDTO(
                status: 'NOT_FOUND',
                reason: "No disponemos de '{$rawInput}' en nuestra carta oficial.",
                additionalData: [
                    'commercial_substitution_allowed' => false,
                    'input_qualifiers' => $inputQualifiers,
                    'rejected_candidates' => collect($conflictingCandidates)->pluck('name')->toArray(),
                ]
            );
        }

        // Nivel 4: Ambigüedad Legítima (ej: Chaufa de Pollo -> Personal S/ 16 vs Familiar S/ 31)
        if (count($compatibleCandidates) > 1) {
            $candsList = collect($compatibleCandidates)->map(function($p) {
                return "{$p->name} (S/ " . number_format((float)$p->base_price, 2) . ")";
            })->toArray();

            return new CatalogAmbiguityResolutionDTO(
                status: CatalogAmbiguityResolutionDTO::STATUS_AMBIGUOUS,
                candidates: $candsList,
                reason: "Tenemos varias opciones que coinciden con su consulta: " . implode(" y ", $candsList) . ". ¿Cuál deseas?"
            );
        }

        // Nivel 5: Coincidencia Única Segura Compatible
        if (count($compatibleCandidates) === 1) {
            return $this->buildResolvedProductDTO($tenantId, $compatibleCandidates[0]);
        }

        return null;
    }

    /**
     * Construir DTO de Producto Resuelto con Trazabilidad Completa (Gate 8 y Gate 14).
     */
    protected function buildResolvedProductDTO(int $tenantId, $prod): CatalogAmbiguityResolutionDTO
    {
        // Firewall de publicación (Regla 7 / Gate 25): Solo entidades activas / publicadas
        if (isset($prod->status)) {
            $statusVal = is_object($prod->status) ? ($prod->status->value ?? $prod->status->name ?? '') : (string) $prod->status;
            if (in_array(strtoupper($statusVal), ['DRAFT', 'NEEDS_REVIEW', 'PROCESSING', 'REJECTED', 'INACTIVE', 'OUT_OF_STOCK'])) {
                return new CatalogAmbiguityResolutionDTO(
                    status: 'NOT_FOUND',
                    reason: "El producto solicitado no se encuentra publicado para la venta."
                );
            }
        }

        // Buscar PVR para trazabilidad completa (Gate 8 y Gate 14)
        $pvr = \App\Modules\Business\Models\ProductVisualReference::where('tenant_id', $tenantId)
            ->where('product_id', $prod->id)
            ->first();

        return new CatalogAmbiguityResolutionDTO(
            status: CatalogAmbiguityResolutionDTO::STATUS_RESOLVED,
            entityType: 'product',
            entityId: $prod->id,
            entityName: $prod->name,
            price: (float) $prod->base_price,
            confidence: 1.0,
            additionalData: [
                'product_id' => $prod->id,
                'commercial_media_id' => $pvr ? $pvr->commercial_media_id : null,
                'evidence_id' => $pvr ? $pvr->evidence_id : null,
                'display_index' => $pvr ? $pvr->display_index : 1,
            ]
        );
    }

    /**
     * Resolución de Cadena Completa para Pedido (Gate 8 & Gate 36):
     * Input -> Match -> product_id -> canonical_catalog_id -> catalog_version_id -> price -> commercial_media_id -> evidence_id -> order line.
     */
    public function resolveFullChainOrder(int $tenantId, string $productQuery): array
    {
        $dto = $this->resolveCustomerReference($tenantId, $productQuery);

        if ($dto->status !== CatalogAmbiguityResolutionDTO::STATUS_RESOLVED) {
            return [
                'status' => 'FAILED',
                'resolution_status' => $dto->status,
                'reason' => $dto->reason,
            ];
        }

        $productId = $dto->entityId;
        $product = \App\Modules\Business\Models\Product::where('tenant_id', $tenantId)->find($productId);

        $pvr = \App\Modules\Business\Models\ProductVisualReference::where('tenant_id', $tenantId)
            ->where(function($q) use ($productId) {
                $q->where('product_id', $productId)
                  ->orWhereHas('variant', fn($sq) => $sq->where('product_id', $productId));
            })
            ->first();

        // Obtener versión de catálogo publicada
        $catalogVersionMedia = null;
        if ($pvr) {
            $catalogVersionMedia = DB::table('catalog_version_media')
                ->where('tenant_id', $tenantId)
                ->where('commercial_media_id', $pvr->commercial_media_id)
                ->first();
        }

        $catalogVersion = DB::table('catalog_versions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'PUBLISHED')
            ->first();

        return [
            'status' => 'SUCCESS',
            'input' => $productQuery,
            'match_type' => 'MATCH_EXACT',
            'product_id' => $productId,
            'product_name' => $product ? $product->name : $dto->entityName,
            'canonical_catalog_id' => $catalogVersion ? $catalogVersion->id : 1,
            'catalog_version_id' => $catalogVersion ? $catalogVersion->id : ($catalogVersionMedia ? $catalogVersionMedia->catalog_version_id : null),
            'price' => (float) $dto->price,
            'commercial_media_id' => $pvr ? $pvr->commercial_media_id : null,
            'evidence_id' => $pvr ? $pvr->evidence_id : null,
            'order_line' => [
                'product_id' => $productId,
                'name' => $product ? $product->name : $dto->entityName,
                'quantity' => 1,
                'unit_price' => (float) $dto->price,
                'unit_price_snapshot' => (float) $dto->price, // AG-36 Inmutable Snapshot
                'subtotal' => (float) $dto->price,
                'commercial_media_id' => $pvr ? $pvr->commercial_media_id : null,
                'evidence_id' => $pvr ? $pvr->evidence_id : null,
            ]
        ];
    }

    /**
     * Normalizar conectores culinarios para comparación canónica (AG-42).
     * Convierte "con salsa de", "en salsa de", "salsa de", "bañadas en", "al estilo de", etc., a un espacio limpio.
     */
    public function normalizeCulinaryConnectors(string $text): string
    {
        $norm = ' ' . Str::of($text)->lower()->ascii()->trim()->squish()->toString() . ' ';
        $connectors = [
            ' banadas en salsa de ',
            ' banados en salsa de ',
            ' banadas en ',
            ' banados en ',
            ' con salsa de ',
            ' en salsa de ',
            ' salsa de ',
            ' con salsa ',
            ' al estilo de ',
            ' al estilo ',
            ' a la ',
            ' al ',
            ' con ',
            ' en ',
            ' de ',
        ];
        foreach ($connectors as $conn) {
            $norm = str_replace($conn, ' ', $norm);
        }
        return trim(preg_replace('/\s+/', ' ', $norm));
    }
}

