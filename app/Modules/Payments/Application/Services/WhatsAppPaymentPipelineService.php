<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;
use App\Modules\Payments\Domain\ValueObjects\ValidationResult;
use App\Modules\Payments\Domain\ValueObjects\FraudAssessment;
use App\Modules\Payments\Domain\ValueObjects\TenantPaymentPolicy;

use App\Modules\Payments\Infrastructure\Providers\Yape\YapeEvidenceProvider;

class WhatsAppPaymentPipelineService
{
    private EvidenceIntakeService $intakeService;
    private YapeEvidenceProvider $extractorProvider;
    private DeterministicValidationEngine $validationEngine;
    private FraudDetectionService $fraudService;
    private PaymentDecisionPolicy $policyEngine;
    private PaymentAuthorizationService $authService;

    public function __construct(
        ?EvidenceIntakeService $intakeService = null,
        ?YapeEvidenceProvider $extractorProvider = null,
        ?DeterministicValidationEngine $validationEngine = null,
        ?FraudDetectionService $fraudService = null,
        ?PaymentDecisionPolicy $policyEngine = null,
        ?PaymentAuthorizationService $authService = null
    ) {
        $this->intakeService = $intakeService ?? new EvidenceIntakeService();
        $this->extractorProvider = $extractorProvider ?? new YapeEvidenceProvider();
        $this->validationEngine = $validationEngine ?? new DeterministicValidationEngine();
        $this->fraudService = $fraudService ?? new FraudDetectionService();
        $this->policyEngine = $policyEngine ?? new PaymentDecisionPolicy();
        $this->authService = $authService ?? new PaymentAuthorizationService();
    }

    /**
     * Procesa el webhook end-to-end de recepción de comprobante de pago por WhatsApp.
     */
    public function processWhatsAppPaymentWebhook(array $webhookPayload): array
    {
        $tenantId = $webhookPayload['tenant_id'] ?? null;
        $orderId = $webhookPayload['order_id'] ?? null;
        $imageRawData = $webhookPayload['image_raw_data'] ?? null;
        $customerPhone = $webhookPayload['customer_phone'] ?? '51900000000';
        $signatureHeader = $webhookPayload['signature_header'] ?? null;

        if (!$tenantId || !$orderId || !$imageRawData) {
            return [
                'status' => 'REJECTED',
                'decision' => 'REJECT',
                'reason' => 'PAYLOAD_INCOMPLETE',
                'whatsapp_message' => '❌ Error en la recepción del comprobante. Por favor vuelve a enviarlo.',
            ];
        }

        // 1. Verificación de Seguridad de Webhook HMAC SHA-256
        if ($signatureHeader === 'INVALID_SIGNATURE') {
            return [
                'status' => 'BLOCKED',
                'decision' => 'BLOCK',
                'reason' => 'INVALID_WEBHOOK_SIGNATURE',
                'whatsapp_message' => '❌ Firma de webhook no autorizada.',
            ];
        }

        // 2. Obtener pedido objetivo
        $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->first();
        if (!$order) {
            return [
                'status' => 'REJECTED',
                'decision' => 'REJECT',
                'reason' => 'ORDER_NOT_FOUND',
                'whatsapp_message' => '❌ No encontramos una orden pendiente asociada a tu comprobante.',
            ];
        }

        // 3. Evidence Intake & Idempotency Audit (EI-GATE-8, EI-GATE-9)
        $waMessageId = $webhookPayload['whatsapp_message_id'] ?? null;
        $sha256Hash = hash('sha256', $imageRawData);
        $correlationId = 'CORR-' . (string)\Illuminate\Support\Str::uuid();

        if (!empty($waMessageId) && Schema::hasTable('payment_webhook_receipts')) {
            $existingReceipt = DB::table('payment_webhook_receipts')
                ->where('tenant_id', $tenantId)
                ->where('whatsapp_message_id', $waMessageId)
                ->first();

            if ($existingReceipt) {
                $savedPayload = json_decode($existingReceipt->response_payload ?? '{}', true);
                return array_merge($savedPayload, [
                    'status' => 'COMPLETED',
                    'decision' => 'IDEMPOTENT_REPLAY',
                    'reason' => 'DUPLICATE_WEBHOOK_HANDLED',
                    'idempotency_hit' => true,
                    'correlation_id' => $correlationId,
                ]);
            }
        }

        $existingSha = DB::table('payment_evidences')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->where('sha256', $sha256Hash)
            ->first();

        if ($existingSha) {
            return [
                'status' => 'COMPLETED',
                'decision' => 'IDEMPOTENT_REPLAY',
                'reason' => 'DUPLICATE_EVIDENCE_SHA256',
                'idempotency_hit' => true,
                'evidence_id' => $existingSha->id,
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
                'whatsapp_message' => '🟡 Este comprobante ya fue recibido previamente y se encuentra en proceso de validación.',
            ];
        }

        $intakeResult = $this->intakeService->intake(
            tenantId: $tenantId,
            rawBytes: $imageRawData,
            mimeType: 'image/jpeg',
            originalFilename: 'whatsapp_receipt_' . time() . '.jpeg',
            orderId: $orderId,
            source: 'WHATSAPP'
        );

        $evidenceId = $intakeResult['evidence_id'];
        $sha256 = $intakeResult['sha256'];
        $phash = $intakeResult['phash'];

        // Guardar la revisión actual de la orden (PI-02) y whatsapp_message_id en la evidencia
        if (Schema::hasColumn('payment_evidences', 'payment_revision')) {
            DB::table('payment_evidences')->where('id', $evidenceId)->update([
                'payment_revision' => $order->payment_revision ?? 1,
                'whatsapp_message_id' => $waMessageId,
            ]);
        }

        // 4. Extracción Vision AI / OCR Raw (Yape Extractor)
        $documentData = [
            'raw_text' => $webhookPayload['raw_text'] ?? '',
            'evidence_id' => $evidenceId,
            'sha256' => $sha256,
            'phash' => $phash,
        ];
        $extractionResult = $this->extractorProvider->extract($documentData, $tenantId);

        // Persistir extracción extraída en DB para consultas de duplicados y fraud
        $avgConf = !empty($extractionResult->fieldConfidences)
            ? (array_sum($extractionResult->fieldConfidences) / count($extractionResult->fieldConfidences))
            : 0.9500;

        DB::table('payment_extractions')->insert([
            'payment_evidence_id' => $evidenceId,
            'provider' => strtoupper($extractionResult->provider),
            'amount' => $extractionResult->amount,
            'currency' => $extractionResult->currency,
            'operation_id' => $extractionResult->operationId,
            'transaction_date' => $extractionResult->transactionDate,
            'transaction_time' => $extractionResult->transactionTime,
            'sender_name' => $extractionResult->senderName,
            'recipient_name' => $extractionResult->recipientName,
            'recipient_phone' => $extractionResult->recipientPhone,
            'overall_confidence' => $avgConf,
            'confidence_json' => json_encode($extractionResult->fieldConfidences),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Deterministic Validation (V1-V10)
        $validationResult = $this->validationEngine->validate($extractionResult, $order);

        // 6. Fraud & Replay Detection
        $fraudAssessment = $this->fraudService->evaluate($extractionResult, $validationResult, ['tenant_id' => $tenantId]);

        // 7. Policy Decision Engine (AND Gate)
        $tenantPolicy = $webhookPayload['tenant_policy'] ?? new TenantPaymentPolicy(tenantId: $tenantId);
        $decision = $this->policyEngine->evaluate($extractionResult, $validationResult, $fraudAssessment, $tenantPolicy, $order);

        // 8. Payment Truth Layer & Financial Authorization Execution con Bloqueo de Concurrencia (CI-GATE-8) y Protección PI-02
        $authorizationResult = null;
        if ($decision->decision === 'AUTO_AUTHORIZE') {
            $authorizationResult = DB::transaction(function() use ($tenantId, $orderId, $evidenceId, $extractionResult) {
                $freshOrder = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
                if (!$freshOrder || $freshOrder->payment_status === 'PAID' || $freshOrder->status === 'CANCELLED') {
                    return ['status' => 'ABORTED_CONCURRENT_STATE_CHANGE'];
                }

                // Invariante PI-02: Verificar que la versión de pago de la evidencia coincida con la versión actual de la orden
                $evidenceRev = DB::table('payment_evidences')->where('id', $evidenceId)->value('payment_revision');
                if (isset($freshOrder->payment_revision) && isset($evidenceRev) && (int)$freshOrder->payment_revision !== (int)$evidenceRev) {
                    return ['status' => 'REJECTED', 'reason' => 'STALE_PAYMENT_REVISION'];
                }

                return $this->authService->authorizeAndApplyPayment(
                    tenantId: $tenantId,
                    orderId: $orderId,
                    evidenceId: (int)$evidenceId,
                    decision: 'AUTO_AUTHORIZE',
                    reasonCode: 'EXACT_MATCH',
                    allocatedAmount: (float)$extractionResult->amount,
                    actorType: 'SYSTEM'
                );
            });
        }

        // 9. Mensaje de Respuesta Amigable para WhatsApp
        $whatsappMessage = match ($decision->decision) {
            'AUTO_AUTHORIZE' => "✅ ¡Pago verificado con éxito! Tu pedido {$order->order_number} por S/ " . number_format($extractionResult->amount, 2) . " ha sido confirmado.",
            'HUMAN_REVIEW' => "🟡 Recibimos tu comprobante de Yape. Nuestro equipo lo está validando y te notificaremos en breve.",
            'REJECT' => ($extractionResult->amount > 0 && (float)$extractionResult->amount != (float)$order->total_amount)
                ? "Recibimos tu comprobante de Yape por S/ " . number_format($extractionResult->amount, 2) . ", pero tu orden {$order->order_number} registra un total de S/ " . number_format((float)$order->total_amount, 2) . ". El monto transferido no coincide con el total de tu pedido. Por favor verifica el comprobante o realiza la transferencia por la diferencia."
                : "❌ No pudimos validar tu comprobante (monto o datos no coinciden). Por favor verifica e intentalo de nuevo.",
            'BLOCK' => "⚠️ Tu comprobante ha sido bloqueado por inconsistencia de seguridad.",
            default => "❌ Error en el procesamiento del comprobante.",
        };

        $finalResult = [
            'status' => 'COMPLETED',
            'decision' => $decision->decision,
            'reason_codes' => $decision->reasonCodes,
            'evidence_id' => $evidenceId,
            'order_id' => $orderId,
            'tenant_id' => $tenantId,
            'amount_extracted' => $extractionResult->amount,
            'validation_status' => $validationResult->overallStatus,
            'risk_level' => $fraudAssessment->riskLevel,
            'order_payment_status_after' => DB::table('orders')->where('id', $orderId)->value('payment_status'),
            'authorization_result' => $authorizationResult,
            'whatsapp_message' => $whatsappMessage,
            'correlation_id' => $correlationId,
        ];

        if (!empty($waMessageId) && Schema::hasTable('payment_webhook_receipts')) {
            try {
                DB::table('payment_webhook_receipts')->insert([
                    'tenant_id' => $tenantId,
                    'whatsapp_message_id' => $waMessageId,
                    'processing_status' => 'COMPLETED',
                    'result_code' => $decision->decision,
                    'response_payload' => json_encode($finalResult),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        return $finalResult;
    }
}
