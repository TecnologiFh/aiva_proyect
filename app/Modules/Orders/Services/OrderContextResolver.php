<?php

namespace App\Modules\Orders\Services;

use App\Modules\Core\Identity\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderContextResolver
{
    /**
     * Reconstruir el CanonicalConversationContext determinístico directamente desde MySQL
     * garantizando aislamiento multi-tenant e Invariantes CI-01, CI-02, CI-03.
     */
    public function resolveContext(int $tenantId, string $customerPhone, ?string $messageText = null): array
    {
        $canonicalPhone = PhoneNormalizer::toCanonical($customerPhone);
        $nationalPhone = PhoneNormalizer::toNational($customerPhone);
        $e164Phone = PhoneNormalizer::toE164($customerPhone);
        $correlationId = 'CORR-' . strtoupper(Str::random(8));

        // AG-44: Detección de referencia explícita a orden (ej. "pedido 152", "orden #ORD-1234")
        $explicitOrderRef = null;
        if (!empty($messageText) && preg_match('/(?:pedido|orden|ticket)\s*(?:#|num|numero)?\s*([0-9a-z\-_]{2,})/i', $messageText, $mOrd)) {
            $explicitOrderRef = trim($mOrd[1]);
        }

        // 1. Si hay referencia explícita, saltar frontera temporal de 4 horas (AG-44 Bypass)
        if ($explicitOrderRef) {
            $explicitOrder = PhoneNormalizer::applyCanonicalQuery(
                DB::table('orders'),
                $tenantId,
                $customerPhone
            )->where(function($q) use ($explicitOrderRef) {
                if (is_numeric($explicitOrderRef)) {
                    $q->where('id', (int)$explicitOrderRef);
                }
                $q->orWhere('order_number', $explicitOrderRef)
                  ->orWhere('order_number', 'like', "%{$explicitOrderRef}%");
            })->first();

            if ($explicitOrder) {
                $isPendingPayment = ($explicitOrder->payment_status === 'PENDING');
                $orderStatus = $isPendingPayment ? 'AWAITING_PAYMENT' : $explicitOrder->status;
                $convState = $isPendingPayment ? 'AWAITING_PAYMENT_EVIDENCE' : 'ORDER_CONFIRMED';

                return [
                    'context_resolution' => [
                        'status' => 'RESOLVED',
                        'source' => 'MYSQL_EXPLICIT_REFERENCE',
                        'resolved_at' => now()->toDateTimeString(),
                        'order_id' => $explicitOrder->id,
                        'correlation_id' => $correlationId,
                    ],
                    'customer' => [
                        'phone_canonical' => $canonicalPhone,
                        'phone_national' => $nationalPhone,
                        'phone_e164' => $e164Phone,
                    ],
                    'order' => [
                        'exists' => true,
                        'id' => $explicitOrder->id,
                        'order_number' => $explicitOrder->order_number,
                        'status' => $orderStatus,
                        'total_amount' => (float)$explicitOrder->total_amount,
                        'delivery_zone' => $explicitOrder->delivery_zone,
                        'relevance' => 'EXPLICIT_REFERENCE',
                    ],
                    'payment' => [
                        'status' => $explicitOrder->payment_status,
                        'awaiting_evidence' => $isPendingPayment,
                    ],
                    'conversation' => [
                        'state' => $convState,
                    ],
                ];
            }
        }

        // 2. Búsqueda contextual regular: Acotada a la sesión comercial activa (últimas 4 horas) (AG-44)
        $sessionInactivityCutoff = now()->subHours(4);
        $activeOrders = PhoneNormalizer::applyCanonicalQuery(
            DB::table('orders'),
            $tenantId,
            $customerPhone
        )->whereNotIn('status', ['CANCELLED', 'DELIVERED', 'COMPLETED'])
         ->where(function($q) use ($sessionInactivityCutoff) {
             $q->where('created_at', '>=', $sessionInactivityCutoff)
               ->orWhere('updated_at', '>=', $sessionInactivityCutoff);
         })
         ->orderByDesc('id')
         ->get();

        // Si existen múltiples órdenes activas abiertas -> CONTEXT_CONFLICT (CI-GATE-5)
        if ($activeOrders->count() > 1) {
            return [
                'context_resolution' => [
                    'status' => 'CONFLICT',
                    'source' => 'MYSQL',
                    'resolved_at' => now()->toDateTimeString(),
                    'order_id' => null,
                    'correlation_id' => $correlationId,
                    'conflict_reason' => 'MULTIPLE_ACTIVE_ORDERS_FOUND',
                ],
                'customer' => [
                    'phone_canonical' => $canonicalPhone,
                    'phone_national' => $nationalPhone,
                    'phone_e164' => $e164Phone,
                ],
                'order' => [
                    'exists' => true,
                    'id' => null,
                    'order_number' => null,
                    'status' => 'CONTEXT_CONFLICT',
                    'total_amount' => 0.00,
                    'delivery_zone' => null,
                    'conflicting_orders' => $activeOrders->map(fn($o) => [
                        'order_number' => $o->order_number,
                        'total_amount' => (float)$o->total_amount,
                        'status' => $o->status
                    ])->toArray()
                ],
                'payment' => [
                    'status' => 'PENDING',
                    'awaiting_evidence' => true,
                ],
                'conversation' => [
                    'state' => 'CONTEXT_CONFLICT',
                ],
            ];
        }

        $activeOrder = $activeOrders->first();

        if (!$activeOrder) {
            return [
                'context_resolution' => [
                    'status' => 'NO_ORDER',
                    'source' => 'MYSQL',
                    'resolved_at' => now()->toDateTimeString(),
                    'order_id' => null,
                    'correlation_id' => $correlationId,
                ],
                'customer' => [
                    'phone_canonical' => $canonicalPhone,
                    'phone_national' => $nationalPhone,
                    'phone_e164' => $e164Phone,
                ],
                'order' => [
                    'exists' => false,
                    'id' => null,
                    'order_number' => null,
                    'status' => 'IDLE',
                    'total_amount' => 0.00,
                    'delivery_zone' => null,
                ],
                'payment' => [
                    'status' => 'NONE',
                    'awaiting_evidence' => false,
                ],
                'conversation' => [
                    'state' => 'IDLE',
                ],
            ];
        }

        // Mapear estado determinístico
        $isPendingPayment = ($activeOrder->payment_status === 'PENDING');
        $orderStatus = $isPendingPayment ? 'AWAITING_PAYMENT' : $activeOrder->status;
        $convState = $isPendingPayment ? 'AWAITING_PAYMENT_EVIDENCE' : 'ORDER_CONFIRMED';

        return [
            'context_resolution' => [
                'status' => 'RESOLVED',
                'source' => 'MYSQL',
                'resolved_at' => now()->toDateTimeString(),
                'order_id' => $activeOrder->id,
                'correlation_id' => $correlationId,
            ],
            'customer' => [
                'phone_canonical' => $canonicalPhone,
                'phone_national' => $nationalPhone,
                'phone_e164' => $e164Phone,
            ],
            'order' => [
                'exists' => true,
                'id' => $activeOrder->id,
                'order_number' => $activeOrder->order_number,
                'status' => $orderStatus,
                'total_amount' => (float)$activeOrder->total_amount,
                'delivery_zone' => $activeOrder->delivery_zone,
            ],
            'payment' => [
                'status' => $activeOrder->payment_status,
                'awaiting_evidence' => $isPendingPayment,
            ],
            'conversation' => [
                'state' => $convState,
            ],
        ];
    }
}
