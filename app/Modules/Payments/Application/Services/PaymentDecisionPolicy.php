<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;
use App\Modules\Payments\Domain\ValueObjects\ValidationResult;
use App\Modules\Payments\Domain\ValueObjects\FraudAssessment;
use App\Modules\Payments\Domain\ValueObjects\TenantPaymentPolicy;
use App\Modules\Payments\Domain\ValueObjects\PaymentDecision;
use App\Modules\Payments\Domain\Enums\PaymentReasonCode;

class PaymentDecisionPolicy
{
    /**
     * Evalúa las 3 verdades (ValidationResult + FraudAssessment + Reconciliation)
     * junto a las políticas financieras del tenant para emitir un PaymentDecision inmutable.
     * 
     * RESTRICCIÓN DE SEGURIDAD ABSOLUTA:
     * Este servicio JAMÁS muta orders.payment_status a PAID.
     */
    public function evaluate(
        CanonicalPaymentEvidence $evidence,
        ValidationResult $validationResult,
        FraudAssessment $fraudAssessment,
        ?TenantPaymentPolicy $tenantPolicy = null,
        ?object $order = null
    ): PaymentDecision {
        $tenantId = $evidence->tenantId;
        $tenantPolicy = $tenantPolicy ?? TenantPaymentPolicy::defaultForTenant($tenantId);
        $reconciliationStatus = $validationResult->metadata['reconciliation_status'] ?? 'UNVERIFIED';
        $orderId = $order->id ?? null;

        $reasonCodes = [];
        $decision = null;

        // -------------------------------------------------------------
        // 1. Bloqueo Global de Seguridad (BLOCK)
        // -------------------------------------------------------------
        if ($fraudAssessment->riskLevel === 'HIGH_RISK' ||
            in_array('CROSS_TENANT_REUSE_BLOCKED', $fraudAssessment->flags, true) ||
            in_array('EXACT_SHA_REPLAY', $fraudAssessment->flags, true)) {
            $decision = 'BLOCK';
            $reasonCodes = array_unique(array_merge($fraudAssessment->reasonCodes, ['HIGH_RISK']));
        }

        // -------------------------------------------------------------
        // 2. Rechazo Determinístico de Validación (REJECT)
        // -------------------------------------------------------------
        if ($decision === null && $validationResult->overallStatus === 'VALIDATION_FAILED') {
            $decision = 'REJECT';
            $failedReasons = $validationResult->metadata['failed_reasons'] ?? ($validationResult->primaryReasonCode ? [$validationResult->primaryReasonCode] : []);
            $reasonCodes = array_unique(array_merge($failedReasons, ['VALIDATION_FAILED']));
        }

        // -------------------------------------------------------------
        // 3. Evaluador de Condiciones para Autorización vs Human Review
        // -------------------------------------------------------------
        if ($decision === null) {
            // Evaluaciones de Riesgo y Similitud
            if ($fraudAssessment->riskLevel === 'SUSPICIOUS' || $fraudAssessment->visualSimilarityDetected) {
                $reasonCodes[] = PaymentReasonCode::FRAUD_SIGNAL->value;
            }

            // Evaluaciones de Reconciliación
            if ($reconciliationStatus === 'PARTIAL_PAYMENT' && !$tenantPolicy->allowPartialPayment) {
                $reasonCodes[] = PaymentReasonCode::PARTIAL_PAYMENT->value;
            }
            if ($reconciliationStatus === 'OVERPAYMENT' && !$tenantPolicy->allowOverpayment) {
                $reasonCodes[] = PaymentReasonCode::OVERPAYMENT->value;
            }
            if ($reconciliationStatus === 'UNVERIFIED') {
                $reasonCodes[] = PaymentReasonCode::PAYMENT_UNVERIFIED->value;
            }

            // Evaluaciones de Política del Tenant
            if (!$tenantPolicy->autoAuthorizationEnabled) {
                $reasonCodes[] = 'AUTO_AUTHORIZATION_DISABLED';
            }
            if ($evidence->amount > $tenantPolicy->maxAutoPaymentAmount) {
                $reasonCodes[] = 'AMOUNT_EXCEEDS_MAX_LIMIT';
            }
            if (!in_array(strtoupper($evidence->provider), array_map('strtoupper', $tenantPolicy->allowedProviders), true)) {
                $reasonCodes[] = 'PROVIDER_NOT_ALLOWED';
            }

            // Evaluaciones de Confianza
            $confidences = $evidence->fieldConfidences;
            if (isset($confidences['amount']) && $confidences['amount'] < $tenantPolicy->requiredConfidenceAmount) {
                $reasonCodes[] = PaymentReasonCode::LOW_CONFIDENCE->value;
            }

            // AND Gate para AUTO_AUTHORIZE
            if (empty($reasonCodes) &&
                $validationResult->overallStatus === 'VALIDATED' &&
                $fraudAssessment->riskLevel === 'CLEAR' &&
                $reconciliationStatus === 'EXACT' &&
                $tenantPolicy->autoAuthorizationEnabled) {
                $decision = 'AUTO_AUTHORIZE';
            } else {
                $decision = 'HUMAN_REVIEW';
                if (empty($reasonCodes)) {
                    $reasonCodes[] = 'MANUAL_REVIEW_REQUIRED';
                }
            }
        }

        $reasonCodes = array_values(array_unique($reasonCodes));

        $paymentDecision = new PaymentDecision(
            decision: $decision,
            reasonCodes: $reasonCodes,
            riskLevel: $fraudAssessment->riskLevel,
            validationStatus: $validationResult->overallStatus,
            reconciliationStatus: $reconciliationStatus,
            provider: $evidence->provider,
            tenantId: $tenantId,
            evidenceId: $evidence->evidenceId,
            orderId: $orderId,
            policyVersion: '1.0',
            createdAt: date('Y-m-d H:i:s')
        );

        // Persistir decisión en payment_decisions para auditoría financiera
        try {
            $numericEvidenceId = is_numeric($evidence->evidenceId) ? (int)$evidence->evidenceId : 1;
            DB::table('payment_decisions')->insert([
                'payment_evidence_id' => $numericEvidenceId,
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'decision' => $decision,
                'reason_code' => $reasonCodes[0] ?? 'NONE',
                'risk_score' => $fraudAssessment->riskScore,
                'actor_type' => 'SYSTEM_POLICY',
                'actor_id' => 1,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return $paymentDecision;
    }
}
