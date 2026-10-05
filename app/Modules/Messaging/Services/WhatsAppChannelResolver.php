<?php

namespace App\Modules\Messaging\Services;

use App\Core\Exceptions\TenantContextMismatchException;
use App\Core\Exceptions\TenantResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppChannelResolver
{
    /**
     * Resuelve determinísticamente el tenant_id a partir de la sesión de Gateway o número de canal (TCI-00 & TCI-00.1).
     *
     * @param string|null $sessionId  Identificador de sesión (ej: 'wallpa-brasa', 'donuts-fs')
     * @param string|null $phoneIdentifier  Identificador de teléfono conectado o WABA
     * @param string|null $correlationId  ID de trazabilidad de la petición
     * @return int Tenant ID resuelto con binding oficial único y activo
     *
     * @throws TenantResolutionException si no existe binding activo (0 resultados)
     * @throws TenantContextMismatchException si existe ambigüedad (>1 resultados)
     */
    public static function resolveStrictBinding(?string $sessionId, ?string $phoneIdentifier = null, ?string $correlationId = null): int
    {
        // Prohibir explícitamente el uso de sesión 'default' como identidad comercial
        if ($sessionId === 'default') {
            // Comprobar si hay un registro explícito en DB para 'default'
            $hasExplicitDefault = DB::table('whatsapp_channels')
                ->where('client_session_id', 'default')
                ->where('status', 'ACTIVE')
                ->exists();

            if (!$hasExplicitDefault) {
                throw new TenantResolutionException(
                    "Sesión 'default' no autorizada para operar comercialmente. Se requiere un client_session_id registrado.",
                    $correlationId
                );
            }
        }

        if (empty($sessionId) && empty($phoneIdentifier)) {
            throw new TenantResolutionException(
                "No se proporcionó identificador de sesión ni canal telefónico para resolver el Tenant.",
                $correlationId
            );
        }

        if (!Schema::hasTable('whatsapp_channels')) {
            throw new TenantResolutionException(
                "Tabla whatsapp_channels no disponible en el sistema.",
                $correlationId
            );
        }

        // P3.13-08: Si se proporciona identificador físico telefónico (connected_phone / phoneIdentifier),
        // Laravel aplica su propia normalización canónica y este tiene prioridad absoluta sobre la sesión técnica.
        if (!empty($phoneIdentifier)) {
            $canonicalPhone = \App\Modules\Core\Identity\PhoneNormalizer::toCanonical($phoneIdentifier);
            $nationalPhone = \App\Modules\Core\Identity\PhoneNormalizer::toNational($phoneIdentifier);

            $phoneQuery = DB::table('whatsapp_channels')
                ->join('tenants', 'tenants.id', '=', 'whatsapp_channels.tenant_id')
                ->where('whatsapp_channels.status', 'ACTIVE')
                ->where('tenants.status', 'ACTIVE')
                ->where(function ($q) use ($canonicalPhone, $nationalPhone, $phoneIdentifier) {
                    $q->where('whatsapp_channels.active_phone_e164', $canonicalPhone)
                      ->orWhere('whatsapp_channels.display_phone_number', $nationalPhone)
                      ->orWhere('whatsapp_channels.phone_number_id', $phoneIdentifier);
                });

            $matchedTenantIds = $phoneQuery->pluck('whatsapp_channels.tenant_id')->unique()->values();

            if ($matchedTenantIds->count() === 1) {
                return (int) $matchedTenantIds->first();
            }

            if ($matchedTenantIds->count() > 1) {
                throw new TenantContextMismatchException(
                    "Resolución ambigua de canal físico (P3.13-08): Múltiples tenants activos (" . $matchedTenantIds->join(', ') . ") vinculados al teléfono [{$phoneIdentifier}].",
                    $correlationId
                );
            }
            // Si no hubo coincidencia por teléfono y NO se proveyó sessionId, falla cerrado
            if (empty($sessionId)) {
                throw new TenantResolutionException(
                    "No se encontró un binding activo registrado para el teléfono conectado [{$phoneIdentifier}] (P3.13-08).",
                    $correlationId
                );
            }
        }

        // Resolución por client_session_id técnico (solo cuando el teléfono no resolvió o no fue suministrado)
        $sessionQuery = DB::table('whatsapp_channels')
            ->join('tenants', 'tenants.id', '=', 'whatsapp_channels.tenant_id')
            ->where('whatsapp_channels.status', 'ACTIVE')
            ->where('tenants.status', 'ACTIVE');

        if (!empty($sessionId) && Schema::hasColumn('whatsapp_channels', 'client_session_id')) {
            $sessionQuery->where('whatsapp_channels.client_session_id', $sessionId);
        }

        $matchedTenantIds = $sessionQuery->pluck('whatsapp_channels.tenant_id')->unique()->values();

        // TCI-00.1 / P3.13-08: Evaluación estricta de cardinalidad de bindings
        if ($matchedTenantIds->isEmpty()) {
            throw new TenantResolutionException(
                "No se encontró un binding activo registrado para la sesión [{$sessionId}] / canal [{$phoneIdentifier}].",
                $correlationId
            );
        }

        if ($matchedTenantIds->count() > 1) {
            throw new TenantContextMismatchException(
                "Resolución ambigua de canal (P3.13-08): Existen múltiples tenants activos (" . $matchedTenantIds->join(', ') . ") vinculados a la misma sesión.",
                $correlationId
            );
        }

        return (int) $matchedTenantIds->first();
    }

    /**
     * Backward compatibility para Webhooks Meta oficiales (WABA Cloud API).
     */
    public static function resolveFromPhoneId(?string $phoneNumberId): int
    {
        return self::resolveStrictBinding(null, $phoneNumberId);
    }
}
