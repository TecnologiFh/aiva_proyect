<?php

namespace App\Modules\Messaging\Controllers;

use App\Modules\Messaging\Application\ReceiveWebhook\ReceiveWebhookAction;
use App\Modules\Messaging\Application\ReceiveWebhook\ReceiveWebhookInput;
use App\Modules\Messaging\Requests\WhatsAppWebhookRequest;
use App\Modules\Messaging\Services\WhatsAppChannelResolver;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WhatsAppWebhookController extends Controller
{
    /**
     * Endpoint GET exigido por Meta Cloud API para verificar el Webhook (Desafío hub.challenge).
     */
    public function verify(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $verifyToken = env('WHATSAPP_VERIFY_TOKEN', 'aiva_webhook_secret_token');

        if (!empty($mode) && !empty($token)) {
            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response((string)$challenge, 200)
                    ->header('Content-Type', 'text/plain')
                    ->header('Bypass-Tunnel-Reminder', 'true');
            }
            return response()->json(['error' => 'hub.verify_token no coincide'], 403);
        }

        if (!empty($challenge)) {
            return response((string)$challenge, 200)
                ->header('Content-Type', 'text/plain')
                ->header('Bypass-Tunnel-Reminder', 'true');
        }

        return response()->json([
            'status' => 'ONLINE',
            'service' => 'AIVA WhatsApp Webhook Endpoint',
            'message' => 'El Webhook de WhatsApp de AIVA está activo y listo para recibir llamadas de Meta Cloud API.'
        ]);
    }

    /**
     * Endpoint POST para recepción de eventos y mensajes en tiempo real desde Meta Cloud API.
     * Validación de Firma HMAC sobre el RAW BODY ($request->getContent()).
     */
    public function handle(WhatsAppWebhookRequest $request, ReceiveWebhookAction $action): JsonResponse
    {
        // 1. Validación de Firma HMAC-SHA256 sobre el cuerpo HTTP crudo (RAW BODY)
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('aiva.whatsapp.app_secret') ?: (config('services.whatsapp.app_secret') ?: env('WHATSAPP_APP_SECRET'));
        $isProductionOrConfigured = in_array(app()->environment(), ['production', 'staging']) || !empty($appSecret);

        if ($isProductionOrConfigured) {
            if (empty($signature) || !str_starts_with($signature, 'sha256=')) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'error' => 'Cabecera X-Hub-Signature-256 requerida o formato inválido.',
                ], 403);
            }

            $expectedHash = 'sha256=' . hash_hmac('sha256', $rawBody, (string)$appSecret);
            if (!hash_equals($expectedHash, $signature)) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'error' => 'Firma HMAC de Meta inválida. Intento de intrusión o cuerpo alterado.',
                ], 403);
            }
        }

        // 2. Validación de Estructura de Payload Meta
        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || ($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response()->json([
                'status' => 'UNPROCESSABLE_ENTITY',
                'error' => 'Payload no conforme al estándar de Meta WhatsApp Business Account.',
            ], 422);
        }

        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        // 3. Ruta Ligera de Estados (Gate A5): Sin invocación de Brain, sin IA, sin Cart
        if (!empty($value['statuses'])) {
            $input = new ReceiveWebhookInput(payload: $payload, tenantId: 0);
            $result = $action->execute($input);
            return ApiResponse::success(
                data: $result->toArray(),
                message: 'Estado de WhatsApp procesado en ruta ligera.'
            );
        }

        // 4. Extracción de phone_number_id y Resolución Estricta de Tenant (Gate A3 & AG47-A-C3)
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        if (empty($phoneNumberId)) {
            return response()->json([
                'status' => 'UNPROCESSABLE_ENTITY',
                'error' => 'Falta metadata.phone_number_id en el payload de Meta.',
            ], 422);
        }

        try {
            $tenantId = WhatsAppChannelResolver::resolveStrictBinding(null, (string)$phoneNumberId);
        } catch (\Throwable $e) {
            // UNMAPPED_TENANT: Bloqueo Fail-Closed absoluto (0 Brain, 0 Cart, 0 Order, 0 Outbound)
            return response()->json([
                'status' => 'UNMAPPED_TENANT_REJECTED',
                'error' => "Canal telefónico [{$phoneNumberId}] sin binding activo registrado. Evento descartado fail-closed.",
                'phone_number_id' => $phoneNumberId,
                'tenant_id' => null,
                'brain_calls' => 0,
                'cart_mutations' => 0,
                'order_mutations' => 0,
                'outbound_calls' => 0,
            ], 422);
        }

        // 5. Despacho Canónico del Webhook Inbound (Idempotencia + Brain Canónico)
        $input = new ReceiveWebhookInput(
            payload: $payload,
            tenantId: $tenantId
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: array_merge($result->toArray(), [
                'resolved_tenant_id' => $tenantId,
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]),
            message: 'Webhook recibido y procesado correctamente'
        );
    }
}
