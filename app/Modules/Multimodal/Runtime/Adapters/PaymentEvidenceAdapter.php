<?php

namespace App\Modules\Multimodal\Runtime\Adapters;

use App\Modules\Multimodal\Runtime\Contracts\VisualEvidenceAdapterContract;
use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;
use App\Modules\Multimodal\Runtime\Enums\VisualResolutionStatus;
use App\Modules\Multimodal\Runtime\Services\VisualEvidenceNormalizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptador de Evidencia de Pago hacia P2.11 (v2.3).
 * 
 * Regla Constitucional:
 * El Runtime transporta la evidencia estructurada; P2.11 reconcilia y decide el estado financiero.
 * Aritmética monetaria estrictamente en strings decimales (bcsub/bccomp). 0 floats.
 */
class PaymentEvidenceAdapter implements VisualEvidenceAdapterContract
{
    private VisualEvidenceNormalizationService $normalizer;

    public function __construct(?VisualEvidenceNormalizationService $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new VisualEvidenceNormalizationService();
    }

    public function supports(StructuredVisualEvidenceDTO $evidence): bool
    {
        return $evidence->hasEvidence(CommercialVisualEvidenceType::PAYMENT_RECEIPT)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::BANK_TRANSFER)
            || $evidence->hasEvidence(CommercialVisualEvidenceType::POS_RECEIPT)
            || $evidence->assetClassification === CommercialVisualEvidenceType::PAYMENT_RECEIPT->value;
    }

    public function reconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array
    {
        $tenantId = $evidence->tenantId;
        $orderId = $context['order_id'] ?? ($context['order']['id'] ?? null);
        $orderTotalStr = $this->normalizer->normalizeDecimalAmount($context['order']['total'] ?? ($context['expected_amount'] ?? null));

        // 1. Extraer campos de pago desde observaciones
        $obs = $evidence->observations;
        $rawProvider = $obs['provider'] ?? 'YAPE';
        $provider = $this->normalizer->normalizePaymentProvider($rawProvider);
        $paidAmountStr = $this->normalizer->normalizeDecimalAmount($obs['amount'] ?? null);
        $operationNumber = $obs['operation_number'] ?? null;
        $dateTime = $obs['date_time'] ?? ($obs['date'] ?? null);
        $destination = $obs['destination'] ?? ($obs['recipient_name'] ?? null);

        // 2. Control de Idempotencia y Exactly-Once delegado a la autoridad formal de P2.11 (V13 / V25)
        $isDuplicate = false;
        if (!empty($evidence->messageId) && Schema::hasTable('payment_webhook_receipts')) {
            $isDuplicate = DB::table('payment_webhook_receipts')
                ->where('tenant_id', $tenantId)
                ->where('whatsapp_message_id', $evidence->messageId)
                ->exists();
        }

        if (!$isDuplicate && !empty($operationNumber) && $orderId && Schema::hasTable('payment_extractions')) {
            $isDuplicate = DB::table('payment_extractions')
                ->join('payment_evidences', 'payment_evidences.id', '=', 'payment_extractions.payment_evidence_id')
                ->where('payment_evidences.tenant_id', $tenantId)
                ->where('payment_evidences.order_id', $orderId)
                ->where('payment_extractions.operation_id', $operationNumber)
                ->exists();
        }

        if ($isDuplicate) {
            return [
                'status' => 'COMPLETED',
                'decision' => 'IDEMPOTENT_REPLAY',
                'reconciliation_status' => 'IDEMPOTENT_DUPLICATE',
                'idempotency_hit' => true,
                'financial_duplicate_effect' => 0,
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'paid_amount' => $paidAmountStr,
                'operation_number' => $operationNumber,
                'provider' => $provider,
                'message' => '🟡 Este comprobante ya fue recibido y procesado previamente para este pedido.',
            ];
        }

        // Registrar recibo en P2.11 para futuras consultas de idempotencia
        if (!empty($evidence->messageId) && Schema::hasTable('payment_webhook_receipts')) {
            DB::table('payment_webhook_receipts')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'whatsapp_message_id' => $evidence->messageId,
                'processing_status' => 'COMPLETED',
                'response_payload' => json_encode(['order_id' => $orderId, 'processed_at' => now()->toIso8601String()]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Reconciliación con la orden
        if (empty($orderTotalStr) || $orderId === null) {
            return [
                'status' => 'STRUCTURED_ONLY',
                'decision' => 'PENDING_ORDER_BINDING',
                'reconciliation_status' => 'NO_ACTIVE_ORDER',
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'paid_amount' => $paidAmountStr,
                'operation_number' => $operationNumber,
                'provider' => $provider,
                'message' => '⚠️ Recibimos tu comprobante, pero no encontramos un pedido pendiente de pago asociado a tu conversación.',
            ];
        }

        // Comparación monetaria exacta con bccomp (0 floats)
        $cmp = bccomp($paidAmountStr ?? '0.00', $orderTotalStr, 2);

        if ($cmp === 0) {
            // Pago Exacto (V5)
            return [
                'status' => 'APPROVED',
                'decision' => 'PAYMENT_ACCEPTED',
                'reconciliation_status' => VisualResolutionStatus::MATCH_EXACT->value,
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'paid_amount' => $paidAmountStr,
                'expected_amount' => $orderTotalStr,
                'pending_amount' => '0.00',
                'operation_number' => $operationNumber,
                'provider' => $provider,
                'is_approved' => true,
                'message' => "✅ Comprobante de {$provider} por S/ {$paidAmountStr} validado exitosamente. Tu pedido ha sido confirmado.",
            ];
        } elseif ($cmp < 0) {
            // Pago Insuficiente / Amount Mismatch (V4)
            $pendingAmount = bcsub($orderTotalStr, $paidAmountStr ?? '0.00', 2);
            return [
                'status' => 'AMOUNT_MISMATCH',
                'decision' => 'PARTIAL_PAYMENT',
                'reconciliation_status' => 'AMOUNT_MISMATCH',
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'paid_amount' => $paidAmountStr,
                'expected_amount' => $orderTotalStr,
                'pending_amount' => $pendingAmount,
                'operation_number' => $operationNumber,
                'provider' => $provider,
                'is_approved' => false,
                'message' => "Monto incompleto. Hemos registrado tu abono de S/ {$paidAmountStr} (Op: {$operationNumber}), pero el total de tu pedido es S/ {$orderTotalStr}. Saldo pendiente: S/ {$pendingAmount}.",
            ];
        } else {
            // Pago Excedente
            return [
                'status' => 'APPROVED',
                'decision' => 'PAYMENT_ACCEPTED_OVERPAID',
                'reconciliation_status' => VisualResolutionStatus::MATCH_EXACT->value,
                'tenant_id' => $tenantId,
                'correlation_id' => $evidence->correlationId,
                'paid_amount' => $paidAmountStr,
                'expected_amount' => $orderTotalStr,
                'pending_amount' => '0.00',
                'operation_number' => $operationNumber,
                'provider' => $provider,
                'is_approved' => true,
                'message' => "✅ Comprobante de {$provider} por S/ {$paidAmountStr} validado exitosamente.",
            ];
        }
    }
}
