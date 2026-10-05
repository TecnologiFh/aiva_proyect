<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use App\Modules\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionalOutboxService
{
    public const MAX_ATTEMPTS = 5;

    /**
     * C1 & C3: Encolado atómico co-commit dentro de la transacción comercial.
     * Bloquea pesimistamente la fila de la conversación para serializar de forma segura
     * la asignación del sequence_number antes del INSERT.
     */
    public function scheduleOutbound(
        int $tenantId,
        int|string $conversationId,
        OutgoingWhatsAppMessageDTO $dto,
        ?string $correlationToken = null
    ): OutboxMessage {
        // C3: Bloqueo pesimista sobre la conversación para autoridad de secuencia
        DB::table('conversations')
            ->where('id', $conversationId)
            ->lockForUpdate()
            ->first();

        // Calcular siguiente secuencia bajo lock exclusivo
        $maxSeq = DB::table('outbox_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->max('sequence_number') ?? 0;

        $nextSeq = (int)$maxSeq + 1;

        // C1: Snapshot inmutable del mensaje acordado
        $payloadSnapshot = [
            'recipient_phone' => $dto->recipientPhone,
            'content' => $dto->content,
            'type' => $dto->messageType,
            'media_url' => $dto->mediaUrl,
            'media_id' => null,
            'template_name' => null,
            'template_language' => null,
            'template_components' => null,
        ];

        return OutboxMessage::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'sequence_number' => $nextSeq,
            'recipient_phone' => $dto->recipientPhone,
            'message_type' => $dto->messageType,
            'payload_json' => $payloadSnapshot,
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_attempt_at' => null,
            'whatsapp_message_id' => null,
            'correlation_token' => $correlationToken ?: (string) Str::uuid(),
            'last_http_status' => null,
            'last_error' => null,
        ]);
    }

    /**
     * C2 & C3 & C6-A: Reclamo atómico con lease respetando FIFO conversacional estricto.
     * No reclama mensaje N+1 si mensaje N de la misma conversación está bloqueado en PENDING/DISPATCHING/RETRYING.
     */
    public function claimNextEligibleForTenant(int $tenantId, int $leaseSeconds = 30): ?OutboxMessage
    {
        return DB::transaction(function () use ($tenantId, $leaseSeconds) {
            $now = now();

            // Buscar candidatos elegibles ordenados por conversación y sequence_number
            $candidates = OutboxMessage::where('tenant_id', $tenantId)
                ->where(function ($q) use ($now) {
                    $q->where(function ($q2) use ($now) {
                        $q2->where('status', OutboxMessage::STATUS_PENDING)
                            ->where(function ($q3) use ($now) {
                                $q3->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', $now);
                            });
                    })
                    ->orWhere(function ($q2) use ($now) {
                        // C2: Operational crash recovery: Lease expirado en DISPATCHING
                        $q2->where('status', OutboxMessage::STATUS_DISPATCHING)
                            ->where('lease_expires_at', '<', $now);
                    })
                    ->orWhere(function ($q2) use ($now) {
                        // C4: Reintento con backoff cumplido
                        $q2->where('status', OutboxMessage::STATUS_RETRYING)
                            ->where('next_retry_at', '<=', $now);
                    });
                })
                ->orderBy('conversation_id')
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $cand) {
                // C3: Head-of-Line Protection por conversación
                // Verificar si existe un mensaje anterior no resuelto para la misma conversación
                $hasPriorUnresolved = OutboxMessage::where('tenant_id', $tenantId)
                    ->where('conversation_id', $cand->conversation_id)
                    ->where('sequence_number', '<', $cand->sequence_number)
                    ->whereIn('status', [
                        OutboxMessage::STATUS_PENDING,
                        OutboxMessage::STATUS_DISPATCHING,
                        OutboxMessage::STATUS_RETRYING,
                        OutboxMessage::STATUS_AMBIGUOUS
                    ])
                    ->exists();

                if ($hasPriorUnresolved) {
                    // Head-of-line bloqueado: este mensaje debe esperar
                    continue;
                }

                // C6-A: Reclamo atómico con lease
                $leaseUntil = now()->addSeconds($leaseSeconds);
                $updated = DB::table('outbox_messages')
                    ->where('id', $cand->id)
                    ->where(function ($q) use ($now) {
                        $q->whereIn('status', [OutboxMessage::STATUS_PENDING, OutboxMessage::STATUS_RETRYING])
                            ->orWhere(function ($q2) use ($now) {
                                $q2->where('status', OutboxMessage::STATUS_DISPATCHING)
                                    ->where('lease_expires_at', '<', $now);
                            });
                    })
                    ->update([
                        'status' => OutboxMessage::STATUS_DISPATCHING,
                        'lease_expires_at' => $leaseUntil,
                        'updated_at' => $now,
                    ]);

                if ($updated > 0) {
                    $cand->refresh();
                    return $cand;
                }
            }

            return null;
        });
    }

    /**
     * C5: Registro de éxito HTTP 200 con wamid retornado por Meta.
     */
    public function recordDispatchSuccess(int $outboxId, string $wamid, int $httpStatus = 200): bool
    {
        $outbox = OutboxMessage::find($outboxId);
        if (!$outbox) return false;

        $newAttempts = $outbox->attempts + 1; // C4: attempts = POSTs efectivamente ejecutados

        $outbox->update([
            'status' => OutboxMessage::STATUS_SENT,
            'whatsapp_message_id' => $wamid,
            'attempts' => $newAttempts,
            'last_attempt_at' => now(),
            'last_http_status' => (string) $httpStatus,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => null,
        ]);

        return true;
    }

    /**
     * C4: Registro de fallo transitorio con backoff determinista y límite de 5 intentos.
     */
    public function recordTransientFailure(int $outboxId, string $error, int $httpStatus = 503): bool
    {
        $outbox = OutboxMessage::find($outboxId);
        if (!$outbox) return false;

        $newAttempts = $outbox->attempts + 1; // C4: Se ejecutó un intento POST

        if ($newAttempts >= self::MAX_ATTEMPTS) {
            // C4: Límite de 5 intentos alcanzado -> DEAD_LETTER determinista
            $outbox->update([
                'status' => OutboxMessage::STATUS_DEAD_LETTER,
                'attempts' => $newAttempts,
                'last_attempt_at' => now(),
                'last_http_status' => (string) $httpStatus,
                'lease_expires_at' => null,
                'next_retry_at' => null,
                'last_error' => "MAX_DISPATCH_ATTEMPTS_EXCEEDED ({$newAttempts}/" . self::MAX_ATTEMPTS . "): " . $error,
            ]);
            return false;
        }

        // C4: Fórmula determinista: min(2^attempts * 2, 60) + jitter
        $delaySeconds = (int) min(pow(2, $newAttempts) * 2, 60);
        $nextRetryAt = now()->addSeconds($delaySeconds);

        $outbox->update([
            'status' => OutboxMessage::STATUS_RETRYING,
            'attempts' => $newAttempts,
            'last_attempt_at' => now(),
            'last_http_status' => (string) $httpStatus,
            'lease_expires_at' => null,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $error,
        ]);

        return true;
    }

    /**
     * C5: Error terminal síncrono (HTTP 400 semántico, 401, 403) -> DEAD_LETTER inmediato sin reintento.
     */
    public function recordTerminalFailure(int $outboxId, string $error, int $httpStatus = 400): bool
    {
        $outbox = OutboxMessage::find($outboxId);
        if (!$outbox) return false;

        $newAttempts = $outbox->attempts + 1;

        $outbox->update([
            'status' => OutboxMessage::STATUS_DEAD_LETTER,
            'attempts' => $newAttempts,
            'last_attempt_at' => now(),
            'last_http_status' => (string) $httpStatus,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => "TERMINAL_DISPATCH_ERROR: " . $error,
        ]);

        return true;
    }

    /**
     * C6-B: Timeout de red ambiguo (socket dropped / no response headers).
     * No se reintenta automáticamente con un POST ciego.
     */
    public function recordAmbiguousOutcome(int $outboxId, string $error): bool
    {
        $outbox = OutboxMessage::find($outboxId);
        if (!$outbox) return false;

        $newAttempts = $outbox->attempts + 1;

        $outbox->update([
            'status' => OutboxMessage::STATUS_AMBIGUOUS,
            'attempts' => $newAttempts,
            'last_attempt_at' => now(),
            'last_http_status' => 'SOCKET_TIMEOUT',
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => "NETWORK_TIMEOUT_NO_RESPONSE_HEADER: " . $error,
        ]);

        return true;
    }

    /**
     * C6-B: Reconciliación de estado ambiguo.
     * Exige correlación inequívoca (token o hash). De lo contrario, pasa a FORENSIC_UNRESOLVED.
     */
    public function reconcileAmbiguousOutcome(int $outboxId, ?string $unequivocalWamid = null, ?string $evidenceToken = null): string
    {
        $outbox = OutboxMessage::find($outboxId);
        if (!$outbox) return 'NOT_FOUND';

        // Solo procesa si está en estado ambiguo
        if ($outbox->status !== OutboxMessage::STATUS_AMBIGUOUS) {
            return $outbox->status;
        }

        // C6-B: Verificar si existe evidencia inequívocamente correlacionada
        $isCorrelated = (!empty($unequivocalWamid) && !empty($evidenceToken) && $outbox->correlation_token === $evidenceToken);

        if ($isCorrelated) {
            $outbox->update([
                'status' => OutboxMessage::STATUS_SENT,
                'whatsapp_message_id' => $unequivocalWamid,
                'last_error' => 'RECONCILED_VIA_UNEQUIVOCAL_EVIDENCE',
            ]);
            return 'RECONCILED_SENT';
        }

        // Si no hay correlación inequívoca, pasa irreversiblemente a FORENSIC_UNRESOLVED
        $outbox->update([
            'status' => OutboxMessage::STATUS_FORENSIC_UNRESOLVED,
            'last_error' => 'NO_UNEQUIVOCAL_CORRELATION_AFTER_RECONCILIATION_WINDOW',
        ]);

        return 'FORENSIC_UNRESOLVED';
    }
}
