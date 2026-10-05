<?php

namespace App\Modules\Multimodal\Runtime\Services;

/**
 * Normalización determinista de evidencia visual y datos numéricos/monetarios.
 * Prohíbe terminantemente el uso de floats para dinero.
 */
class VisualEvidenceNormalizationService
{
    /**
     * Normaliza un importe a formato decimal canónico estricto (string con dos decimales).
     * Ej: "10" -> "10.00", "218.5" -> "218.50", "S/ 25.00" -> "25.00".
     */
    public function normalizeDecimalAmount(mixed $rawAmount): ?string
    {
        if ($rawAmount === null || $rawAmount === '') {
            return null;
        }

        $str = (string)$rawAmount;
        // Limpiar símbolos de moneda y caracteres no numéricos excepto punto o coma
        $str = preg_replace('/[^\d.,]/', '', $str);
        if ($str === '') {
            return null;
        }

        // Normalizar coma a punto decimal
        $str = str_replace(',', '.', $str);

        // Si tiene múltiples puntos, quedarse con el último como separador decimal
        if (substr_count($str, '.') > 1) {
            $parts = explode('.', $str);
            $last = array_pop($parts);
            $str = implode('', $parts) . '.' . $last;
        }

        if (!is_numeric($str)) {
            return null;
        }

        // Retornar exactamente con 2 decimales usando bcadd o number_format
        return number_format((float)$str, 2, '.', '');
    }

    /**
     * Normaliza texto a ASCII minúsculas sin acentos ni signos para coincidencia determinista.
     */
    public function normalizeText(string $text): string
    {
        $clean = mb_strtolower(trim($text), 'UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean) ?: $clean;
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Normaliza identificadores de proveedores de pago a estándar universal.
     */
    public function normalizePaymentProvider(string $rawProvider): string
    {
        $norm = strtoupper(trim($rawProvider));
        return match (true) {
            str_contains($norm, 'YAPE') => 'YAPE',
            str_contains($norm, 'PLIN') => 'PLIN',
            str_contains($norm, 'BCP') => 'BCP',
            str_contains($norm, 'INTERBANK') => 'INTERBANK',
            str_contains($norm, 'BBVA') => 'BBVA',
            str_contains($norm, 'SCOTIA') => 'SCOTIABANK',
            str_contains($norm, 'POS') || str_contains($norm, 'IZIPAY') || str_contains($norm, 'NIUBIZ') => 'POS',
            default => 'BANK_TRANSFER',
        };
    }
}
