<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\TenantCatalogSchemaProfile;
use Illuminate\Support\Str;

class SemanticSchemaMapperService
{
    /**
     * Diccionario de sinónimos canónicos para mapeo semántico universal
     */
    protected array $synonymMap = [
        'core.name' => [
            'producto', 'articulo', 'artículo', 'nombre', 'descripcion_corta',
            'item', 'plato', 'descripcion_producto', 'denominacion', 'title'
        ],
        'core.sku' => [
            'sku', 'codigo', 'código', 'cod', 'referencia', 'ref', 'id_producto',
            'part_number', 'item_code', 'cod_art'
        ],
        'core.category' => [
            'categoria', 'categoría', 'familia', 'linea', 'línea', 'grupo',
            'seccion', 'sección', 'tipo_producto', 'clase'
        ],
        'core.description' => [
            'descripcion', 'descripción', 'detalle', 'caracteristicas',
            'observaciones', 'notas', 'info', 'especificaciones'
        ],
        'core.base_price' => [
            'precio_s', 'precio', 'precio_s_', 'precio_soles', 'precio_unitario',
            'precio_venta', 'pvp', 'importe', 'costo_venta', 'price'
        ],
        'core.status' => [
            'estado', 'status', 'condicion', 'disponibilidad_flag'
        ],
        'compatibility.brand' => [
            'marca', 'brand', 'fabricante', 'marca_moto', 'marca_vehiculo'
        ],
        'compatibility.model' => [
            'modelo', 'model', 'vehiculo', 'moto', 'aplicacion_modelo'
        ],
        'compatibility.year_raw' => [
            'ano_compatibilidad', 'año_compatibilidad', 'ano', 'año', 'years',
            'compatibilidad', 'ano_aplicacion', 'rango_anos', 'year', 'rango_fabricacion'
        ],
        'attributes.presentation_raw' => [
            'tipo_presentacion', 'tipo_presentación', 'presentacion',
            'presentación', 'formato', 'tipo', 'envase', 'empaque'
        ],
        'attributes.side_raw' => [
            'lado', 'posicion', 'posición', 'ubicacion', 'ubicación', 'side'
        ],
        'attributes.material' => [
            'material', 'acabado', 'composicion', 'composición', 'sustrato'
        ],
        'attributes.color' => [
            'color', 'tono', 'coloracion', 'coloración', 'color_carenado'
        ],
        'inventory.stock_raw' => [
            'stock', 'cantidad', 'cant', 'disponible', 'existencias',
            'inventario', 'qty', 'stock_actual', 'unidades'
        ],
        'attributes.custom_specs' => [
            'voltaje', 'voltaje_sistema', 'tension', 'voltage',
            'potencia', 'amperaje', 'presion', 'capacidad', 'cilindrada',
            'especificacion', 'especificaciones', 'custom_specs'
        ],
        'IGNORED_COMPUTED' => [
            'valor_stock_s', 'valor_stock', 'total', 'subtotal',
            'valor_inventario', 'costo_total', 'valorizado', 'calculado'
        ],
    ];

    /**
     * Mapea las columnas descubiertas a los campos canónicos
     *
     * @param array $columnsMetadata Arreglo de metadatos de columnas [index, name, normalized, inferred_type]
     * @return array [column_mappings, unmapped_columns, mapping_confidence]
     */
    public function mapColumns(array $columnsMetadata): array
    {
        $mappings = [];
        $unmapped = [];
        $mappedTargets = [];

        foreach ($columnsMetadata as $col) {
            $normalized = $col['normalized'];
            $bestTarget = $this->findBestTarget($normalized, $col['name'], $col['inferred_type']);

            if ($bestTarget !== null) {
                $mappings[$col['name']] = $bestTarget;
                $mappedTargets[$bestTarget] = $col['name'];
            } else {
                $unmapped[] = $col['name'];
            }
        }

        // Si se mapeó TIPO / PRESENTACIÓN pero no LADO, el side se resolverá en normalización
        return [
            'column_mappings' => $mappings,
            'unmapped_columns' => $unmapped,
            'has_core_name' => isset($mappedTargets['core.name']),
            'has_core_price' => isset($mappedTargets['core.base_price']),
        ];
    }

    /**
     * Encuentra el target canónico que mejor hace match con el nombre y tipo
     */
    protected function findBestTarget(string $normalized, string $rawName, string $inferredType): ?string
    {
        // 0. Prioridad para columnas calculadas o agregadas que deben ser ignoradas
        if (str_contains($normalized, 'valor_stock') || str_contains($normalized, 'costo_total') || $normalized === 'total' || $normalized === 'subtotal') {
            return 'IGNORED_COMPUTED';
        }

        // 1. Coincidencia directa o parcial con sinónimos
        foreach ($this->synonymMap as $target => $synonyms) {
            foreach ($synonyms as $synonym) {
                if ($normalized === $synonym || str_contains($normalized, $synonym)) {
                    // Evitar falsos positivos en core.name para columnas compuestas como condicion_producto, estado_producto, etc.
                    if ($target === 'core.name' && (str_contains($normalized, 'condicion') || str_contains($normalized, 'estado') || str_contains($normalized, 'tipo') || str_contains($normalized, 'id_') || str_contains($normalized, 'codigo'))) {
                        continue;
                    }
                    // Evitar falsos positivos entre 'precio' y 'valor_stock'
                    if ($target === 'core.base_price' && str_contains($normalized, 'stock')) {
                        return 'IGNORED_COMPUTED';
                    }
                    // Evitar falsos positivos entre 'stock' y 'valor_stock'
                    if ($target === 'inventory.stock_raw' && str_contains($normalized, 'valor')) {
                        return 'IGNORED_COMPUTED';
                    }
                    return $target;
                }
            }
        }

        // 2. Coincidencia por heurística de tipo
        if ($inferredType === 'YEAR_RANGE' && !str_contains($normalized, 'fecha')) {
            return 'compatibility.year_raw';
        }

        return null;
    }

    /**
     * Clasifica el Schema Drift frente a un perfil previo
     */
    public function classifyDrift(?TenantCatalogSchemaProfile $previousProfile, string $newSignature, array $columnMappings): array
    {
        // Si no hay perfil previo, es la versión inicial (NO_CHANGE)
        if ($previousProfile === null) {
            $hasEssential = isset(array_flip($columnMappings)['core.name']) && isset(array_flip($columnMappings)['core.base_price']);
            return [
                'drift_category' => TenantCatalogSchemaProfile::DRIFT_NO_CHANGE,
                'validation_status' => $hasEssential ? TenantCatalogSchemaProfile::STATUS_VALID : TenantCatalogSchemaProfile::STATUS_REQUIRES_REVIEW,
                'version' => 1,
                'reason' => 'Perfil inicial baseline de esquema',
            ];
        }

        // Caso 1: Firma exactamente idéntica
        if ($previousProfile->schema_signature === $newSignature) {
            return [
                'drift_category' => TenantCatalogSchemaProfile::DRIFT_NO_CHANGE,
                'validation_status' => TenantCatalogSchemaProfile::STATUS_VALID,
                'version' => $previousProfile->version,
                'reason' => 'Esquema idéntico al perfil activo',
            ];
        }

        // Caso 2: Analizar compatibilidad de columnas
        $prevMappings = $previousProfile->column_mappings ?? [];
        $prevEssentialMapped = isset(array_flip($prevMappings)['core.name']) && isset(array_flip($prevMappings)['core.base_price']);
        $newEssentialMapped = isset(array_flip($columnMappings)['core.name']) && isset(array_flip($columnMappings)['core.base_price']);

        if (!$newEssentialMapped) {
            return [
                'drift_category' => TenantCatalogSchemaProfile::DRIFT_BREAKING_CHANGE,
                'validation_status' => TenantCatalogSchemaProfile::STATUS_BREAKING_CHANGE,
                'version' => $previousProfile->version + 1,
                'reason' => 'Columnas esenciales requeridas (nombre o precio) no se pudieron mapear en el nuevo esquema',
            ];
        }

        // Verificar si las columnas anteriores siguen estando presentes
        $prevColNames = array_keys($prevMappings);
        $newColNames = array_keys($columnMappings);
        $missingCols = array_diff($prevColNames, $newColNames);
        $addedCols = array_diff($newColNames, $prevColNames);

        if (empty($missingCols) && empty($addedCols)) {
            // Mismas columnas semánticas, únicamente cambió la posición/layout
            return [
                'drift_category' => TenantCatalogSchemaProfile::DRIFT_NO_CHANGE,
                'validation_status' => TenantCatalogSchemaProfile::STATUS_VALID,
                'version' => $previousProfile->version,
                'reason' => 'Reordenamiento de columnas (Layout Permutation) semánticamente compatible',
            ];
        }

        if (empty($missingCols)) {
            // Se agregaron columnas nuevas pero se conservan todas las previas
            return [
                'drift_category' => TenantCatalogSchemaProfile::DRIFT_COMPATIBLE_EXTENSION,
                'validation_status' => TenantCatalogSchemaProfile::STATUS_VALID,
                'version' => $previousProfile->version + 1,
                'reason' => 'Extensión compatible: nuevas columnas detectadas sin romper las previas',
            ];
        }

        // Faltan columnas pero el núcleo comercial sigue mapeado
        return [
            'drift_category' => TenantCatalogSchemaProfile::DRIFT_MAPPING_REQUIRES_REVIEW,
            'validation_status' => TenantCatalogSchemaProfile::STATUS_REQUIRES_REVIEW,
            'version' => $previousProfile->version + 1,
            'reason' => 'Cambio de esquema con columnas faltantes respecto a la versión anterior',
        ];
    }

    /**
     * Descubre, mapea y persiste el perfil de esquema para un tenant
     */
    public function discoverAndRegisterProfile(int $tenantId, string $filePath, string $profileName = 'Catálogo'): TenantCatalogSchemaProfile
    {
        $discoveryService = new CatalogSchemaDiscoveryService();
        $discovery = $discoveryService->discoverFromFile($filePath);

        $mappingResult = $this->mapColumns($discovery['columns_metadata']);

        // Obtener perfil activo previo si existe
        $previousProfile = TenantCatalogSchemaProfile::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();

        $drift = $this->classifyDrift($previousProfile, $discovery['schema_signature'], $mappingResult['column_mappings']);

        // Si es NO_CHANGE y el perfil ya existe con esa firma, retornar el existente asegurando mapeos al día
        if ($drift['drift_category'] === TenantCatalogSchemaProfile::DRIFT_NO_CHANGE && $previousProfile && $previousProfile->schema_signature === $discovery['schema_signature']) {
            if ($previousProfile->column_mappings !== $mappingResult['column_mappings']) {
                $previousProfile->update([
                    'column_mappings' => $mappingResult['column_mappings'],
                    'unmapped_columns' => $mappingResult['unmapped_columns'],
                ]);
            }
            return $previousProfile;
        }

        // Crear nuevo perfil
        return TenantCatalogSchemaProfile::create([
            'tenant_id' => $tenantId,
            'profile_name' => $profileName,
            'source_type' => 'EXCEL',
            'schema_signature' => $discovery['schema_signature'],
            'header_row_index' => $discovery['header_row_index'],
            'detected_columns' => $discovery['detected_columns'],
            'column_mappings' => $mappingResult['column_mappings'],
            'unmapped_columns' => $mappingResult['unmapped_columns'],
            'validation_status' => $drift['validation_status'],
            'drift_category' => $drift['drift_category'],
            'version' => $drift['version'],
            'is_active' => true,
        ]);
    }
}
