<?php

namespace App\Modules\Multimodal\Runtime\Adapters;

use App\Modules\Multimodal\Runtime\Contracts\VisualEvidenceAdapterContract;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;

/**
 * Adaptador de Documentos Fiscales y Tributarios (Facturas/Boletas) (v2.3).
 * 
 * Regla Constitucional:
 * Normaliza y valida estructura tributaria; si un dato no está en la imagen, permanece null (PARTIAL).
 * Prohibido que el LLM invente campos tributarios faltantes.
 */
class TaxDocumentEvidenceAdapter implements VisualEvidenceAdapterContract
{
    private VisualEvidenceNormalizationService $normalizer;

    public function __construct(?VisualEvidenceNormalizationService $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new VisualEvidenceNormalizationService();
    }

    public function supports(StructuredVisualEvidenceDTO $evidence): bool
    {
        return $evidence->hasEvidence(CommercialVisualEvidenceType::TAX_DOCUMENT)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::INVOICE)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::SALES_RECEIPT)
            || $evidence->assetClassification === CommercialVisualEvidenceType::TAX_DOCUMENT->value
            || $evidence->assetClassification === CommercialVisualEvidenceType::INVOICE->value;
    }

    public function reconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array
    {
        $tenantId = $evidence->tenantId;
        $obs = $evidence->observations;

        $ruc = $obs['ruc'] ?? null;
        $legalName = $obs['legal_name'] ?? null;
        $series = $obs['series'] ?? null;
        $number = $obs['number'] ?? null;
        $subtotal = $this->normalizer->normalizeDecimalAmount($obs['subtotal'] ?? null);
        $tax = $this->normalizer->normalizeDecimalAmount($obs['tax'] ?? null);
        $total = $this->normalizer->normalizeDecimalAmount($obs['total'] ?? null);
        $date = $obs['date'] ?? null;

        // Evaluación de completitud (Sección 21: Documentos Incompletos)
        $isPartial = empty($total) || empty($ruc) || empty($number);
        $evidenceStatus = $isPartial ? 'PARTIAL' : 'STRUCTURED';

        return [
            'status' => VisualResolutionStatus::STRUCTURED_ONLY->value,
            'decision' => 'TAX_EVIDENCE_RECORDED',
            'reconciliation_status' => VisualResolutionStatus::STRUCTURED_ONLY->value,
            'evidence_status' => $evidenceStatus,
            'tenant_id' => $tenantId,
            'correlation_id' => $evidence->correlationId,
            'tax_data' => [
                'ruc' => $ruc,
                'legal_name' => $legalName,
                'series' => $series,
                'number' => $number,
                'date' => $date,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total, // Permanece null si no está en la imagen, nunca inventado
                'currency' => $obs['currency'] ?? 'PEN',
            ],
            'message' => $isPartial 
                ? 'Documento fiscal recibido con campos parciales. Información estructurada sin interpolación.'
                : 'Documento fiscal estructurado correctamente.',
        ];
    }
}
