<?php

namespace App\Modules\Messaging\Services;

class CustomerPhoneNormalizer
{
    /**
     * Normalizar un número telefónico a una cadena numérica canónica determinista.
     * Elimina prefijos '+', '00', guiones, espacios y aplica formato internacional canónico.
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '000000000';
        }

        // 1. Eliminar cualquier caracter no numérico
        $cleaned = preg_replace('/\D+/', '', (string)$phone);

        // 2. Eliminar prefijo '00' internacional
        if (str_starts_with($cleaned, '00')) {
            $cleaned = substr($cleaned, 2);
        }

        // 3. Caso especial Perú: Si son 9 dígitos empezando en 9 (móvil nacional), anteponer código país 51
        if (strlen($cleaned) === 9 && str_starts_with($cleaned, '9')) {
            $cleaned = '51' . $cleaned;
        }

        return !empty($cleaned) ? $cleaned : '000000000';
    }
}
