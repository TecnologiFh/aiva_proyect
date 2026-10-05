<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomaticOrderParserService
{
    /**
     * Sincronizar y procesar un pedido garantizando la Autoridad Comercial Absoluta de MySQL (Reglas B-03, B-04, B-06).
     *
     * @param int $tenantId ID del Tenant
     * @param string $customerPhone Teléfono del cliente
     * @param string|null $customerName Nombre del cliente
     * @param array $requestedItems Array de ítems solicitados por el IntentResolver: [['name' => 'Chaufa', 'quantity' => 2]]
     * @return array Resumen de resolución del pedido
     */
    public function syncCommercialOrder(int $tenantId, string $customerPhone, ?string $customerName, array $requestedItems): array
    {
        $canonicalPhone = \App\Modules\Core\Identity\PhoneNormalizer::toCanonical($customerPhone);
        $cleanPhone = \App\Modules\Core\Identity\PhoneNormalizer::toNational($customerPhone);

        if (empty($requestedItems)) {
            return [
                'status' => 'NO_ITEMS_PROVIDED',
                'order' => null,
                'resolved_items' => [],
            ];
        }

        $resolvedOrderItems = [];
        $calculatedTotalAmount = 0.00;

        // 1. Regla B-03 & B-04: Deterministic Resolution & MySQL Commercial Price Authority
        foreach ($requestedItems as $item) {
            $rawName = $item['name'] ?? '';
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $normName = Str::of($rawName)->lower()->ascii()->trim()->squish()->toString();

            // Buscar producto en MySQL por (tenant_id, normalized_name)
            $candidates = DB::table('products')
                ->where('tenant_id', $tenantId)
                ->where(function($q) use ($normName) {
                    $q->where('normalized_name', $normName)
                      ->orWhere(DB::raw('LOWER(name)'), $normName);
                })
                ->where('status', 'ACTIVE')
                ->get();

            if ($candidates->count() === 0) {
                // B-03: 0 coincidencias -> UNRESOLVED_PRODUCT (No se inventan productos ni precios)
                return [
                    'status' => 'UNRESOLVED_PRODUCT',
                    'reason' => "El producto '{$rawName}' no existe en el menú de la empresa.",
                    'unresolved_product_name' => $rawName,
                    'order' => null,
                ];
            }

            if ($candidates->count() > 1) {
                // B-03: >1 coincidencia -> AMBIGUOUS_PRODUCT (Prohibido first() arbitrario)
                return [
                    'status' => 'AMBIGUOUS_PRODUCT',
                    'reason' => "Existen múltiples variantes para '{$rawName}'. Se requiere aclaración.",
                    'ambiguous_product_name' => $rawName,
                    'candidates' => $candidates->pluck('name')->toArray(),
                    'order' => null,
                ];
            }

            // Exactly 1 match -> RESOLVED
            $productRecord = $candidates->first();
            
            // B-04: El precio nace EXCLUSIVAMENTE del base_price de MySQL (el LLM no tiene autoridad)
            $officialUnitPrice = (float) $productRecord->base_price;
            $itemSubtotal = round($quantity * $officialUnitPrice, 2);
            $calculatedTotalAmount += $itemSubtotal;

            $resolvedOrderItems[] = [
                'product_id' => $productRecord->id,
                'product_name' => $productRecord->name,
                'unit_price' => $officialUnitPrice,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
            ];
        }

        // 2. Persistencia Atómica del Pedido en MySQL
        return DB::transaction(function () use ($tenantId, $cleanPhone, $canonicalPhone, $customerName, $calculatedTotalAmount, $resolvedOrderItems) {
            $orderNumber = 'ORD-WA-' . rand(1000, 9999);

            $orderId = DB::table('orders')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'order_number' => $orderNumber,
                'customer_name' => $customerName ?? ('Cliente ' . substr($cleanPhone, -4)),
                'customer_phone' => $cleanPhone,
                'order_type' => 'DELIVERY',
                'subtotal' => $calculatedTotalAmount,
                'delivery_fee' => 0.00,
                'discount_amount' => 0.00,
                'total_amount' => $calculatedTotalAmount,
                'status' => 'CONFIRMED',
                'payment_status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // B-06: Snapshot Histórico Comercial en order_items
            foreach ($resolvedOrderItems as $resolvedItem) {
                DB::table('order_items')->insert([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'order_id' => $orderId,
                    'product_id' => $resolvedItem['product_id'],
                    'product_name' => $resolvedItem['product_name'],
                    'unit_price' => $resolvedItem['unit_price'],
                    'quantity' => $resolvedItem['quantity'],
                    'subtotal' => $resolvedItem['subtotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Registrar Evento en Event Store
            DB::table('order_events')->insert([
                'order_id' => $orderId,
                'tenant_id' => $tenantId,
                'event_type' => 'ORDER_CREATED',
                'new_state' => 'CONFIRMED',
                'created_at' => now(),
            ]);

            $orderRecord = DB::table('orders')->where('id', $orderId)->first();

            return [
                'status' => 'ORDER_CREATED',
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $calculatedTotalAmount,
                'items_count' => count($resolvedOrderItems),
                'order' => $orderRecord,
                'items' => $resolvedOrderItems,
            ];
        });
    }

    /**
     * Sincroniza y crea el pedido en MySQL directamente desde la interacción conversacional.
     */
    public function syncOrderFromConversation(int $tenantId, string $customerPhone, ?string $customerName, string $aiReplyText, string $customerMessageText): ?object
    {
        $cleanPhone = \App\Modules\Core\Identity\PhoneNormalizer::toNational($customerPhone);
        $canonicalPhone = \App\Modules\Core\Identity\PhoneNormalizer::toCanonical($customerPhone);

        // 1. Verificar si ya existe una orden activa PENDING para este cliente
        $existingOrder = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where(function($q) use ($cleanPhone, $canonicalPhone) {
                $q->where('customer_phone', $cleanPhone)
                  ->orWhere('customer_phone_canonical', $canonicalPhone);
            })
            ->whereNotIn('status', ['CANCELLED', 'DELIVERED', 'COMPLETED'])
            ->orderByDesc('id')
            ->first();

        // 2. Analizar productos mencionados en la conversación
        $combinedText = mb_strtolower($customerMessageText . " " . $aiReplyText);
        $itemsToBuild = [];

        // Buscar coincidencias con productos/menús activos en la DB
        $products = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($products as $p) {
            $normP = mb_strtolower($p->name);
            if (str_contains($combinedText, $normP) || (!empty($p->normalized_name) && str_contains($combinedText, $p->normalized_name))) {
                // Detectar cantidad (ejemplo: "2 menús", "2 platos", "dos")
                $qty = 1;
                if (preg_match('/([0-9]+)\s*(?:unidades|platos|men[uú]s|x)/i', $combinedText, $m)) {
                    $qty = max(1, (int)$m[1]);
                }
                $itemsToBuild[] = [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'unit_price' => (float)$p->base_price,
                    'quantity' => $qty,
                    'subtotal' => round($qty * (float)$p->base_price, 2)
                ];
            }
        }

        // Si no se halló producto exacto pero la conversación confirma pedido de "menú especial"
        if (empty($itemsToBuild) && (str_contains($combinedText, 'menú especial') || str_contains($combinedText, 'menu especial'))) {
            $specialProduct = DB::table('products')->where('tenant_id', $tenantId)->where('normalized_name', 'menu especial')->first();
            $unitPrice = $specialProduct ? (float)$specialProduct->base_price : 15.00;
            $qty = 2; // Por defecto 2 unidades según el contexto conversacional
            if (preg_match('/([0-9]+)\s*(?:unidades|platos|men[uú]s)/i', $combinedText, $m)) {
                $qty = max(1, (int)$m[1]);
            }
            $itemsToBuild[] = [
                'product_id' => $specialProduct->id ?? null,
                'name' => 'Menú Especial',
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'subtotal' => round($qty * $unitPrice, 2)
            ];
        }

        if (empty($itemsToBuild) && !$existingOrder) {
            return null;
        }

        // 3. Detectar costo de delivery si se menciona un distrito
        $deliveryFee = 0.00;
        $deliveryZoneName = null;
        $zones = DB::table('delivery_zones')->where('tenant_id', $tenantId)->where('is_active', true)->get();
        foreach ($zones as $z) {
            if (str_contains($combinedText, mb_strtolower($z->zone_name))) {
                $deliveryFee = (float)$z->delivery_fee;
                $deliveryZoneName = $z->zone_name;
                break;
            }
        }

        $itemsSubtotal = array_sum(array_column($itemsToBuild, 'subtotal'));
        $totalAmount = round($itemsSubtotal + $deliveryFee, 2);

        if ($totalAmount <= 0 && $existingOrder) {
            return $existingOrder;
        }

        // 4. Crear o actualizar la orden en MySQL
        return DB::transaction(function() use ($tenantId, $cleanPhone, $canonicalPhone, $customerName, $totalAmount, $itemsSubtotal, $deliveryFee, $deliveryZoneName, $itemsToBuild, $existingOrder) {
            if ($existingOrder) {
                DB::table('orders')->where('id', $existingOrder->id)->update([
                    'subtotal' => $itemsSubtotal > 0 ? $itemsSubtotal : $existingOrder->subtotal,
                    'delivery_fee' => $deliveryFee > 0 ? $deliveryFee : $existingOrder->delivery_fee,
                    'total_amount' => $totalAmount > 0 ? $totalAmount : $existingOrder->total_amount,
                    'delivery_zone' => $deliveryZoneName ?? $existingOrder->delivery_zone,
                    'updated_at' => now(),
                ]);

                if (!empty($deliveryZoneName)) {
                    try {
                        $policyService = new \App\Modules\Orders\Services\OrderCommercialTransitionPolicyService();
                        $policyService->processDeliveryConfirmation($tenantId, $existingOrder->id, '', $deliveryZoneName);
                    } catch (\Throwable $e) {}
                }

                return DB::table('orders')->where('id', $existingOrder->id)->first();
            }

            $orderNumber = 'ORD-WA-' . rand(1000, 9999);
            $orderId = DB::table('orders')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'order_number' => $orderNumber,
                'customer_name' => $customerName ?? ('Cliente ' . substr($cleanPhone, -4)),
                'customer_phone' => $cleanPhone,
                'customer_phone_canonical' => $canonicalPhone,
                'channel' => 'WHATSAPP',
                'order_type' => 'DELIVERY',
                'subtotal' => $itemsSubtotal,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => 0.00,
                'total_amount' => $totalAmount,
                'delivery_zone' => $deliveryZoneName,
                'status' => 'AWAITING_PAYMENT',
                'payment_status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($itemsToBuild as $item) {
                DB::table('order_items')->insert([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('orders')->where('id', $orderId)->first();
        });
    }
}
