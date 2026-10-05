<?php

namespace App\Modules\Multimodal\Services;

use App\Modules\Multimodal\Enums\VisualRelationType;
use App\Modules\Multimodal\Models\VisualAsset;
use App\Modules\Multimodal\Models\VisualRelation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisualAssetIngestionService
{
    /**
     * Ingesta un archivo gráfico calculando su hash SHA-256 de forma determinista.
     * Deduplica por (tenant_id, file_hash_sha256).
     */
    public function ingestAsset(
        int $tenantId,
        string $filePath,
        string $mimeType = 'image/png',
        array $metadata = []
    ): VisualAsset {
        $realPath = $filePath;
        $fileHash = null;

        if (file_exists($realPath)) {
            $fileHash = hash_file('sha256', $realPath);
        } else {
            // Soporte para paths virtuales / test fixtures
            $fileHash = hash('sha256', $filePath . '_' . ($metadata['test_seed'] ?? 'seed'));
        }

        // Deduplicación determinista por tenant y hash
        $existing = VisualAsset::where('tenant_id', $tenantId)
            ->where('file_hash_sha256', $fileHash)
            ->first();

        if ($existing) {
            return $existing;
        }

        return VisualAsset::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'source_file_path' => $filePath,
            'file_hash_sha256' => $fileHash,
            'mime_type' => $mimeType,
            'dimensions_json' => $metadata['dimensions'] ?? ['width' => 1920, 'height' => 1080],
            'status' => 'ACTIVE',
            'metadata_json' => $metadata,
        ]);
    }

    /**
     * Precision 2: SUPERSEDES no es automático por diferencia de hash.
     * Requiere relación semántica explícita de actualización contextual.
     */
    public function registerSupersedes(
        int $tenantId,
        int $newAssetId,
        int $priorAssetId,
        ?string $contextualReason = null
    ): VisualRelation {
        return DB::transaction(function () use ($tenantId, $newAssetId, $priorAssetId, $contextualReason) {
            // Actualizar status del asset previo a SUPERSEDED (sin destruirlo ni sobreescribirlo)
            VisualAsset::where('id', $priorAssetId)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'SUPERSEDED']);

            // Crear relación explícita SUPERSEDES en visual_relations
            return VisualRelation::create([
                'tenant_id' => $tenantId,
                'asset_id' => $newAssetId,
                'source_entity_id' => $newAssetId, // a nivel de asset
                'target_entity_id' => $priorAssetId,
                'relation_type' => VisualRelationType::SUPERSEDES->value,
                'confidence' => 1.0,
                'metadata_json' => [
                    'reason' => $contextualReason ?? 'CONTEXTUAL_MENU_CARD_UPDATE',
                    'superseded_at' => now()->toIso8601String(),
                ],
            ]);
        });
    }
}
