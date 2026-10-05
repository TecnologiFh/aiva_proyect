<?php

namespace App\Modules\Payments\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Orders\Services\OrderStateMachineEngine;
use App\Modules\Payments\Domain\Enums\EvidenceStatus;
use App\Modules\Payments\Domain\Enums\PaymentReasonCode;

class PaymentAuthorizationService
{
    protected OrderStateMachineEngine $orderStateMachine;

    public function __construct(?OrderStateMachineEngine $orderStateMachine = null)
    {
        $this->orderStateMachine = $orderStateMachine ?? new OrderStateMachineEngine();
    }

    /**
     * Única ruta de código autorizada en todo el sistema AIVA para autorizar un pago y marcar la orden como PAID.
     */
    public function authorizeAndApplyPayment(
        int $tenantId,
        int $orderId,
        int $evidenceId,
        string $decision,
        string $reasonCode,
        float $allocatedAmount,
        string $actorType = 'SYSTEM',
        ?int $actorId = null
    ): array {
        return DB::transaction(function () use ($tenantId, $orderId, $evidenceId, $decision, $reasonCode, $allocatedAmount, $actorType, $actorId) {
            // 1. Bloqueo atómico SELECT FOR UPDATE del pedido
            $order = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return [
                    'status' => 'ERROR',
                    'message' => "Pedido #{$orderId} no encontrado para el Tenant {$tenantId}.",
                ];
            }

            // 2. Verificar evidencia
            $evidence = DB::table('payment_evidences')
                ->where('id', $evidenceId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$evidence) {
                return [
                    'status' => 'ERROR',
                    'message' => "Evidencia de pago #{$evidenceId} no encontrada.",
                ];
            }

            // Invariante PI-GATE-2: Atomic CAS Payment State Transition — Exactly Once
            $evRevision = isset($evidence->payment_revision) ? (int)$evidence->payment_revision : (int)($order->payment_revision ?? 1);
            $affected = DB::table('orders')
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->where('payment_status', 'PENDING')
                ->where('payment_revision', $evRevision)
                ->update([
                    'payment_status' => 'PAID',
                    'status' => 'CONFIRMED',
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                return [
                    'status' => 'ABORTED',
                    'reason_code' => PaymentReasonCode::DUPLICATE_OPERATION->value,
                    'message' => 'ATOMIC_CAS_AUTHORIZATION_LOST: La transición atómica falló. El pedido ya fue autorizado o su versión de pago (payment_revision) expiró.',
                ];
            }

            // Subtest PAY33-G: Prevenir reutilización de una decisión previa
            $existingAllocation = DB::table('payment_allocations')
                ->where('payment_evidence_id', $evidenceId)
                ->where('order_id', $orderId)
                ->where('status', 'APPLIED')
                ->first();

            if ($existingAllocation) {
                return [
                    'status' => 'ERROR',
                    'reason_code' => PaymentReasonCode::DUPLICATE_OPERATION->value,
                    'message' => 'PAY33 Security Violation: Esta evidencia de pago ya fue aplicada anteriormente.',
                ];
            }

            $orderTotal = (float)$order->total_amount;
            $remainingAmount = max(0.00, round($orderTotal - $allocatedAmount, 2));

            // 3. Registrar Decision (Inmutable - Insert Only)
            $decisionId = DB::table('payment_decisions')->insertGetId([
                'payment_evidence_id' => $evidenceId,
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'decision' => $decision,
                'reason_code' => $reasonCode,
                'risk_score' => 0,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'created_at' => now(),
            ]);

            // 4. Generar Token Criptográfico de Autorización de Seguridad (PAY33)
            $authorizationToken = 'PAY33_AUTH_TOKEN_' . hash('sha256', $tenantId . '|' . $orderId . '|' . $decisionId . '|' . config('app.key', 'aiva-secret'));

            $isExactOrFull = ($remainingAmount <= 0.05);
            $newPaymentStatus = $isExactOrFull ? 'PAID' : 'PENDING';

            // 5. Transicionar estado del pedido vía OrderStateMachine con el Token de Autorización PAY33
            $this->orderStateMachine->transitionPaymentStatus(
                tenantId: $tenantId,
                orderId: $orderId,
                newPaymentStatus: $newPaymentStatus,
                actorType: $actorType,
                actorId: $actorId,
                meta: [
                    'authorization_token' => $authorizationToken,
                    'payment_decision_id' => $decisionId,
                    'evidence_id' => $evidenceId,
                    'allocated_amount' => $allocatedAmount,
                ]
            );

            // 6. Registrar Allocation
            $allocationType = $isExactOrFull ? 'EXACT_PAYMENT' : ($allocatedAmount > $orderTotal ? 'OVERPAYMENT' : 'PARTIAL_PAYMENT');
            DB::table('payment_allocations')->insert([
                'payment_evidence_id' => $evidenceId,
                'order_id' => $orderId,
                'allocated_amount' => $allocatedAmount,
                'remaining_order_amount' => $remainingAmount,
                'allocation_type' => $allocationType,
                'status' => 'APPLIED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 7. Actualizar estado de evidencia
            DB::table('payment_evidences')->where('id', $evidenceId)->update([
                'status' => $isExactOrFull ? EvidenceStatus::PAID->value : EvidenceStatus::APPLIED->value,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            // 8. Evento de Auditoría Inmutable
            DB::table('payment_audit_events')->insert([
                'tenant_id' => $tenantId,
                'payment_evidence_id' => $evidenceId,
                'event_type' => 'PAYMENT_AUTHORIZED_AND_APPLIED',
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata_json' => json_encode([
                    'order_id' => $orderId,
                    'decision' => $decision,
                    'reason_code' => $reasonCode,
                    'allocated_amount' => $allocatedAmount,
                    'new_payment_status' => $newPaymentStatus,
                ]),
                'created_at' => now(),
            ]);

            return [
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'evidence_id' => $evidenceId,
                'decision_id' => $decisionId,
                'payment_status' => $newPaymentStatus,
                'allocated_amount' => $allocatedAmount,
                'remaining_amount' => $remainingAmount,
            ];
        });
    }
}
