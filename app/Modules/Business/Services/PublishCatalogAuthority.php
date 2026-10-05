<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\CatalogVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublishCatalogAuthority
{
    protected CommercialCompletenessValidatorService $validator;

    public function __construct(?CommercialCompletenessValidatorService $validator = null)
    {
        $this->validator = $validator ?? new CommercialCompletenessValidatorService();
    }

    /**
     * Única Autoridad Central de Publicación (Regla Constitucional C14).
     * Transición exclusiva a PUBLISHED ∧ is_active = 1 sujeta a completitud 100%.
     * Si faltan ítems vendibles, la publicación es revocada y asignada a NEEDS_REVIEW.
     */
    public function authorizeAndPublish(int $catalogVersionId, int $tenantId, ?array $customExpectedItems = null): CatalogVersion
    {
        // 1. Ejecutar Validación Formal de Completitud Comercial (AG-34)
        $report = $this->validator->validate($catalogVersionId, $tenantId, $customExpectedItems);

        if ($report['status'] !== 'COMPLETE' || $report['missing_count'] > 0) {
            // Bloqueo físico de publicación (Regla C14) persistido en base de datos
            CatalogVersion::where('id', $catalogVersionId)->where('tenant_id', $tenantId)->update([
                'status' => 'NEEDS_REVIEW',
                'is_active' => 0,
            ]);

            Log::warning("⛔ [C14 - PublishCatalogAuthority] Publicación denegada para Version {$catalogVersionId} (Tenant {$tenantId}): Faltan {$report['missing_count']} ítems comerciales vendibles.");

            throw new CommercialCompletenessException(
                "Publicación bloqueada por Regla C14 (Tenant {$tenantId}, Version {$catalogVersionId}): status={$report['status']}, expected={$report['expected_sellable_count']}, missing={$report['missing_count']}, missing_items=" . json_encode($report['missing_items'] ?? []),
                $report
            );
        }

        // 2. Transición Atómica Segura (C13 Single Active Published)
        return DB::transaction(function () use ($catalogVersionId, $tenantId) {
            $version = CatalogVersion::where('id', $catalogVersionId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            CatalogVersion::where('tenant_id', $tenantId)
                ->where('id', '!=', $catalogVersionId)
                ->where('status', 'PUBLISHED')
                ->update([
                    'status' => 'SUPERSEDED',
                    'is_active' => 0,
                ]);

            // 3. Consumar Publicación Oficial Autorizada
            $version->update([
                'status' => 'PUBLISHED',
                'is_active' => 1,
            ]);

            Log::info("✅ [C14 - PublishCatalogAuthority] Catálogo publicado exitosamente con 100% de completitud comercial (Version {$catalogVersionId}, Tenant {$tenantId}).");

            return $version;
        });
    }

    /**
     * Guardrail de Integridad en Boot: Si una versión quedó en PUBLISHED de manera espuria sin validación, corregirla.
     */
    public function enforceIntegrity(int $catalogVersionId, int $tenantId): void
    {
        $report = $this->validator->validate($catalogVersionId, $tenantId);
        if ($report['status'] !== 'COMPLETE') {
            CatalogVersion::where('id', $catalogVersionId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => 'NEEDS_REVIEW',
                    'is_active' => 0,
                ]);
        }
    }
}
