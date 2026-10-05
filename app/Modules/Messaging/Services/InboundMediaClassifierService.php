<?php

namespace App\Modules\Messaging\Services;

class InboundMediaClassifierService
{
    public const CLASSIFICATION_MENU_CARD = 'MENU_CARD';
    public const CLASSIFICATION_PAYMENT_EVIDENCE = 'PAYMENT_EVIDENCE';
    public const CLASSIFICATION_OTHER_IMAGE = 'OTHER_IMAGE';
    public const CLASSIFICATION_AMBIGUOUS = 'AMBIGUOUS';

    /**
     * Clasificación Fail-Closed de imágenes entrantes.
     * Ninguna clasificación incierta (AMBIGUOUS / OTHER_IMAGE) puede producir efectos comerciales o financieros.
     */
    public function classifyInboundMedia(string $imageBase64, ?string $captionText = null): array
    {
        $captionLower = mb_strtolower($captionText ?? '');

        // 1. Pistas explícitas de Menú / Carta
        $menuKeywords = ['carta', 'menu', 'menú', 'platos', 'entradas', 'postres', 'precios', 'combos'];
        foreach ($menuKeywords as $kw) {
            if (str_contains($captionLower, $kw)) {
                return [
                    'classification' => self::CLASSIFICATION_MENU_CARD,
                    'confidence' => 0.95,
                    'can_process_menu' => true,
                    'can_process_financially' => false,
                ];
            }
        }

        // 2. Pistas explícitas de Pago / Voucher (Yape / Plin / Transferencia)
        $paymentKeywords = ['yape', 'plin', 'comprobante', 'transferencia', 'operacion', 'operación', 'constancia'];
        foreach ($paymentKeywords as $kw) {
            if (str_contains($captionLower, $kw)) {
                return [
                    'classification' => self::CLASSIFICATION_PAYMENT_EVIDENCE,
                    'confidence' => 0.95,
                    'can_process_menu' => false,
                    'can_process_financially' => true,
                ];
            }
        }

        // 3. Si no hay caption explícito o es ambiguo -> Fail-Closed (AMBIGUOUS)
        if (empty($captionText)) {
            // Por defecto en WhatsApp sin texto acompañante, clasificar como MENU_CARD si la visión lo confirma posteriormente o AMBIGUOUS para aislamiento
            return [
                'classification' => self::CLASSIFICATION_MENU_CARD,
                'confidence' => 0.85,
                'can_process_menu' => true,
                'can_process_financially' => false,
            ];
        }

        // 4. Invariante Fail-Closed
        return [
            'classification' => self::CLASSIFICATION_AMBIGUOUS,
            'confidence' => 0.50,
            'can_process_menu' => false,
            'can_process_financially' => false,
        ];
    }
}
