<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Core\Identity\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WhatsAppChannelBindingService
{
    /**
     * Vincula o reasigna atómicamente un número físico de WhatsApp a un Tenant.
     * Garantía P3.13-08 & P3.13-09: Unicidad a nivel de DB con row locking (lockForUpdate).
     *
     * @param int $tenantId ID del tenant que solicita la vinculación
     * @param string $rawPhone Número telefónico en cualquier formato (+51..., 9..., etc.)
     * @param string|null $clientSessionId Identificador técnico opcional de sesión WWebJS
     * @return array Resultado de la vinculación y auditoría
     *
     * @throws ConflictHttpException Si existe un bloqueo irreconciliable
     */
    public function bindChannel(int $tenantId, string $rawPhone, ?string $clientSessionId = null): array
    {
        $cleanNational = preg_replace('/[^0-9]/', '', $rawPhone);
        if (empty($cleanNational)) {
            throw new \InvalidArgumentException("El número de teléfono proporcionado no es válido.");
        }

        $canonicalPhone = PhoneNormalizer::toCanonical($rawPhone);
        $nationalPhone = PhoneNormalizer::toNational($rawPhone);

        return DB::transaction(function () use ($tenantId, $canonicalPhone, $nationalPhone, $clientSessionId) {
            // 1. Obtener el slug oficial del tenant para el client_session_id técnico
            $tenantSlug = DB::table('tenants')->where('id', $tenantId)->value('slug') ?? ('tenant-' . $tenantId);
            $finalSessionId = $clientSessionId ?: $tenantSlug;

            // 2. Identificar el canal destino para el tenant objetivo:
            // Preferir uno que ya coincida con este teléfono, o el primer canal del tenant
            $targetChannel = DB::table('whatsapp_channels')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($canonicalPhone, $nationalPhone) {
                    $q->where('active_phone_e164', $canonicalPhone)
                      ->orWhere('display_phone_number', $nationalPhone);
                })
                ->lockForUpdate()
                ->first();

            if (!$targetChannel) {
                $targetChannel = DB::table('whatsapp_channels')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();
            }

            // 3. Lock de todas las filas existentes en la tabla asociadas a este teléfono
            $existingActiveBindings = DB::table('whatsapp_channels')
                ->where(function ($q) use ($canonicalPhone, $nationalPhone) {
                    $q->where('active_phone_e164', $canonicalPhone)
                      ->orWhere('display_phone_number', $nationalPhone);
                })
                ->lockForUpdate()
                ->get();

            $deactivatedTenants = [];

            // 4. Desactivar cualquier otra fila para garantizar unicidad estricta (P3.13-05 / P3.13-09)
            foreach ($existingActiveBindings as $binding) {
                // Si la fila no es el targetChannel seleccionado, liberamos el número
                if (!$targetChannel || (int)$binding->id !== (int)$targetChannel->id) {
                    DB::table('whatsapp_channels')
                        ->where('id', $binding->id)
                        ->update([
                            'active_phone_e164' => null,
                            'status' => 'INACTIVE',
                            'updated_at' => now(),
                        ]);

                    // Si pertenecía a otro tenant, limpiar también en business_profiles y registrar
                    if ((int)$binding->tenant_id !== $tenantId) {
                        if (Schema::hasTable('business_profiles')) {
                            DB::table('business_profiles')
                                ->where('tenant_id', $binding->tenant_id)
                                ->where(function ($q) use ($nationalPhone, $canonicalPhone) {
                                    $q->where('phone', $nationalPhone)
                                      ->orWhere('phone', $canonicalPhone);
                                })
                                ->update([
                                    'phone' => null,
                                    'updated_at' => now(),
                                ]);
                        }
                        $deactivatedTenants[] = (int)$binding->tenant_id;
                    }
                }
            }

            // 5. Actualizar o insertar el canal del tenant objetivo
            if ($targetChannel) {
                DB::table('whatsapp_channels')
                    ->where('id', $targetChannel->id)
                    ->update([
                        'client_session_id' => $finalSessionId,
                        'display_phone_number' => $nationalPhone,
                        'active_phone_e164' => $canonicalPhone,
                        'status' => 'ACTIVE',
                        'updated_at' => now(),
                    ]);
                $channelId = $targetChannel->id;
            } else {
                $channelId = DB::table('whatsapp_channels')->insertGetId([
                    'tenant_id' => $tenantId,
                    'client_session_id' => $finalSessionId,
                    'phone_number_id' => null,
                    'display_phone_number' => $nationalPhone,
                    'active_phone_e164' => $canonicalPhone,
                    'status' => 'ACTIVE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Actualizar el perfil del negocio objetivo
            if (Schema::hasTable('business_profiles')) {
                DB::table('business_profiles')
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'phone' => $nationalPhone,
                        'updated_at' => now(),
                    ]);
            }

            // 6. Auditoría inmutable de reasignación
            \Illuminate\Support\Facades\Log::info("P3.13-09: WhatsApp Channel Reassigned", [
                'tenant_id' => $tenantId,
                'channel_id' => $channelId,
                'canonical_phone' => $canonicalPhone,
                'national_phone' => $nationalPhone,
                'client_session_id' => $finalSessionId,
                'deactivated_tenants' => $deactivatedTenants,
            ]);

            return [
                'status' => 'SUCCESS',
                'channel_id' => $channelId,
                'tenant_id' => $tenantId,
                'canonical_phone' => $canonicalPhone,
                'national_phone' => $nationalPhone,
                'client_session_id' => $finalSessionId,
                'deactivated_tenants' => $deactivatedTenants,
            ];
        });
    }
}
