<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\DTOs\CanonicalProductDTO;
use App\Modules\Business\DTOs\ProvenanceDTO;
use App\Modules\Business\Models\CanonicalCatalogItem;
use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\Category;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Models\TenantCatalogSchemaProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogSchemaAdapterService
{
    protected CatalogSchemaDiscoveryService $discoveryService;
    protected SemanticSchemaMapperService $mapperService;
    protected CanonicalValueNormalizerService $normalizerService;

    public function __construct(
        ?CatalogSchemaDiscoveryService $discoveryService = null,
        ?SemanticSchemaMapperService $mapperService = null,
        ?CanonicalValueNormalizerService $normalizerService = null
    ) {
        $this->discoveryService = $discoveryService ?? new CatalogSchemaDiscoveryService();
        $this->mapperService = $mapperService ?? new SemanticSchemaMapperService();
        $this->normalizerService = $normalizerService ?? new CanonicalValueNormalizerService();
    }

    /**
     * Pipeline completo de ingestión canónica, versionado y publicación atómica (AG-46-D).
     *
     * @param int $tenantId ID del tenant
     * @param string $filePath Ruta absoluta al archivo físico
     * @param string $importMode FULL_SNAPSHOT o INCREMENTAL
     * @param int|null $userId ID del usuario que ejecuta la acción
     * @return array Resumen forense de la ingestión
     */
    public function ingestAndPublish(
        int $tenantId,
        string $filePath,
        string $importMode = CatalogVersion::MODE_FULL_SNAPSHOT,
        ?int $userId = null
    ): array {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Archivo no encontrado en {$filePath}");
        }

        // D3: Idempotencia estricta por hash SHA-256 de archivo
        $fileHash = hash_file('sha256', $filePath);
        $existingVersion = CatalogVersion::where('tenant_id', $tenantId)
            ->where('source_file_hash', $fileHash)
            ->where('status', CatalogVersion::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->first();

        if ($existingVersion !== null && $importMode === CatalogVersion::MODE_FULL_SNAPSHOT) {
            return [
                'status' => 'SOURCE_ALREADY_INGESTED',
                'message' => 'El archivo ya fue procesado e ingerido previamente sin cambios.',
                'catalog_version_id' => $existingVersion->id,
                'version_number' => $existingVersion->version_number,
                'items_count' => $existingVersion->published_items_count,
                'is_idempotent_hit' => true,
            ];
        }

        // 1. Descubrimiento de esquema (AG-46-B)
        $discovery = $this->discoveryService->discoverFromFile($filePath);
        $headerRowIndex = $discovery['header_row_index'];

        // 2. Registro y clasificación de Schema Drift (AG-46-B)
        $profile = $this->mapperService->discoverAndRegisterProfile(
            $tenantId,
            $filePath,
            'Catálogo ' . pathinfo($filePath, PATHINFO_FILENAME)
        );

        // 3. Extraer todas las filas de datos
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $rawRows = ($extension === 'csv' || $extension === 'txt')
            ? $this->discoveryService->extractRowsFromCsv($filePath)
            : $this->discoveryService->extractRowsFromXlsx($filePath);

        $canonicalDTOs = [];
        $fileName = basename($filePath);

        foreach ($rawRows as $rowIndex => $cells) {
            if ($rowIndex <= $headerRowIndex) {
                continue;
            }

            // Mapear celdas a array asociativo con nombres de cabecera
            $assocRow = [];
            $hasData = false;
            foreach ($discovery['detected_columns'] as $cIdx => $colName) {
                $val = trim((string)($cells[$cIdx] ?? ''));
                $assocRow[$colName] = $val;
                if ($val !== '') {
                    $hasData = true;
                }
            }

            if (!$hasData) {
                continue;
            }

            $provenance = new ProvenanceDTO(
                sourceFile: $fileName,
                sourceRow: $rowIndex,
                schemaProfileId: $profile->id,
                catalogVersionId: null, // Asignado tras crear la versión
                sourceValues: $assocRow
            );

            // AG-46-C: Normalización canónica pura con preservación RAW
            $dto = $this->normalizerService->normalizeRowToCanonicalDTO(
                $assocRow,
                $profile->column_mappings,
                $provenance
            );

            $canonicalDTOs[] = [
                'dto' => $dto,
                'row_index' => $rowIndex,
                'raw_row' => $assocRow,
            ];
        }

        if (empty($canonicalDTOs)) {
            throw new \RuntimeException("No se extrajeron registros válidos para importar");
        }

        // D1: Ingestión Atómica dentro de Transacción de Base de Datos
        DB::beginTransaction();
        try {
            // D2: Versionado real determinista
            $maxVer = CatalogVersion::where('tenant_id', $tenantId)->max('version_number') ?? 0;
            $nextVersion = $maxVer + 1;

            $catalogVersion = CatalogVersion::create([
                'tenant_id' => $tenantId,
                'version_number' => $nextVersion,
                'source_file' => $fileName,
                'source_file_hash' => $fileHash,
                'schema_profile_id' => $profile->id,
                'import_mode' => $importMode,
                'changelog_summary' => "Ingestión {$importMode} (v{$nextVersion}) desde {$fileName}",
                'published_items_count' => count($canonicalDTOs),
                'published_by' => $userId,
                'status' => CatalogVersion::STATUS_DRAFT,
                'is_active' => false,
            ]);

            // Persistir cada ítem canónico en canonical_catalog_items
            $persistedCanonicalIds = [];
            foreach ($canonicalDTOs as $item) {
                /** @var CanonicalProductDTO $dto */
                $dto = $item['dto'];

                $provArray = $dto->provenance->toArray();
                $provArray['catalog_version_id'] = $catalogVersion->id;

                $canonicalRecord = CanonicalCatalogItem::create([
                    'tenant_id' => $tenantId,
                    'catalog_version_id' => $catalogVersion->id,
                    'schema_profile_id' => $profile->id,
                    'sku' => $dto->core->sku,
                    'name' => $dto->core->name,
                    'category' => $dto->core->category,
                    'description' => $dto->core->description,
                    'base_price' => $dto->core->basePrice,
                    'price_status' => $dto->core->priceStatus,
                    'compatibility_json' => $dto->compatibility->toArray(),
                    'attributes_json' => $dto->attributes->toArray(),
                    'stock' => $dto->inventory->stock,
                    'stock_status' => $dto->inventory->stockStatus,
                    'provenance_json' => $provArray,
                    'status' => $dto->core->status,
                ]);

                $persistedCanonicalIds[] = $canonicalRecord->id;
            }

            // D1 & D2: Publicación atómica
            // 1. Desactivar versión publicada previa para cumplir restricción uq_single_active_catalog
            CatalogVersion::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'status' => CatalogVersion::STATUS_SUPERSEDED,
                ]);

            // 2. Activar la nueva versión
            $catalogVersion->update([
                'status' => CatalogVersion::STATUS_PUBLISHED,
                'is_active' => true,
            ]);

            // 3. Proyección comercial en products
            $this->projectToCommercialProducts($tenantId, $catalogVersion, $canonicalDTOs, $importMode);

            DB::commit();

            return [
                'status' => 'SUCCESS',
                'message' => "Catálogo ingerido y publicado exitosamente en Versión {$nextVersion}",
                'catalog_version_id' => $catalogVersion->id,
                'version_number' => $nextVersion,
                'items_count' => count($canonicalDTOs),
                'schema_profile_id' => $profile->id,
                'drift_category' => $profile->drift_category,
                'import_mode' => $importMode,
                'is_idempotent_hit' => false,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("CatalogSchemaAdapterService::ingestAndPublish falló: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'tenant_id' => $tenantId,
            ]);
            throw $e;
        }
    }

    /**
     * Proyecta los registros canónicos en la tabla operacional products (AG-46-E/Proyección)
     */
    protected function projectToCommercialProducts(
        int $tenantId,
        CatalogVersion $catalogVersion,
        array $canonicalItems,
        string $importMode
    ): void {
        if (!Schema::hasTable('products')) {
            return;
        }

        // D4: Si es FULL_SNAPSHOT, marcar como inactivos productos de versiones anteriores
        if ($importMode === CatalogVersion::MODE_FULL_SNAPSHOT) {
            Product::where('tenant_id', $tenantId)
                ->where('catalog_version_id', '!=', $catalogVersion->id)
                ->update([
                    'status' => 'INACTIVE',
                    'is_available' => false,
                ]);
        }

        // Cachear categorías
        $categoryCache = [];
        if (Schema::hasTable('categories')) {
            $existingCategories = DB::table('categories')->where('tenant_id', $tenantId)->get();
            foreach ($existingCategories as $c) {
                $categoryCache[strtoupper(trim($c->name))] = $c->id;
            }
        }

        foreach ($canonicalItems as $item) {
            /** @var CanonicalProductDTO $dto */
            $dto = $item['dto'];

            // Resolver ID de categoría
            $catName = $dto->core->category;
            $catKey = strtoupper(trim($catName));
            $categoryId = $categoryCache[$catKey] ?? null;

            if ($categoryId === null && Schema::hasTable('categories') && !empty($catName)) {
                $catData = [
                    'tenant_id' => $tenantId,
                    'name' => $catName,
                    'slug' => Str::slug($catName) . '-' . Str::random(4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('categories', 'uuid')) {
                    $catData['uuid'] = (string) Str::uuid();
                }
                $categoryId = DB::table('categories')->insertGetId($catData);
                $categoryCache[$catKey] = $categoryId;
            }

            // Buscar producto existente por SKU (o por nombre SOLO si no tiene SKU)
            $existingProduct = null;
            if (!empty($dto->core->sku)) {
                $existingProduct = Product::where('tenant_id', $tenantId)
                    ->where('sku', $dto->core->sku)
                    ->first();
            } else {
                $existingProduct = Product::where('tenant_id', $tenantId)
                    ->where('name', $dto->core->name)
                    ->first();
            }

            $projData = [
                'tenant_id' => $tenantId,
                'catalog_version_id' => $catalogVersion->id,
                'category_id' => $categoryId,
                'name' => $dto->core->name,
                'sku' => $dto->core->sku,
                'description' => $dto->core->description,
                'base_price' => $dto->core->basePrice,
                'price_status' => $dto->core->priceStatus,
                'status' => in_array($dto->core->status, ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK']) ? $dto->core->status : 'ACTIVE',
                'is_available' => ($dto->core->status === 'ACTIVE'),
                'stock' => $dto->inventory->stock,
                'stock_status' => $dto->inventory->stockStatus,
                'compatibility' => $dto->compatibility->toArray(),
                'attributes' => $dto->attributes->toArray(),
                'updated_at' => now(),
            ];

            if ($existingProduct !== null) {
                $existingProduct->update($projData);
            } else {
                $projData['uuid'] = (string) Str::uuid();
                $projData['slug'] = Str::slug($dto->core->name) . '-' . Str::random(4);
                $projData['product_type'] = 'PRODUCT';
                $projData['created_at'] = now();
                Product::create($projData);
            }
        }
    }
}
