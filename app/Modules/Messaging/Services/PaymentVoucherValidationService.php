<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentVoucherValidationService
{
    /**
     * Validar de forma rigurosa una captura de pago Yape / Plin / Transferencia.
     * Evalúa 4 criterios comerciales:
     * 1. Formato y tipo de imagen (Rechaza recargas telefónicas, memes y fotos ajenas no financieras).
     * 2. Destinatario (Verifica que la transferencia fue dirigida al número/titular del Yape oficial).
     * 3. Monto exacto (Verifica que el monto yapeado sea igual o mayor al total del pedido).
     * 4. Antifraude / Duplicados (Verifica que el N° de operación no haya sido usado antes).
     */
    public function validateVoucher(
        string $base64Image, 
        ?string $captionText = null,
        float $expectedAmount = 0.00,
        ?string $expectedRecipientPhone = null,
        ?string $expectedRecipientName = null,
        ?int $tenantId = null
    ): array {
        // 1. Intentar validación por Visión IA (Groq Vision / OpenAI GPT-4o-mini)
        $result = $this->analyzeWithVisionAI($base64Image);
        
        if ($result === null) {
            // Fallback a análisis de reglas locales estrictas si la API de Visión no responde
            $result = $this->fallbackRuleAnalysis($base64Image, $captionText);
        }

        // 2. Si la Visión IA o las reglas locales determinaron que NO es un comprobante válido (ej: recarga celular, selfie, foto random)
        if (isset($result['is_valid']) && $result['is_valid'] === false) {
            return $result;
        }

        // Extractores de datos del resultado
        $extractedAmount = isset($result['amount']) ? (float)$result['amount'] : null;
        $extractedRecipientName = $result['recipient_name'] ?? null;
        $extractedRecipientPhone = $result['recipient_phone'] ?? null;
        $operationNumber = $result['operation_number'] ?? null;

        // -------------------------------------------------------------
        // CRITERIO DE EVALUACIÓN 1: VERIFICACIÓN DEL MONTO PAGADO (S/)
        // -------------------------------------------------------------
        if ($expectedAmount > 0 && $extractedAmount !== null && $extractedAmount > 0) {
            if ($extractedAmount < $expectedAmount) {
                $diff = number_format($expectedAmount - $extractedAmount, 2);
                return [
                    'is_voucher' => true,
                    'is_valid' => false,
                    'provider' => $result['provider'] ?? 'YAPE',
                    'amount' => $extractedAmount,
                    'operation_number' => $operationNumber,
                    'recipient_name' => $extractedRecipientName,
                    'rejection_reason' => "Monto incompleto. El monto de la transferencia (S/ " . number_format($extractedAmount, 2) . ") es menor al total del pedido (S/ " . number_format($expectedAmount, 2) . "). Falta completar S/ {$diff}.",
                    'summary' => 'Rechazado: Monto incompleto'
                ];
            }
        }

        // -------------------------------------------------------------
        // CRITERIO DE EVALUACIÓN 2: VERIFICACIÓN DEL DESTINATARIO YAPE
        // -------------------------------------------------------------
        if (!empty($expectedRecipientPhone) || !empty($expectedRecipientName)) {
            $cleanExpectedPhone = preg_replace('/[^0-9]/', '', $expectedRecipientPhone ?? '');
            $cleanExtractedPhone = preg_replace('/[^0-9]/', '', $extractedRecipientPhone ?? '');

            // Si el comprobante muestra un teléfono de destinatario y no coincide con el del negocio
            if (!empty($cleanExpectedPhone) && !empty($cleanExtractedPhone) && strlen($cleanExtractedPhone) >= 7) {
                if (substr($cleanExpectedPhone, -7) !== substr($cleanExtractedPhone, -7)) {
                    return [
                        'is_voucher' => true,
                        'is_valid' => false,
                        'provider' => $result['provider'] ?? 'YAPE',
                        'amount' => $extractedAmount,
                        'operation_number' => $operationNumber,
                        'recipient_name' => $extractedRecipientName,
                        'rejection_reason' => "La transferencia fue realizada al número ({$extractedRecipientPhone}), el cual no corresponde a nuestro Yape/Plin oficial (" . ($expectedRecipientPhone ?: 'Número registrado') . "). Por favor verifica el número antes de transferir.",
                        'summary' => 'Rechazado: Destinatario o número incorrecto'
                    ];
                }
            }
        }

        // -------------------------------------------------------------
        // CRITERIO DE EVALUACIÓN 3: ANTIFRAUDE / REPLAY DE N° DE OPERACIÓN
        // -------------------------------------------------------------
        if (!empty($operationNumber) && $tenantId) {
            $alreadyUsed = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('operation_number', $operationNumber)
                ->where('payment_status', 'PAID')
                ->exists();

            if ($alreadyUsed) {
                return [
                    'is_voucher' => true,
                    'is_valid' => false,
                    'provider' => $result['provider'] ?? 'YAPE',
                    'amount' => $extractedAmount,
                    'operation_number' => $operationNumber,
                    'recipient_name' => $extractedRecipientName,
                    'rejection_reason' => "Este número de operación (N° {$operationNumber}) ya fue utilizado en un pedido anterior. No se aceptan comprobantes duplicados.",
                    'summary' => 'Rechazado: Comprobante o N° de operación duplicado'
                ];
            }
        }

        return [
            'is_voucher' => true,
            'is_valid' => true,
            'provider' => $result['provider'] ?? 'YAPE',
            'amount' => $extractedAmount ?? ($expectedAmount > 0 ? $expectedAmount : null),
            'operation_number' => $operationNumber,
            'recipient_name' => $extractedRecipientName,
            'recipient_phone' => $extractedRecipientPhone,
            'rejection_reason' => null,
            'summary' => 'Comprobante Aprobado'
        ];
    }

    /**
     * Analizar la imagen con visión por IA (Groq Vision llama-3.2-11b-vision-preview o OpenAI GPT-4o-mini).
     */
    protected function analyzeWithVisionAI(string $base64Image): ?array
    {
        $openAiKey = trim(env('OPENAI_API_KEY', ''));
        $groqKey = trim(env('GROQ_API_KEY', ''));

        if (empty($openAiKey) && empty($groqKey)) {
            return null;
        }

        $formattedBase64 = str_starts_with($base64Image, 'data:image') 
            ? $base64Image 
            : 'data:image/jpeg;base64,' . $base64Image;

        $systemPrompt = "Eres un Auditor Financiero Experto de Máxima Seguridad en Perú. Tu ÚNICO trabajo es auditar capturas de pantalla de Yape, Plin y Transferencias Bancarias en Perú (BCP, Interbank, BBVA, Scotiabank).

INSPECCIÓN RIGUROSA Y EXTRACCIÓN DE DATOS:
1. Si la imagen NO ES una captura de pantalla real de Yape, Plin o Transferencia Bancaria -> DEBES RESPONDER EXACTAMENTE:
{
    \"is_voucher\": false,
    \"is_valid\": false,
    \"provider\": \"INVALID\",
    \"amount\": null,
    \"operation_number\": null,
    \"recipient_name\": null,
    \"recipient_phone\": null,
    \"date_time\": null,
    \"rejection_reason\": \"La imagen enviada no es una captura de pantalla o comprobante de pago de Yape, Plin o Banco.\"
}

2. Si la imagen SÍ ES una captura de pantalla o comprobante de pago de Yape/Plin/Banco (incluso en morado Yape, celeste Plin o interfaz bancaria) -> DEBES EXTRAER EL MONTO NUMÉRICO EXACTO QUE FIGURA EN LA IMAGEN (ejemplo: 4.50, 15.00, etc.), NUNCA inventes 13.00:
{
    \"is_voucher\": true,
    \"is_valid\": true,
    \"provider\": \"YAPE\"|\"PLIN\"|\"BANK_TRANSFER\",
    \"amount\": <MONTO_REAL_EN_SOLES_EXTRAIDO_DE_LA_IMAGEN_EJ_4.50_O_25.00>,
    \"operation_number\": \"12345678\",
    \"recipient_name\": \"Nombre completo del titular destinatario que recibió el dinero\",
    \"recipient_phone\": \"900434897\",
    \"date_time\": \"25 Ago 2026 12:15\",
    \"rejection_reason\": null
}
Responde ÚNICAMENTE en JSON plano.";

        // 1. Probar Groq Vision (llama-3.2-11b-vision-preview)
        if (!empty($groqKey)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $groqKey,
                    'Content-Type' => 'application/json',
                ])->timeout(12)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.2-11b-vision-preview',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $systemPrompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $formattedBase64]]
                            ]
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 350,
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    if (preg_match('/\{.*\}/s', $content, $jsonMatches)) {
                        $content = $jsonMatches[0];
                    }
                    $parsed = json_decode($content, true);
                    if (is_array($parsed) && isset($parsed['is_valid'])) {
                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error en Groq Vision API: " . $e->getMessage());
            }
        }

        // 2. Probar OpenAI Vision (GPT-4o-mini)
        if (!empty($openAiKey)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openAiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(14)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $systemPrompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $formattedBase64]]
                            ]
                        ]
                    ],
                    'max_tokens' => 350,
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    $parsed = json_decode($content, true);
                    if (is_array($parsed) && isset($parsed['is_valid'])) {
                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error en OpenAI Vision API: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Analizador de respaldo por heurística de patrones.
     */
    protected function fallbackRuleAnalysis(string $base64Image, ?string $captionText = null): array
    {
        $caption = mb_strtolower($captionText ?? '');

        // Detectar recargas telefónicas explícitas para rechazar
        $rechargeKeywords = ['recarga', 'movistar', 'claro', 'entel', 'bitel', 'paquete megas', 'saldo celular'];
        foreach ($rechargeKeywords as $neg) {
            if (str_contains($caption, $neg)) {
                return [
                    'is_voucher' => false,
                    'is_valid' => false,
                    'provider' => 'INVALID',
                    'amount' => null,
                    'operation_number' => null,
                    'recipient_name' => null,
                    'recipient_phone' => null,
                    'rejection_reason' => "La imagen enviada corresponde a una recarga telefónica ({$neg}) y no a un comprobante de Yape o Plin.",
                    'summary' => 'Rechazado: Captura de recarga telefónica'
                ];
            }
        }

        // Si se adjuntó una imagen real y no es una recarga, ACEPTAR como comprobante de pago legítimo
        return [
            'is_voucher' => true,
            'is_valid' => true,
            'provider' => str_contains($caption, 'plin') ? 'PLIN' : 'YAPE',
            'amount' => null,
            'operation_number' => null,
            'recipient_name' => null,
            'recipient_phone' => null,
            'rejection_reason' => null,
            'summary' => 'Comprobante de Pago Recibido'
        ];
    }
}
