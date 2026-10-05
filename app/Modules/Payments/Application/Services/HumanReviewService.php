<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Payments\Domain\ValueObjects\PaymentDecision;
use App\Modules\Payments\Domain\Enums\PaymentReasonCode;

class HumanReviewService
{
    /**
     * Obtiene el listado de la bandeja de entrada (Inbox) con aislamiento multi-tenant estricto y filtros.
     */
    public function getInbox(int $tenantId, array $filters = []): array
    {
        $query = DB::table('payment_decisions')
            ->join('payment_evidences', 'payment_decisions.payment_evidence_id', '=', 'payment_evidences.id')
            ->where('payment_decisions.tenant_id', $tenantId)
            ->where('payment_decisions.decision', 'HUMAN_REVIEW')
            ->select(
                'payment_decisions.id as decision_id',
                'payment_decisions.payment_evidence_id',
                'payment_decisions.tenant_id',
                'payment_decisions.order_id',
                'payment_decisions.decision',
                'payment_decisions.reason_code',
                'payment_decisions.risk_score',
                'payment_decisions.created_at as decision_time',
                'payment_evidences.provider',
                'payment_evidences.sha256',
                'payment_evidences.phash',
                'payment_evidences.status as evidence_status'
            );

        if (!empty($filters['reason_code'])) {
            $query->where('payment_decisions.reason_code', $filters['reason_code']);
        }
        if (!empty($filters['provider'])) {
            $query->where('payment_evidences.provider', strtoupper($filters['provider']));
        }

        return $query->orderBy('payment_decisions.id', 'desc')->get()->toArray();
    }

    /**
     * Retorna el detalle completo de las 3 verdades y el historial append-only de decisiones para un comprobante.
     */
    public function getEvidenceReviewDetail(int $tenantId, int $evidenceId): array
    {
        $evidence = DB::table('payment_evidences')
            ->where('tenant_id', $tenantId)
            ->where('id', $evidenceId)
            ->first();

        if (!$evidence) {
            throw new \InvalidArgumentException("Evidencia no encontrada o sin acceso multi-tenant.");
        }

        $extraction = DB::table('payment_extractions')
            ->where('payment_evidence_id', $evidenceId)
            ->first();

        $validations = DB::table('payment_validations')
            ->where('payment_evidence_id', $evidenceId)
            ->get();

        $allPassed = count($validations) > 0 && $validations->every(fn($v) => $v->status === 'PASS');

        $decisionHistory = DB::table('payment_decisions')
            ->where('payment_evidence_id', $evidenceId)
            ->orderBy('id', 'asc')
            ->get();

        $auditTrail = DB::table('payment_audit_events')
            ->where('payment_evidence_id', $evidenceId)
            ->orderBy('id', 'asc')
            ->get();

        return [
            'evidence_truth' => [
                'id' => $evidence->id,
                'tenant_id' => $evidence->tenant_id,
                'provider' => $evidence->provider,
                'sha256' => $evidence->sha256,
                'phash' => $evidence->phash,
                'source' => $evidence->source,
                'status' => $evidence->status,
                'raw_ocr' => $extraction ? json_decode($extraction->raw_ocr_json ?? '{}', true) : [],
            ],
            'extraction_truth' => [
                'amount' => $extraction->amount ?? null,
                'currency' => $extraction->currency ?? null,
                'operation_id' => $extraction->operation_id ?? null,
                'sender_name' => $extraction->sender_name ?? null,
                'recipient_name' => $extraction->recipient_name ?? null,
                'recipient_phone' => $extraction->recipient_phone ?? null,
            ],
            'validation_truth' => [
                'status' => $allPassed ? 'VALIDATED' : 'VALIDATION_FAILED',
                'reconciliation_state' => 'EXACT',
                'rule_results' => $validations->toArray(),
            ],
            'decision_history' => $decisionHistory,
            'audit_trail' => $auditTrail,
        ];
    }

    /**
     * Registra una decisión humana inmutable (append-only) sin mutar órdenes a PAID.
     */
    public function recordHumanDecision(
        int $tenantId,
        int $evidenceId,
        string $humanDecision, // 'HUMAN_APPROVED' | 'HUMAN_REJECTED' | 'RE_EVIDENCE_REQUESTED' | 'ESCALATED'
        string $operatorId,
        string $notes = ''
    ): array {
        $allowedDecisions = ['HUMAN_APPROVED', 'HUMAN_REJECTED', 'RE_EVIDENCE_REQUESTED', 'ESCALATED'];
        if (!in_array($humanDecision, $allowedDecisions, true)) {
            throw new \InvalidArgumentException("Decisión humana no permitida: {$humanDecision}");
        }

        $evidence = DB::table('payment_evidences')
            ->where('tenant_id', $tenantId)
            ->where('id', $evidenceId)
            ->first();

        if (!$evidence) {
            throw new \InvalidArgumentException("Evidencia no encontrada o sin acceso multi-tenant.");
        }

        $numericActorId = (int)preg_replace('/\D/', '', $operatorId);
        if ($numericActorId === 0) $numericActorId = 1;

        // Insertar nueva fila append-only en payment_decisions
        $decisionId = DB::table('payment_decisions')->insertGetId([
            'payment_evidence_id' => $evidenceId,
            'tenant_id' => $tenantId,
            'order_id' => null,
            'decision' => $humanDecision,
            'reason_code' => $humanDecision === 'HUMAN_APPROVED' ? 'NONE' : 'HUMAN_OVERRIDE_REJECT',
            'risk_score' => 0,
            'actor_type' => 'HUMAN_OPERATOR',
            'actor_id' => $numericActorId,
            'created_at' => now(),
        ]);

        // Registrar evento de auditoría append-only
        DB::table('payment_audit_events')->insert([
            'tenant_id' => $tenantId,
            'payment_evidence_id' => $evidenceId,
            'event_type' => 'HUMAN_REVIEW_DECISION_RECORDED',
            'actor_type' => 'HUMAN_OPERATOR',
            'metadata_json' => json_encode([
                'operator_id' => $operatorId,
                'human_decision' => $humanDecision,
                'notes' => $notes,
                'decision_id' => $decisionId,
            ]),
            'created_at' => now(),
        ]);

        // FRONTERA DE SEGURIDAD ABSOLUTA: VERIFICACIÓN DE NO MUTACIÓN PAIDA
        // orders.payment_status NO es modificado por HumanReviewService.

        return [
            'status' => 'SUCCESS',
            'decision_id' => $decisionId,
            'human_decision' => $humanDecision,
            'operator_id' => $operatorId,
            'payment_status_mutated_to_paid' => false,
        ];
    }
}
