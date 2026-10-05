<?php

namespace App\Modules\Core\Identity;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class PhoneNormalizer
{
    /**
     * Normalizar teléfono a su formato numérico canónico (ej: 51900434897 para Perú).
     */
    public static function toCanonical(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (empty($digits)) {
            return '';
        }

        // Si son 9 dígitos (ej. 900434897 en Perú), anteponer código del país 51
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        // Si viene con prefijo internacional largo (ej: 0051900434897)
        if (str_starts_with($digits, '0051')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Retorna el número nacional peruano de 9 dígitos.
     */
    public static function toNational(?string $phone): string
    {
        $canonical = self::toCanonical($phone);
        if (strlen($canonical) === 11 && str_starts_with($canonical, '519')) {
            return substr($canonical, 2);
        }
        return strlen($canonical) >= 9 ? substr($canonical, -9) : $canonical;
    }

    /**
     * Retorna el número en formato internacional e.164 (ej: +51900434897).
     */
    public static function toE164(?string $phone): string
    {
        $canonical = self::toCanonical($phone);
        return !empty($canonical) ? '+' . $canonical : '';
    }

    /**
     * Aplica consulta canónica garantizando aislamiento Multi-Tenant (tenant_id)
     * e idéntica resolución sobre MySQL.
     */
    public static function applyCanonicalQuery($query, int $tenantId, string $phone, string $column = 'customer_phone')
    {
        $canonical = self::toCanonical($phone);
        $national = self::toNational($phone);
        $e164 = self::toE164($phone);

        $phonesToMatch = array_unique(array_filter([
            $canonical,
            $national,
            $e164,
            '+' . $canonical,
            trim($phone)
        ]));

        return $query->where('tenant_id', $tenantId)
            ->where(function ($q) use ($column, $phonesToMatch) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($q->from ?? 'orders', 'customer_phone_canonical')) {
                    $q->whereIn('customer_phone_canonical', $phonesToMatch)
                      ->orWhereIn($column, $phonesToMatch);
                } else {
                    $q->whereIn($column, $phonesToMatch);
                }
            });
    }
}
