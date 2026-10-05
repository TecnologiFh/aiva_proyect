<?php

namespace App\Modules\Multimodal\Runtime\Services;

use App\Modules\Multimodal\Runtime\Adapters\PaymentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\Adapters\TaxDocumentEvidenceAdapter;
use App\Modules\Multimodal\Runtime\Adapters\VisualCatalogAdapter;
use App\Modules\Multimodal\Runtime\Contracts\VisualEvidenceAdapterContract;
use App\Modules\Multimodal\Runtime\Contracts\VisualEvidenceRouterContract;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Exceptions\UnsupportedVisualEvidenceException;

/**
 * Router Universal de Evidencia Visual Comercial (v2.3).
 * Despacha evidencia estructurada hacia el adaptador de dominio propietario.
 */
class CommercialVisualEvidenceRouter implements VisualEvidenceRouterContract
{
    /** @var array<VisualEvidenceAdapterContract> */
    private array $adapters;
    private VisualEvidenceTraceabilityService $traceability;

    public function __construct(
        ?array $adapters = null,
        ?VisualEvidenceTraceabilityService $traceability = null
    ) {
        $this->adapters = $adapters ?? [
            new PaymentEvidenceAdapter(),
            new VisualCatalogAdapter(),
            new TaxDocumentEvidenceAdapter(),
        ];
        $this->traceability = $traceability ?? new VisualEvidenceTraceabilityService();
    }

    public function routeAndReconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array
    {
        // 1. Trazabilidad y validación de aislamiento de Tenant
        if (isset($context['tenant_id'])) {
            $this->traceability->assertTenantIntegrity($evidence, (int)$context['tenant_id']);
        }
        $this->traceability->logEvidenceAudit($evidence, 'ROUTING');

        // 2. Si la imagen fue clasificada como no comercial (V6 / V24)
        if ($evidence->assetClassification === CommercialVisualEvidenceType::NON_COMMERCIAL_IMAGE->value
            || $evidence->hasEvidence(CommercialVisualEvidenceType::NON_COMMERCIAL_IMAGE)) {
            return [
                'status' => VisualResolutionStatus::NON_COMMERCIAL->value,
                'decision' => 'NON_COMMERCIAL_IMAGE_RECEIVED',
                'reconciliation_status' => VisualResolutionStatus::NON_COMMERCIAL->value,
                'tenant_id' => $evidence->tenantId,
                'correlation_id' => $evidence->correlationId,
                'message' => 'Imagen recibida sin contenido comercial o financiero directo.',
            ];
        }

        // 3. Enrutar hacia el primer adaptador que soporte la evidencia
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($evidence)) {
                return $adapter->reconcile($evidence, $context);
            }
        }

        // 4. Si ningún adaptador especializado lo soporta: STRUCTURED_ONLY (Sección 25)
        return [
            'status' => VisualResolutionStatus::STRUCTURED_ONLY->value,
            'decision' => 'UNROUTED_STRUCTURED_EVIDENCE',
            'reconciliation_status' => VisualResolutionStatus::STRUCTURED_ONLY->value,
            'tenant_id' => $evidence->tenantId,
            'correlation_id' => $evidence->correlationId,
            'asset_classification' => $evidence->assetClassification,
            'detected_evidence' => $evidence->detectedEvidence,
            'message' => 'Evidencia visual estructurada sin adaptador de dominio asignado.',
        ];
    }
}
