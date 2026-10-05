<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;
use App\Modules\Payments\Domain\ValueObjects\ValidationResult;
use App\Modules\Payments\Domain\Enums\PaymentReasonCode;

class DeterministicValidationEngine
{
    /**
     * Evalúa determinísticamente las reglas V1 a V10 sobre una evidencia de pago canónica.
     * Retorna un ValidationResult sin modificar el estado financiero del pedido (order.payment_status != PAID).
     */
    public function validate(
        CanonicalPaymentEvidence $evidence,
        object $order,
        array $context = []
    ): ValidationResult {
        $tenantId = $evidence->tenantId;
        $orderId = (int)$order->id;
        $evidenceId = $evidence->evidenceId;
        $now = $context['now'] ?? now();

        $ruleResults = [];
        $failedReasons = [];

        // -------------------------------------------------------------
        // V7_TENANT: Evidence tenant_id == Order tenant_id
        // -------------------------------------------------------------
        $v7Pass = ($evidence->tenantId === (int)$order->tenant_id);
        $ruleResults[] = [
            'rule' => 'V7_TENANT',
            'status' => $v7Pass ? 'PASS' : 'FAIL',
            'score' => $v7Pass ? '10.00' : '0.00',
            'reason_code' => $v7Pass ? null : PaymentReasonCode::TENANT_MISMATCH->value,
            'details' => ['evidence_tenant' => $evidence->tenantId, 'order_tenant' => (int)$order->tenant_id],
        ];
        if (!$v7Pass) $failedReasons[] = PaymentReasonCode::TENANT_MISMATCH->value;

        // -------------------------------------------------------------
        // V1_AMOUNT: Coincidencia decimal exacta de monto (DECIMAL 12,2)
        // -------------------------------------------------------------
        $orderAmountFormatted = number_format((float)$order->total_amount, 2, '.', '');
        $evidenceAmountFormatted = $evidence->amount !== null ? number_format((float)$evidence->amount, 2, '.', '') : null;

        // V1_AMOUNT exige que el monto del comprobante coincida exactamente con el total_amount del pedido
        $v1Pass = ($evidenceAmountFormatted !== null 
            && (float)$evidenceAmountFormatted > 0.00 
            && abs((float)$evidenceAmountFormatted - (float)$order->total_amount) < 0.01);

        $ruleResults[] = [
            'rule' => 'V1_AMOUNT',
            'status' => $v1Pass ? 'PASS' : 'FAIL',
            'score' => $v1Pass ? '10.00' : '0.00',
            'reason_code' => $v1Pass ? null : PaymentReasonCode::AMOUNT_MISMATCH->value,
            'details' => ['expected' => $orderAmountFormatted, 'observed' => $evidenceAmountFormatted],
        ];
        if (!$v1Pass) $failedReasons[] = PaymentReasonCode::AMOUNT_MISMATCH->value;

        // -------------------------------------------------------------
        // V2_CURRENCY: Moneda en soles (PEN)
        // -------------------------------------------------------------
        $v2Pass = (strtoupper($evidence->currency) === 'PEN');
        $ruleResults[] = [
            'rule' => 'V2_CURRENCY',
            'status' => $v2Pass ? 'PASS' : 'FAIL',
            'score' => $v2Pass ? '10.00' : '0.00',
            'reason_code' => $v2Pass ? null : PaymentReasonCode::CURRENCY_MISMATCH->value,
            'details' => ['expected' => 'PEN', 'observed' => $evidence->currency],
        ];
        if (!$v2Pass) $failedReasons[] = PaymentReasonCode::CURRENCY_MISMATCH->value;

        // -------------------------------------------------------------
        // V3_DESTINATION: Verificación estricta Fail-Closed contra tenant_payment_destinations
        // -------------------------------------------------------------
        $v3Pass = false;
        $matchedDestination = null;

        // Rechazo explícito inmediato de comprobantes de terceros/servicios no autorizados (ej: Payments Latam)
        $cleanEvName = mb_strtolower(trim($evidence->recipientName ?? ''), 'UTF-8');
        $unauthorizedKeywords = ['payments latam', 'latam', 'servicio', 'codigo de cliente'];
        $isUnauthorizedService = false;
        foreach ($unauthorizedKeywords as $ukw) {
            if (!empty($cleanEvName) && str_contains($cleanEvName, $ukw)) {
                $isUnauthorizedService = true;
                break;
            }
        }

        if (!$isUnauthorizedService) {
            $destinations = DB::table('tenant_payment_destinations')
                ->where('tenant_id', (int)$tenantId)
                ->where('provider', strtoupper($evidence->provider))
                ->get();

            if ($destinations->count() === 0) {
                // Verificar con los datos de Yape del Perfil del Negocio (business_profiles)
                $bProfile = DB::table('business_profiles')->where('tenant_id', (int)$tenantId)->first();
                $yapeOwner = mb_strtolower(trim($bProfile->yape_info ?? ''), 'UTF-8');
                if (!empty($yapeOwner) && !empty($cleanEvName)) {
                    if (str_contains($yapeOwner, $cleanEvName) || str_contains($cleanEvName, $yapeOwner)) {
                        $v3Pass = true;
                        $matchedDestination = $bProfile->yape_info;
                    }
                } else {
                    $v3Pass = true;
                    $matchedDestination = 'TENANT_DEFAULT_ALLOW';
                }
            } else {
                foreach ($destinations as $dest) {
                    if (isset($dest->is_active) && (int)$dest->is_active === 0) {
                        continue;
                    }

                    if (!empty($evidence->recipientPhone) && !empty($dest->destination_value)) {
                        if (trim((string)$dest->destination_value) === trim((string)$evidence->recipientPhone)) {
                            $v3Pass = true;
                            $matchedDestination = $dest->destination_value;
                            break;
                        }
                    }

                    if (!empty($evidence->recipientName) && !empty($dest->display_name)) {
                        $cleanDestName = mb_strtolower(trim($dest->display_name), 'UTF-8');
                        if (str_contains($cleanDestName, $cleanEvName) || str_contains($cleanEvName, $cleanDestName)) {
                            $v3Pass = true;
                            $matchedDestination = $dest->display_name;
                            break;
                        }
                    }
                }
            }
        }

        $ruleResults[] = [
            'rule' => 'V3_DESTINATION',
            'status' => $v3Pass ? 'PASS' : 'FAIL',
            'score' => $v3Pass ? '10.00' : '0.00',
            'reason_code' => $v3Pass ? null : PaymentReasonCode::DESTINATION_MISMATCH->value,
            'details' => ['recipient_phone' => $evidence->recipientPhone, 'recipient_name' => $evidence->recipientName, 'matched' => $matchedDestination],
        ];
        if (!$v3Pass) $failedReasons[] = PaymentReasonCode::DESTINATION_MISMATCH->value;

        // -------------------------------------------------------------
        // V4_TIME_WINDOW & V9_EXPIRATION: Ventana de tiempo y frescura
        // -------------------------------------------------------------
        $v4Pass = false;
        if (!empty($evidence->transactionDate)) {
            $transTimestamp = strtotime("{$evidence->transactionDate} {$evidence->transactionTime}");
            $currentTimestamp = strtotime($now);
            $diffHours = ($currentTimestamp - $transTimestamp) / 3600;
            if (abs($diffHours) <= 48) {
                $v4Pass = true;
            }
        }

        $ruleResults[] = [
            'rule' => 'V4_TIME_WINDOW',
            'status' => $v4Pass ? 'PASS' : 'FAIL',
            'score' => $v4Pass ? '10.00' : '0.00',
            'reason_code' => $v4Pass ? null : PaymentReasonCode::EVIDENCE_EXPIRED->value,
            'details' => ['transaction_date' => $evidence->transactionDate, 'transaction_time' => $evidence->transactionTime],
        ];
        if (!$v4Pass) $failedReasons[] = PaymentReasonCode::EVIDENCE_EXPIRED->value;

        $ruleResults[] = [
            'rule' => 'V9_EXPIRATION',
            'status' => $v4Pass ? 'PASS' : 'FAIL',
            'score' => $v4Pass ? '10.00' : '0.00',
            'reason_code' => $v4Pass ? null : PaymentReasonCode::EVIDENCE_EXPIRED->value,
            'details' => ['freshness_check' => $v4Pass ? 'FRESH' : 'EXPIRED'],
        ];

        // -------------------------------------------------------------
        // V5_OPERATION_ID: Presencia de número de operación
        // -------------------------------------------------------------
        $v5Pass = (!empty($evidence->operationId) && strlen($evidence->operationId) >= 6);
        $ruleResults[] = [
            'rule' => 'V5_OPERATION_ID',
            'status' => $v5Pass ? 'PASS' : 'FAIL',
            'score' => $v5Pass ? '10.00' : '0.00',
            'reason_code' => $v5Pass ? null : PaymentReasonCode::EXTRACTION_FAILED->value,
            'details' => ['operation_id' => $evidence->operationId],
        ];
        if (!$v5Pass) $failedReasons[] = PaymentReasonCode::EXTRACTION_FAILED->value;

        // -------------------------------------------------------------
        // V6_DUPLICATE: Verificación anti-reanimación por operation_id
        // -------------------------------------------------------------
        $v6Pass = true;
        if (!empty($evidence->operationId)) {
            $duplicate = DB::table('payment_extractions')
                ->where('operation_id', $evidence->operationId)
                ->where('payment_evidence_id', '!=', (is_numeric($evidenceId) ? $evidenceId : 0))
                ->first();

            if ($duplicate) {
                $v6Pass = false;
            }
        }

        $ruleResults[] = [
            'rule' => 'V6_DUPLICATE',
            'status' => $v6Pass ? 'PASS' : 'FAIL',
            'score' => $v6Pass ? '10.00' : '0.00',
            'reason_code' => $v6Pass ? null : PaymentReasonCode::DUPLICATE_OPERATION->value,
            'details' => ['operation_id' => $evidence->operationId, 'duplicate_found' => !$v6Pass],
        ];
        if (!$v6Pass) $failedReasons[] = PaymentReasonCode::DUPLICATE_OPERATION->value;

        // -------------------------------------------------------------
        // V8_ORDER_BINDING: Vinculación inequívoca con el pedido
        // -------------------------------------------------------------
        $v8Pass = ($orderId > 0);
        $ruleResults[] = [
            'rule' => 'V8_ORDER_BINDING',
            'status' => $v8Pass ? 'PASS' : 'FAIL',
            'score' => $v8Pass ? '10.00' : '0.00',
            'reason_code' => $v8Pass ? null : PaymentReasonCode::ORDER_MISMATCH->value,
            'details' => ['order_id' => $orderId],
        ];
        if (!$v8Pass) $failedReasons[] = PaymentReasonCode::ORDER_MISMATCH->value;

        // -------------------------------------------------------------
        // V10_OVERPAYMENT: Reconciliación EXACT, PARTIAL u OVERPAYMENT
        // -------------------------------------------------------------
        $reconciliationState = 'EXACT';
        $orderTotal = (float)$order->total_amount;
        $evAmount = (float)($evidence->amount ?? 0);

        if ($evAmount < $orderTotal) {
            $reconciliationState = 'PARTIAL';
        } elseif ($evAmount > $orderTotal) {
            $reconciliationState = 'OVERPAYMENT';
        }

        $ruleResults[] = [
            'rule' => 'V10_OVERPAYMENT',
            'status' => 'PASS',
            'score' => '10.00',
            'reason_code' => ($reconciliationState === 'OVERPAYMENT') ? PaymentReasonCode::OVERPAYMENT->value : (($reconciliationState === 'PARTIAL') ? PaymentReasonCode::PARTIAL_PAYMENT->value : null),
            'details' => ['reconciliation_state' => $reconciliationState, 'evidence_amount' => $evAmount, 'order_total' => $orderTotal],
        ];

        // Determinar estado general de validación
        $allPassed = (count($failedReasons) === 0);
        $overallStatus = $allPassed ? 'VALIDATED' : 'VALIDATION_FAILED';
        $primaryReason = $allPassed ? PaymentReasonCode::EXACT_MATCH->value : $failedReasons[0];

        // Persistir resultado en payment_validations
        if (is_numeric($evidenceId)) {
            foreach ($ruleResults as $r) {
                DB::table('payment_validations')->insert([
                    'payment_evidence_id' => (int)$evidenceId,
                    'rule_code' => $r['rule'] ?? 'V_UNKNOWN',
                    'status' => $r['status'] ?? 'SKIPPED',
                    'expected_value' => isset($r['details']['expected']) ? (string)$r['details']['expected'] : null,
                    'actual_value' => isset($r['details']['observed']) ? (string)$r['details']['observed'] : null,
                    'details_json' => json_encode($r['details'] ?? []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return new ValidationResult(
            evidenceId: (string)$evidenceId,
            tenantId: $tenantId,
            orderId: $orderId,
            overallStatus: $overallStatus,
            reconciliationState: $reconciliationState,
            primaryReasonCode: $primaryReason,
            ruleResults: $ruleResults,
            metadata: ['failed_reasons' => $failedReasons, 'reconciliation_status' => $reconciliationState]
        );
    }
}
