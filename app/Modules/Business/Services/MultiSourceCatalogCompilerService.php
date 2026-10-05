<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Models\ProductVisualReference;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MultiSourceCatalogCompilerService
{
    const ROW_TOLERANCE = 0.05;

    /**
     * Compila y publica atómicamente un catálogo canónico multi-fuente (C13 Atomic Publishing).
     */
    public function compileAndPublish(int $tenantId, array $mediaIds, string $versionSummary = 'Compilación multi-fuente unificada', ?array $customExpectedItems = null): CatalogVersion
    {
        return DB::transaction(function () use ($tenantId, $mediaIds, $versionSummary, $customExpectedItems) {
            // Determinar siguiente número de versión
            $lastVersion = CatalogVersion::where('tenant_id', $tenantId)->max('version_number') ?? 0;
            $newVersionNumber = $lastVersion + 1;

            // 1. Crear nueva versión canónica en estado PROCESSING preliminar
            $newVersion = CatalogVersion::create([
                'tenant_id' => $tenantId,
                'version_number' => $newVersionNumber,
                'changelog_summary' => $versionSummary,
                'published_items_count' => count($mediaIds),
                'published_by' => 1,
                'status' => 'PROCESSING',
                'is_active' => 0,
                'version_snapshot' => [
                    'media_ids' => $mediaIds,
                    'compiled_at' => now()->toIso8601String(),
                ],
            ]);

            // Asignar estado inicial PROCESSING
            try {
                DB::table('catalog_versions')->where('id', $newVersion->id)->update([
                    'status' => 'PROCESSING',
                    'is_active' => 0,
                ]);
            } catch (\Throwable $e) {}

            // 2. Vincular medios comerciales en catalog_version_media (Rule 10: Tenant-Bound)
            foreach ($mediaIds as $mId) {
                $media = CommercialMedia::where('id', $mId)->where('tenant_id', $tenantId)->firstOrFail();

                CatalogVersionMedia::create([
                    'catalog_version_id' => $newVersion->id,
                    'commercial_media_id' => $media->id,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                ]);
            }

            // 3. Regla C14: Autoridad Central Única de Publicación (Validador AG-34 de Completitud)
            $publishAuthority = new PublishCatalogAuthority();
            return $publishAuthority->authorizeAndPublish($newVersion->id, $tenantId, $customExpectedItems);
        });
    }

    /**
     * Algoritmo Determinístico de Filas Geométricas 2D (C12).
     * Asigna display_index (1..N) agrupando por center_y y ordenando por bbox_x.
     * Queda estrictamente prohibido que el LLM fije este índice.
     */
    public function computeGeometricDisplayIndices(array $rawItems): array
    {
        if (empty($rawItems)) {
            return [];
        }

        // 1. Calcular center_y normalizado para cada elemento
        $processed = [];
        foreach ($rawItems as $idx => $item) {
            $bboxY = (float) ($item['bbox_y'] ?? 0.0);
            $bboxH = (float) ($item['bbox_h'] ?? 0.0);
            $bboxX = (float) ($item['bbox_x'] ?? 0.0);
            $bboxW = (float) ($item['bbox_w'] ?? 0.0);

            $centerY = $bboxY + ($bboxH / 2.0);

            $processed[] = [
                'item_id' => $item['id'] ?? $idx,
                'original' => $item,
                'bbox_x' => $bboxX,
                'bbox_y' => $bboxY,
                'bbox_w' => $bboxW,
                'bbox_h' => $bboxH,
                'center_y' => $centerY,
            ];
        }

        // 2. Ordenar preliminarmente por center_y ascendente, luego bbox_x ascendente
        usort($processed, function ($a, $b) {
            if (abs($a['center_y'] - $b['center_y']) > 0.0001) {
                return $a['center_y'] <=> $b['center_y'];
            }
            return $a['bbox_x'] <=> $b['bbox_x'];
        });

        // 3. Agrupar secuencialmente en filas determinísticas usando row_reference_y
        $rows = [];
        $currentRow = [];
        $rowReferenceY = null;

        foreach ($processed as $element) {
            if ($rowReferenceY === null) {
                // Primer elemento de la fila establece row_reference_y
                $rowReferenceY = $element['center_y'];
                $currentRow[] = $element;
            } else {
                if (abs($element['center_y'] - $rowReferenceY) <= self::ROW_TOLERANCE) {
                    $currentRow[] = $element;
                } else {
                    // Cerrar fila actual y comenzar nueva fila
                    $rows[] = $currentRow;
                    $currentRow = [$element];
                    $rowReferenceY = $element['center_y'];
                }
            }
        }

        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        // 4. Dentro de cada fila, ordenar estrictamente por bbox_x ascendente (izquierda a derecha)
        $indexedElements = [];
        $currentIndex = 1;

        foreach ($rows as $row) {
            usort($row, fn($a, $b) => $a['bbox_x'] <=> $b['bbox_x']);
            foreach ($row as $elem) {
                $elem['display_index'] = $currentIndex++;
                $indexedElements[] = $elem;
            }
        }

        return $indexedElements;
    }

    /**
     * Registro con Control de Linaje y Revisiones (C11).
     */
    public function registerMediaRevision(
        int $tenantId,
        string $filePath,
        string $title,
        ?string $category = null,
        ?string $lineageId = null,
        int $displayOrder = 1
    ): CommercialMedia {
        $fullPath = FilePathResolver::resolve($filePath);
        $contentHash = file_exists($fullPath) ? hash_file('sha256', $fullPath) : hash('sha256', $filePath . $title);

        $lineage = $lineageId ?? (string) Str::uuid();

        // Si ya existe un medio con el mismo hash en el mismo tenant y lineage, reutilizar sin crear duplicado
        $existing = CommercialMedia::where('tenant_id', $tenantId)
            ->where('lineage_id', $lineage)
            ->where('content_hash', $contentHash)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Buscar última revisión de este linaje
        $previous = CommercialMedia::where('tenant_id', $tenantId)
            ->where('lineage_id', $lineage)
            ->orderBy('revision_number', 'desc')
            ->first();

        $newRevisionNumber = $previous ? ($previous->revision_number + 1) : 1;
        $previousMediaId = $previous ? $previous->id : null;

        // Regla 10: Validación de tenant en previous_media
        if ($previous && $previous->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException("Cross-tenant lineage violation rejected.");
        }

        return CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => $lineage,
            'media_type' => 'MENU_CARD',
            'category' => $category,
            'title' => $title,
            'file_path' => $filePath,
            'content_hash' => $contentHash,
            'revision_number' => $newRevisionNumber,
            'previous_media_id' => $previousMediaId,
            'mime_type' => 'image/png',
            'file_size_bytes' => file_exists($fullPath) ? filesize($fullPath) : 0,
            'display_order' => $displayOrder,
            'status' => 'ACTIVE',
        ]);
    }
}

class FilePathResolver
{
    public static function resolve(string $path): string
    {
        if (file_exists($path)) return $path;
        if (file_exists(public_path($path))) return public_path($path);
        if (file_exists(storage_path('app/' . $path))) return storage_path('app/' . $path);
        return $path;
    }
}
