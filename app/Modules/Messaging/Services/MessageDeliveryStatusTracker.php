<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Messaging\Models\InboundWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageDeliveryStatusTracker
{
    public const STATUS_WEIGHTS = [
        'PENDING'   => 10,
        'SENT'      => 20,
        'DELIVERED' => 30,
        'READ'      => 40,
        'FAILED'    => 50,
    ];

    /**
     * Actualizar atómicamente el estado de entrega en MySQL respetando la máquina monotónica.
     * Garantiza que eventos desfasados (ej. READ recibido antes de DELIVERED o SENT)
     * no puedan degradar el estado superior ya alcanzado.
     */
    public function updateStatusMonotonic(int $tenantId, string $wamid, string $rawStatus): array
    {
        $normalizedStatus = strtoupper(trim($rawStatus));
        $newWeight = self::STATUS_WEIGHTS[$normalizedStatus] ?? 0;

        // Asegurar que el registro exista con firstOrCreate
        $event = InboundWebhookEvent::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'whatsapp_message_id' => $wamid,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'status' => 'RECEIVED',
                'delivery_status' => $normalizedStatus,
                'delivery_weight' => $newWeight,
                'delivery_updated_at' => now(),
            ]
        );

        if ($event->wasRecentlyCreated) {
            return [
                'status' => $event->delivery_status,
                'weight' => $newWeight,
                'updated' => true,
                'ignored_out_of_order' => false,
            ];
        }

        $currentWeight = (int) ($event->delivery_weight ?? 0);

        if ($newWeight > $currentWeight) {
            // Actualización atómica con condición de peso en MySQL
            $affected = DB::table('inbound_webhook_events')
                ->where('tenant_id', $tenantId)
                ->where('whatsapp_message_id', $wamid)
                ->where(function ($query) use ($newWeight) {
                    $query->where('delivery_weight', '<', $newWeight)
                          ->orWhereNull('delivery_weight');
                })
                ->update([
                    'delivery_status' => $normalizedStatus,
                    'delivery_weight' => $newWeight,
                    'delivery_updated_at' => now(),
                ]);

            $event->refresh();
            return [
                'status' => $event->delivery_status,
                'weight' => (int) $event->delivery_weight,
                'updated' => ($affected > 0),
                'ignored_out_of_order' => false,
            ];
        }

        return [
            'status' => $event->delivery_status,
            'weight' => $currentWeight,
            'updated' => false,
            'ignored_out_of_order' => true,
        ];
    }

    /**
     * Obtener el estado de entrega actual directamente desde la autoridad en MySQL
     */
    public function getStatus(int $tenantId, string $wamid): ?array
    {
        $event = InboundWebhookEvent::where('tenant_id', $tenantId)
            ->where('whatsapp_message_id', $wamid)
            ->first();

        if (!$event) {
            return null;
        }

        return [
            'status' => $event->delivery_status ?? 'UNKNOWN',
            'weight' => (int) ($event->delivery_weight ?? 0),
            'updated_at' => $event->delivery_updated_at?->toIso8601String(),
        ];
    }
}
