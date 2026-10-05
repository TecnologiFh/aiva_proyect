<?php

namespace App\Modules\Messaging\Application\ReceiveWebhook;

use App\Modules\Messaging\Controllers\TenantBrainController;
use App\Modules\Messaging\Models\ProcessedWebhook;
use App\Modules\Messaging\Services\InboundMediaClassifierService;
use App\Modules\Messaging\Services\WhatsAppMediaDownloaderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiveWebhookAction
{
    public function __construct(
        private readonly ?WhatsAppMediaDownloaderService $mediaDownloader = null,
        private readonly ?InboundMediaClassifierService $mediaClassifier = null
    ) {}

    public function execute(ReceiveWebhookInput $input): ReceiveWebhookResult
    {
        $downloader = $this->mediaDownloader ?? new WhatsAppMediaDownloaderService();
        $classifier = $this->mediaClassifier ?? new InboundMediaClassifierService();
        $statusTracker = app(\App\Modules\Messaging\Services\MessageDeliveryStatusTracker::class);

        $payload = $input->payload;
        $tenantId = $input->tenantId;
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        // 1. Ruta Ligera de Estados (Gate A5 / B3 Monotonicity): Sin Brain, sin Cart, sin efectos IA
        if (!empty($value['statuses'])) {
            $updatedStatuses = [];
            foreach ($value['statuses'] as $st) {
                $wamid = $st['id'] ?? null;
                $statusStr = $st['status'] ?? null;
                if ($wamid && $statusStr) {
                    // Actualización monotónica atómica en MySQL
                    $tracked = $statusTracker->updateStatusMonotonic($tenantId, $wamid, $statusStr);
                    $updatedStatuses[] = $tracked;

                    $statusKey = "status:{$tenantId}:{$wamid}:{$statusStr}";
                    ProcessedWebhook::firstOrCreate(
                        ['event_id' => $statusKey],
                        ['provider' => 'META_WHATSAPP', 'processed_at' => now()]
                    );

                    if ($tenantId > 0 && \Illuminate\Support\Facades\Schema::hasTable('messages')) {
                        DB::table('messages')
                            ->where('tenant_id', $tenantId)
                            ->where('external_id', $wamid)
                            ->update(['status' => strtoupper($statusStr), 'updated_at' => now()]);
                    }

                    // C6-B: Reconciliación oficial vía Meta biz_opaque_callback_data
                    $bizOpaque = $st['biz_opaque_callback_data'] ?? null;
                    if ($bizOpaque && $tenantId > 0) {
                        $ambiguousOutbox = \App\Modules\Messaging\Models\OutboxMessage::where('tenant_id', $tenantId)
                            ->where('correlation_token', $bizOpaque)
                            ->where('status', \App\Modules\Messaging\Models\OutboxMessage::STATUS_AMBIGUOUS)
                            ->first();
                        if ($ambiguousOutbox) {
                            app(\App\Modules\Messaging\Services\TransactionalOutboxService::class)
                                ->reconcileAmbiguousOutcome($ambiguousOutbox->id, $wamid, $bizOpaque);
                        }
                    }
                }
            }
            return new ReceiveWebhookResult(
                processed: true,
                status: 'STATUS_ACKNOWLEDGED',
                additionalData: [
                    'statuses_count' => count($value['statuses']),
                    'tracked_statuses' => $updatedStatuses,
                ]
            );
        }

        // 2. Ruta Inbound de Mensajes
        $messages = $value['messages'] ?? [];
        if (empty($messages)) {
            return new ReceiveWebhookResult(processed: true, status: 'IGNORED_EMPTY_OR_UNSUPPORTED');
        }

        $rawMsg = $messages[0];
        $wamid = $rawMsg['id'] ?? (string) Str::uuid();

        // ── Precisión 1: Autoridad Constitucional de Idempotencia en MySQL ──
        // Intentar inserción atómica en MySQL protegida por UNIQUE(tenant_id, whatsapp_message_id)
        $eventRecord = null;
        try {
            $eventRecord = \App\Modules\Messaging\Models\InboundWebhookEvent::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'whatsapp_message_id' => $wamid,
                'status' => 'PROCESSING',
                'payload' => $rawMsg,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Violación de restricción UNIQUE en MySQL (Código 1062 / Duplicate entry)
            // MySQL es la autoridad constitucional: rechazar inmediatamente como duplicado
            return new ReceiveWebhookResult(
                processed: true,
                status: 'DUPLICATE_EVENT_SKIPPED',
                additionalData: [
                    'wamid' => $wamid,
                    'tenant_id' => $tenantId,
                    'authority' => 'MYSQL_UNIQUE_CONSTRAINT',
                    'error_code' => $e->getCode(),
                ]
            );
        }

        // Registrar también en processed_webhooks para retrocompatibilidad
        $inboundEventKey = "inbound:{$tenantId}:{$wamid}";
        ProcessedWebhook::firstOrCreate(
            ['event_id' => $inboundEventKey],
            ['provider' => 'META_WHATSAPP', 'processed_at' => now()]
        );

        $customerPhone = $rawMsg['from'] ?? '000000000';
        $customerName = $value['contacts'][0]['profile']['name'] ?? 'Cliente';
        $messageType = strtoupper($rawMsg['type'] ?? 'TEXT');

        // ── Precisión 6: Namespace Canónico del Lock (tenant + teléfono normalizado) ──
        $canonicalPhone = \App\Modules\Messaging\Services\CustomerPhoneNormalizer::normalize($customerPhone);

        $contentText = '';
        $hasMedia = false;
        $mediaBase64 = null;
        $mediaClassification = null;

        if ($messageType === 'TEXT') {
            $contentText = $rawMsg['text']['body'] ?? '';
        } elseif ($messageType === 'INTERACTIVE') {
            $contentText = $rawMsg['interactive']['button_reply']['title'] 
                ?? $rawMsg['interactive']['list_reply']['title'] 
                ?? $rawMsg['interactive']['button_reply']['id'] 
                ?? '';
        } elseif ($messageType === 'IMAGE' || $messageType === 'DOCUMENT') {
            $mediaObj = $rawMsg[strtolower($messageType)] ?? [];
            $mediaId = $mediaObj['id'] ?? null;
            $caption = $mediaObj['caption'] ?? '';
            $contentText = $caption;

            if ($mediaId) {
                $downloadResult = $downloader->downloadMedia($tenantId, (string)$mediaId);
                if (($downloadResult['status'] ?? '') === 'SUCCESS' && !empty($downloadResult['base64'])) {
                    $hasMedia = true;
                    $mediaBase64 = $downloadResult['base64'];
                    $mediaClassification = $classifier->classifyInboundMedia($mediaBase64, $caption);
                }
            }
        } elseif ($messageType === 'LOCATION') {
            $lat = $rawMsg['location']['latitude'] ?? '';
            $lng = $rawMsg['location']['longitude'] ?? '';
            $contentText = "Ubicación compartida: {$lat}, {$lng}";
        } else {
            $contentText = "Mensaje {$messageType}";
        }

        // ── Precisión 2 & 3: Fresh-State-Invariant & Conversation Mutex Lock con Telemetría ──
        $convLockKey = "lock_conv_mutex:{$tenantId}:{$canonicalPhone}";
        $convLock = \Illuminate\Support\Facades\Cache::lock($convLockKey, 15);
        $lockAcquired = false;
        $lockAcquiredAt = 0.0;
        $criticalSectionStartedAt = 0.0;
        $criticalSectionFinishedAt = 0.0;

        try {
            $lockAcquired = $convLock->block(5);
            $lockAcquiredAt = microtime(true);
            $criticalSectionStartedAt = microtime(true);

            // 3. Dispatch canónico único a TenantBrainController (Gate A10: Single Conversational Entry Point)
            $req = Request::create('/api/v1/messaging/qr-inbound', 'POST', [
                'phone' => $customerPhone,
                'name' => $customerName,
                'message' => $contentText,
                'has_media' => $hasMedia,
                'media_base64' => $mediaBase64,
                'media_classification' => $mediaClassification,
                'external_message_id' => $wamid,
                'source' => 'META_CLOUD_API',
            ]);
            $req->attributes->set('tenant_id', $tenantId);
            $req->attributes->set('correlation_id', (string) Str::uuid());

            $brainController = app(TenantBrainController::class);
            $brainResponse = $brainController->processInboundMessage($req);
            $brainData = json_decode($brainResponse->getContent(), true) ?? [];

            $criticalSectionFinishedAt = microtime(true);

            // ── Precisión C1 & C3: Encolado atómico en Outbox Message ──
            $convId = $brainData['conversation_id'] ?? null;
            $outboxRecord = null;
            if (!empty($convId) && (!empty($brainData['reply_text']) || !empty($brainData['media_url']))) {
                try {
                    $outboxService = app(\App\Modules\Messaging\Services\TransactionalOutboxService::class);
                    $outDto = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: (string)($brainData['reply_text'] ?? ''),
                        messageType: !empty($brainData['media_url']) ? 'IMAGE' : 'TEXT',
                        mediaUrl: $brainData['media_url'] ?? null
                    );
                    $outboxRecord = $outboxService->scheduleOutbound($tenantId, (string)$convId, $outDto, (string) Str::uuid());
                } catch (\Throwable $oe) {
                    \Illuminate\Support\Facades\Log::warning("Outbox scheduling notice: " . $oe->getMessage());
                }
            }

            // ── Precisión 4: Marcar PROCESSED como unidad coherente al finalizar ──
            if ($eventRecord) {
                $eventRecord->update([
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                ]);
            }

            $criticalSectionDurationMs = round(($criticalSectionFinishedAt - $criticalSectionStartedAt) * 1000, 2);
            $lockReleasedAt = microtime(true);
            $leaseSafetyMarginMs = round((15.0 - ($lockReleasedAt - $lockAcquiredAt)) * 1000, 2);

            return new ReceiveWebhookResult(
                processed: true,
                status: 'PROCESSED',
                additionalData: [
                    'wamid' => $wamid,
                    'tenant_id' => $tenantId,
                    'brain_response' => $brainData,
                    'media_classification' => $mediaClassification,
                    'outbox_id' => $outboxRecord?->id,
                    'outbox_uuid' => $outboxRecord?->uuid,
                    'outbox_status' => $outboxRecord?->status,
                    'outbox_sequence' => $outboxRecord?->sequence_number,
                    'telemetry' => [
                        'lease_ttl_ms' => 15000,
                        'lock_acquired_at' => $lockAcquiredAt,
                        'critical_section_started_at' => $criticalSectionStartedAt,
                        'critical_section_finished_at' => $criticalSectionFinishedAt,
                        'lock_released_at' => $lockReleasedAt,
                        'critical_section_duration_ms' => $criticalSectionDurationMs,
                        'lease_safety_margin_ms' => $leaseSafetyMarginMs,
                        'canonical_phone' => $canonicalPhone,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            // Caso A: Crash before commit -> eliminar o marcar FAILED para que el retry pueda procesar limpiamente
            if ($eventRecord && $eventRecord->status === 'PROCESSING') {
                $eventRecord->delete();
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                optional($convLock)->release();
            }
        }
    }
}

