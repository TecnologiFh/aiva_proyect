<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;
use App\Modules\Payments\Domain\ValueObjects\ValidationResult;
use App\Modules\Payments\Domain\ValueObjects\FraudAssessment;
use App\Modules\Payments\Domain\Enums\PaymentReasonCode;

class FraudDetectionService
{
    /**
     * Evalúa determinísticamente señales de fraude, reanimación (replay) y similitud visual.
     * Genera un FraudAssessment sin mutar estados de pago de pedidos.
     */
    public function evaluate(
        CanonicalPaymentEvidence $evidence,
        ?ValidationResult $validationResult = null,
        array $context = []
    ): FraudAssessment {
        $tenantId = $evidence->tenantId;
        $evidenceId = $evidence->evidenceId;
        $riskScore = 0;
        $flags = [];
        $reasonCodes = [];
        $replayDetected = false;
        $visualSimilarityDetected = false;

        // -------------------------------------------------------------
        // F1: Detección de duplicado exacto por SHA-256 (Mismo Tenant)
        // -------------------------------------------------------------
        $shaDuplicate = DB::table('payment_evidences')
            ->where('tenant_id', $tenantId)
            ->where('sha256', $evidence->sha256)
            ->where('id', '!=', (is_numeric($evidenceId) ? $evidenceId : 0))
            ->first();

        if ($shaDuplicate) {
            $riskScore += 6;
            $replayDetected = true;
            $flags[] = 'EXACT_SHA_REPLAY';
            $reasonCodes[] = PaymentReasonCode::DUPLICATE_OPERATION->value;
        }

        // -------------------------------------------------------------
        // F2: Detección de reutilización de N° de Operación (Mismo Tenant)
        // -------------------------------------------------------------
        if (!empty($evidence->operationId)) {
            $opDuplicate = DB::table('payment_extractions')
                ->where('operation_id', $evidence->operationId)
                ->where('payment_evidence_id', '!=', (is_numeric($evidenceId) ? $evidenceId : 0))
                ->first();

            if ($opDuplicate) {
                $riskScore += 6;
                $replayDetected = true;
                $flags[] = 'OPERATION_ID_REPLAY';
                if (!in_array(PaymentReasonCode::DUPLICATE_OPERATION->value, $reasonCodes, true)) {
                    $reasonCodes[] = PaymentReasonCode::DUPLICATE_OPERATION->value;
                }
            }
        }

        // -------------------------------------------------------------
        // F3: Similitud Visual dHash/pHash (Hamming Distance <= 5)
        // -------------------------------------------------------------
        if (!empty($evidence->phash)) {
            $pastEvidences = DB::table('payment_evidences')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('phash')
                ->where('id', '!=', (is_numeric($evidenceId) ? $evidenceId : 0))
                ->limit(50)
                ->get();

            foreach ($pastEvidences as $pastEv) {
                $distance = $this->calculateHammingDistance($evidence->phash, $pastEv->phash);
                if ($distance <= 5) {
                    $riskScore += 3;
                    $visualSimilarityDetected = true;
                    $flags[] = 'VISUAL_SIMILARITY_MATCH';
                    $reasonCodes[] = PaymentReasonCode::FRAUD_SIGNAL->value;
                    break;
                }
            }
        }

        // -------------------------------------------------------------
        // F4: Reutilización Cross-Tenant (Violación Estricta Multi-Tenant)
        // -------------------------------------------------------------
        $crossTenantSha = DB::table('payment_evidences')
            ->where('tenant_id', '!=', $tenantId)
            ->where('sha256', $evidence->sha256)
            ->first();

        if ($crossTenantSha) {
            $riskScore += 10;
            $replayDetected = true;
            $flags[] = 'CROSS_TENANT_REUSE_BLOCKED';
            $reasonCodes[] = PaymentReasonCode::TENANT_MISMATCH->value;
        }

        // -------------------------------------------------------------
        // F5: Indicadores de Alteración o Anomalías Visuales / Bajas Confianzas
        // -------------------------------------------------------------
        $confidences = $evidence->fieldConfidences;
        if (isset($confidences['amount']) && $confidences['amount'] < 0.85) {
            $riskScore += 2;
            $flags[] = 'LOW_AMOUNT_CONFIDENCE';
            $reasonCodes[] = PaymentReasonCode::LOW_CONFIDENCE->value;
        }

        // -------------------------------------------------------------
        // F6: Mapeo Determinístico de Risk Score & Nivel de Riesgo
        // -------------------------------------------------------------
        if ($riskScore <= 2) {
            $riskLevel = 'CLEAR';
            $decision = 'CLEAR';
        } elseif ($riskScore <= 5) {
            $riskLevel = 'SUSPICIOUS';
            $decision = 'SUSPICIOUS';
        } else {
            $riskLevel = 'HIGH_RISK';
            $decision = 'BLOCKED';
        }

        // Registrar evento de auditoría de fraude
        try {
            $numericEvidenceId = is_numeric($evidenceId) ? (int)$evidenceId : 1;
            DB::table('payment_audit_events')->insert([
                'tenant_id' => $tenantId,
                'payment_evidence_id' => $numericEvidenceId,
                'event_type' => 'FRAUD_ASSESSMENT_COMPLETED',
                'actor_type' => 'SYSTEM',
                'metadata_json' => json_encode([
                    'evidence_id' => $evidenceId,
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'decision' => $decision,
                    'replay_detected' => $replayDetected,
                    'visual_similarity' => $visualSimilarityDetected,
                    'flags' => $flags,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return new FraudAssessment(
            evidenceId: (string)$evidenceId,
            tenantId: $tenantId,
            riskScore: $riskScore,
            riskLevel: $riskLevel,
            decision: $decision,
            replayDetected: $replayDetected,
            visualSimilarityDetected: $visualSimilarityDetected,
            flags: $flags,
            reasonCodes: array_unique($reasonCodes),
            metadata: ['evaluated_rules' => ['F1', 'F2', 'F3', 'F4', 'F5', 'F6']]
        );
    }

    /**
     * Calcula la distancia de Hamming entre dos cadenas hexadecimales de igual longitud.
     */
    protected function calculateHammingDistance(string $hex1, string $hex2): int
    {
        $bin1 = '';
        $bin2 = '';

        for ($i = 0; $i < min(strlen($hex1), strlen($hex2)); $i++) {
            $bin1 .= str_pad(base_convert($hex1[$i], 16, 2), 4, '0', STR_PAD_LEFT);
            $bin2 .= str_pad(base_convert($hex2[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $distance = 0;
        for ($i = 0; $i < min(strlen($bin1), strlen($bin2)); $i++) {
            if ($bin1[$i] !== $bin2[$i]) {
                $distance++;
            }
        }

        return $distance;
    }
}
