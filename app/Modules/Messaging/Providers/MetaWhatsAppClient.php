<?php

namespace App\Modules\Messaging\Providers;

use App\Core\Contracts\Messaging\WhatsAppClientInterface;
use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppClient implements WhatsAppClientInterface
{
    public function __construct(
        private readonly ?string $phoneNumberId = null,
        private readonly ?string $accessToken = null
    ) {}

    /**
     * Resuelve credenciales estricta por tenant_id (AG47-A-C3).
     * Prohíbe terminantemente el fallback a .env global para un tenant sin binding activo.
     */
    public function resolveCredentialsForTenant(?int $tenantId): array
    {
        if ($tenantId !== null && $tenantId > 0) {
            $channel = DB::table('whatsapp_channels')
                ->where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->first();

            if ($channel && !empty($channel->phone_number_id)) {
                $token = $channel->access_token 
                    ?? config('services.whatsapp.access_token') 
                    ?? env('WHATSAPP_ACCESS_TOKEN');

                return [
                    'phone_id' => (string)$channel->phone_number_id,
                    'token' => (string)$token,
                    'is_tenant_bound' => true,
                ];
            }

            // AG47-A-C3: Bloqueo fail-closed de credenciales
            Log::warning("MetaWhatsAppClient [AG47-A-C3]: No active WhatsApp channel binding for tenant {$tenantId}. Suppressing outbound send to prevent cross-tenant leak.");
            return [
                'phone_id' => null,
                'token' => null,
                'is_tenant_bound' => false,
            ];
        }

        // Fallback permitido EXCLUSIVAMENTE para bootstrap/dev o SYSTEM_DEFAULT sin tenant
        $phoneId = $this->phoneNumberId 
            ?: config('services.whatsapp.phone_number_id') 
            ?: env('WHATSAPP_PHONE_NUMBER_ID') 
            ?: env('WHATSAPP_PHONE_ID');

        $token = $this->accessToken 
            ?: config('services.whatsapp.access_token') 
            ?: env('WHATSAPP_ACCESS_TOKEN') 
            ?: env('WHATSAPP_TOKEN');

        return [
            'phone_id' => $phoneId,
            'token' => $token,
            'is_tenant_bound' => false,
        ];
    }

    /**
     * Construye el payload conforme a la especificación de Meta Graph API v18.0+
     * Validando el cumplimiento operacional de medios (Gate A7).
     */
    public function buildMetaPayload(OutgoingWhatsAppMessageDTO $message): array
    {
        $type = strtoupper($message->messageType ?? 'TEXT');
        $mediaUrl = $message->mediaUrl;

        $isMedia = ($type === 'IMAGE' || $type === 'DOCUMENT' || !empty($mediaUrl));

        if ($isMedia && !empty($mediaUrl)) {
            $isLocal = str_contains($mediaUrl, '127.0.0.1') 
                || str_contains($mediaUrl, 'localhost') 
                || str_starts_with($mediaUrl, 'file://');

            $mediaType = ($type === 'DOCUMENT' || str_ends_with(strtolower($mediaUrl), '.pdf')) ? 'document' : 'image';

            $mediaObject = [];
            if (str_starts_with($mediaUrl, 'media_id:')) {
                $mediaObject['id'] = substr($mediaUrl, 9);
            } else {
                $mediaObject['link'] = $mediaUrl;
            }

            if (!empty($message->content)) {
                $mediaObject['caption'] = $message->content;
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $message->recipientPhone,
                'type' => $mediaType,
                $mediaType => $mediaObject,
                '_operational_compliance' => [
                    'is_routable' => !$isLocal,
                    'requires_meta_media_upload' => $isLocal,
                    'media_type' => $mediaType,
                ],
            ];
            if (!empty($message->correlationToken)) {
                $payload['biz_opaque_callback_data'] = substr($message->correlationToken, 0, 512);
            }
            return $payload;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->recipientPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message->content,
            ],
            '_operational_compliance' => [
                'is_routable' => true,
                'requires_meta_media_upload' => false,
                'media_type' => 'text',
            ],
        ];
        if (!empty($message->correlationToken)) {
            $payload['biz_opaque_callback_data'] = substr($message->correlationToken, 0, 512);
        }
        return $payload;
    }

    public function sendMessage(OutgoingWhatsAppMessageDTO $message): bool
    {
        $creds = $this->resolveCredentialsForTenant($message->tenantId);
        $phoneId = $creds['phone_id'];
        $token = $creds['token'];

        // AG47-A-C3: Bloqueo estricto si tenant no tiene binding
        if (!$phoneId || !$token) {
            if ($message->tenantId > 0) {
                return false;
            }
            return (new MockWhatsAppClient())->sendMessage($message);
        }

        try {
            $payloadWithMeta = $this->buildMetaPayload($message);
            $apiPayload = collect($payloadWithMeta)->except('_operational_compliance')->toArray();

            $url = "https://graph.facebook.com/v18.0/{$phoneId}/messages";

            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $apiPayload);

            if ($response->successful()) {
                return true;
            }

            Log::error("MetaWhatsAppClient Error: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Exception en MetaWhatsAppClient: " . $e->getMessage());
        }

        return false;
    }

    public function markAsRead(string $wamid, ?int $tenantId = null): bool
    {
        $creds = $this->resolveCredentialsForTenant($tenantId);
        $phoneId = $creds['phone_id'];
        $token = $creds['token'];

        if (!$phoneId || !$token) {
            return (new MockWhatsAppClient())->markAsRead($wamid);
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$phoneId}/messages";

            $response = Http::withToken($token)->post($url, [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $wamid,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Exception markAsRead: " . $e->getMessage());
        }

        return false;
    }
}

