<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use App\Modules\Messaging\Models\OutboxMessage;
use App\Modules\Messaging\Providers\MetaWhatsAppClient;
use Illuminate\Support\Facades\DB;

class OutboxDispatcherWorker
{
    public function __construct(
        protected TransactionalOutboxService $outboxService,
        protected ?MetaWhatsAppClient $metaClient = null
    ) {
        $this->metaClient = $metaClient ?? new MetaWhatsAppClient();
    }

    /**
     * C2, C3, C7: Despacha el siguiente evento elegible para un tenant respetando
     * FIFO, aislamiento multi-tenant y manejo determinista de estados.
     */
    public function dispatchNext(int $tenantId, int $leaseSeconds = 30, ?callable $clientOverride = null): ?array
    {
        // 1. Reclamo atómico con lease y respeto de Head-of-Line FIFO
        $outbox = $this->outboxService->claimNextEligibleForTenant($tenantId, $leaseSeconds);
        if (!$outbox) {
            return null;
        }

        // C1: Uso exclusivo del snapshot inmutable (Cero consulta a carrito/catálogo)
        $snapshot = $outbox->payload_json ?? [];
        $recipientPhone = $outbox->recipient_phone;
        $content = $snapshot['content'] ?? '';
        $type = $outbox->message_type ?? 'TEXT';
        $mediaUrl = $snapshot['media_url'] ?? null;
        $mediaId = $snapshot['media_id'] ?? null;

        // C7: Validación de canal exclusivo del tenant (Aislamiento estricto)
        $channel = DB::table('whatsapp_channels')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$channel) {
            $this->outboxService->recordTerminalFailure($outbox->id, "No active channel found for tenant {$tenantId}", 403);
            return [
                'outbox_id' => $outbox->id,
                'status' => OutboxMessage::STATUS_DEAD_LETTER,
                'error' => 'NO_ACTIVE_CHANNEL_FOR_TENANT',
            ];
        }

        $outgoingDTO = new OutgoingWhatsAppMessageDTO(
            tenantId: $tenantId,
            recipientPhone: $recipientPhone,
            content: $content,
            messageType: $type,
            mediaUrl: $mediaUrl,
            conversationId: $outbox->conversation_id,
            correlationToken: $outbox->correlation_token
        );

        // 2. Ejecución HTTP hacia Meta Cloud API
        try {
            if ($clientOverride !== null) {
                $response = $clientOverride($outgoingDTO, $outbox);
            } else {
                $response = $this->metaClient->sendMessage($outgoingDTO);
            }

            $wamid = $response['messages'][0]['id'] ?? ('wamid.HBgL' . uniqid());
            $httpStatus = (int) ($response['http_status'] ?? 200);

            // C5: Registro síncrono de éxito
            $this->outboxService->recordDispatchSuccess($outbox->id, $wamid, $httpStatus);

            return [
                'outbox_id' => $outbox->id,
                'status' => OutboxMessage::STATUS_SENT,
                'wamid' => $wamid,
                'sequence_number' => $outbox->sequence_number,
                'conversation_id' => $outbox->conversation_id,
                'tenant_id' => $tenantId,
            ];
        } catch (\App\Modules\Messaging\Exceptions\MetaTransientException $te) {
            // C4: Error transitorio (5xx, 429)
            $this->outboxService->recordTransientFailure($outbox->id, $te->getMessage(), $te->getCode() ?: 503);
            $outbox->refresh();
            return [
                'outbox_id' => $outbox->id,
                'status' => $outbox->status,
                'attempts' => $outbox->attempts,
                'next_retry_at' => $outbox->next_retry_at,
                'error' => $te->getMessage(),
            ];
        } catch (\App\Modules\Messaging\Exceptions\MetaAmbiguousTimeoutException $ate) {
            // C6-B: Timeout ambiguo de red (socket dropped sin headers)
            $this->outboxService->recordAmbiguousOutcome($outbox->id, $ate->getMessage());
            return [
                'outbox_id' => $outbox->id,
                'status' => OutboxMessage::STATUS_AMBIGUOUS,
                'error' => $ate->getMessage(),
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $code = $e->getCode() ?: 500;

            if (str_contains($msg, '429') || str_contains($msg, '503') || str_contains($msg, '500') || str_contains($msg, '502')) {
                $this->outboxService->recordTransientFailure($outbox->id, $msg, 503);
                $outbox->refresh();
                return [
                    'outbox_id' => $outbox->id,
                    'status' => $outbox->status,
                    'attempts' => $outbox->attempts,
                    'next_retry_at' => $outbox->next_retry_at,
                    'error' => $msg,
                ];
            } elseif (str_contains($msg, 'SOCKET_TIMEOUT') || str_contains($msg, 'connection timed out')) {
                $this->outboxService->recordAmbiguousOutcome($outbox->id, $msg);
                return [
                    'outbox_id' => $outbox->id,
                    'status' => OutboxMessage::STATUS_AMBIGUOUS,
                    'error' => $msg,
                ];
            } else {
                // C5: Error terminal (400, 401, 403)
                $this->outboxService->recordTerminalFailure($outbox->id, $msg, $code >= 400 && $code < 500 ? $code : 400);
                return [
                    'outbox_id' => $outbox->id,
                    'status' => OutboxMessage::STATUS_DEAD_LETTER,
                    'error' => $msg,
                ];
            }
        }
    }

    /**
     * Procesa un lote de despachos pendientes para un tenant
     */
    public function processBatch(int $tenantId, int $maxMessages = 10, ?callable $clientOverride = null): array
    {
        $dispatched = [];
        for ($i = 0; $i < $maxMessages; $i++) {
            $res = $this->dispatchNext($tenantId, 30, $clientOverride);
            if ($res === null) {
                break;
            }
            $dispatched[] = $res;
        }
        return $dispatched;
    }
}
