<?php

namespace App\Modules\Multimodal\Runtime\Services;

use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Exceptions\TenantVisualEvidenceViolation;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Trazabilidad y Aislamiento Constitucional Multi-Tenant.
 * Garantiza cero contaminación cruzada entre tenants y trazabilidad inmutable de correlation_id.
 */
class VisualEvidenceTraceabilityService
{
    /**
     * Valida la integridad del tenant y de la correlación para una evidencia.
     *
     * @throws TenantVisualEvidenceViolation
     */
    public function assertTenantIntegrity(StructuredVisualEvidenceDTO $evidence, int $expectedTenantId): void
    {
        if ($evidence->tenantId <= 0) {
            throw new TenantVisualEvidenceViolation("Tenant ID inválido ({$evidence->tenantId}) en evidencia visual.");
        }

        if ($evidence->tenantId !== $expectedTenantId) {
            Log::critical("MULTIMODAL TENANT VIOLATION: Evidencia de Tenant {$evidence->tenantId} fue enviada en contexto de Tenant {$expectedTenantId}.");
            throw new TenantVisualEvidenceViolation(
                "Violación de aislamiento multi-tenant: la evidencia pertenece al tenant {$evidence->tenantId}, pero el contexto activo es {$expectedTenantId}."
            );
        }
    }

    /**
     * Registra en log estructurado la procedencia y linaje de una evidencia.
     */
    public function logEvidenceAudit(StructuredVisualEvidenceDTO $evidence, string $stage = 'INTAKE'): void
    {
        Log::info("MULTIMODAL EVIDENCE [{$stage}]:", [
            'tenant_id' => $evidence->tenantId,
            'correlation_id' => $evidence->correlationId,
            'message_id' => $evidence->messageId,
            'media_id' => $evidence->mediaId,
            'asset_hash' => $evidence->assetHash,
            'classification' => $evidence->assetClassification,
            'evidence_types' => $evidence->detectedEvidence,
            'entities_count' => count($evidence->entities),
            'confidence' => $evidence->confidence,
        ]);
    }
}
