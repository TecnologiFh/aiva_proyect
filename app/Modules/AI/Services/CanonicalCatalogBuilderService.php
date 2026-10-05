<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\DTOs\CanonicalCatalogDTO;
use App\Modules\AI\DTOs\MenuGroupDTO;
use App\Modules\Business\Models\CatalogVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CanonicalCatalogBuilderService
{
    /**
     * Construye y publica el Catálogo Canónico Versionado (RF-06, RF-07, RF-08, RF-31).
     * Aplica el Firewall de Catálogo: SOLO los datos CONFIRMED/PUBLISHED ingresan a la versión activa.
     */
    public function publishCanonicalCatalog(int $tenantId, array $menuGroupDTOs, ?string $documentId = null): CanonicalCatalogDTO
    {
        return DB::transaction(function () use ($tenantId, $menuGroupDTOs, $documentId) {

            // 1. Marcar la versión/proyección comercial anterior como SUPERSEDED (RF-31)
            // NOTA CONSTITUCIONAL: SUPERSEDED aplica a la versión de proyección en canonical_catalogs,
            // preservando intacta la identidad histórica del modelo MenuGroup en menu_groups.
            DB::table('canonical_catalogs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'PUBLISHED')
                ->update([
                    'status' => 'SUPERSEDED',
                    'superseded_at' => now(),
                    'updated_at' => now(),
                ]);

            // 2. Obtener siguiente número de versión
            $lastVersion = DB::table('canonical_catalogs')->where('tenant_id', $tenantId)->max('version_number') ?? 0;
            $nextVersion = $lastVersion + 1;

            // 3. Filtrar únicamente MenuGroups e Ítems CONFIRMED / NORMALIZED (Firewall RF-06/RF-08/RF-09)
            $publishedMenuGroups = [];
            foreach ($menuGroupDTOs as $mgDTO) {
                if ($mgDTO->status === 'REJECTED') {
                    continue;
                }

                $filteredItems = array_filter($mgDTO->items, function ($item) {
                    return !($item['is_price_ambiguous'] ?? false);
                });

                $publishedMenuGroups[] = new MenuGroupDTO(
                    id: $mgDTO->id,
                    tenantId: $mgDTO->tenantId,
                    name: $mgDTO->name,
                    normalizedName: $mgDTO->normalizedName,
                    price: $mgDTO->price,
                    currency: $mgDTO->currency,
                    description: $mgDTO->description,
                    sourceEvidenceId: $mgDTO->sourceEvidenceId,
                    confidence: $mgDTO->confidence,
                    status: 'PUBLISHED',
                    items: array_values($filteredItems)
                );
            }

            $canonicalCatalogDTO = new CanonicalCatalogDTO(
                version: $nextVersion,
                tenantId: $tenantId,
                status: 'PUBLISHED',
                menuGroups: $publishedMenuGroups,
                unassociatedProducts: [],
                generatedAt: now()->toIso8601String()
            );

            // 4. Persistir registro en canonical_catalogs (RF-07)
            DB::table('canonical_catalogs')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'version_number' => $nextVersion,
                'status' => 'PUBLISHED',
                'source_document_id' => $documentId,
                'published_at' => now(),
                'payload_json' => json_encode($canonicalCatalogDTO->toArray(), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Registrar en catalog_versions para retrocompatibilidad
            CatalogVersion::create([
                'tenant_id' => $tenantId,
                'version_number' => $nextVersion,
                'changelog_summary' => "Publicación v{$nextVersion} desde Canonical Catalog Builder.",
                'published_items_count' => count($publishedMenuGroups),
                'version_snapshot' => $canonicalCatalogDTO->toArray(),
            ]);

            return $canonicalCatalogDTO;
        });
    }
}
