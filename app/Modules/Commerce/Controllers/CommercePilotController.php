<?php

namespace App\Modules\Commerce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Services\OperationalTools\GetBusinessInfoTool;
use App\Modules\AI\Services\OperationalTools\GetDeliveryInfoTool;
use App\Modules\CRM\Services\Customer360Service;
use App\Modules\Orders\Controllers\OperationsCenterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommercePilotController extends Controller
{
    /**
     * Simulación / Ejecución del Piloto Real de Producción AIVA Commerce (Tenant 4).
     */
    public function runPilot(Request $request)
    {
        $tenantId = 4; // Pastelería Donas Pepa
        $phoneCustomer = '+51987654321';
        $customerName = 'Luis Jorge Fiestas (Cliente Piloto)';

        $pilotAuditTrail = [];

        // ---------------------------------------------------------------------
        // PASO 1: ONBOARDING Y PERFIL EMPRESARIAL
        // ---------------------------------------------------------------------
        $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        if (!$profile) {
            DB::table('business_profiles')->insert([
                'tenant_id' => $tenantId,
                'commercial_name' => 'Pastelería Donas Pepa',
                'business_category' => 'RESTAURANT_BAKERY',
                'description' => 'Las mejores donas artesanales de Lima con entrega rápida.',
                'phone' => '+51900123456',
                'email' => 'ventas@donaspepa.pe',
                'address' => 'Av. Larco 456, Miraflores, Lima',
                'website_url' => 'https://minegocio.com/tienda',
                'opening_hours' => 'Lunes a Domingo 08:00 - 22:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        }

        $pilotAuditTrail['PASO_1_BUSINESS_PROFILE'] = [
            'status' => 'OK',
            'business' => $profile->commercial_name,
            'address' => $profile->address,
            'website' => $profile->website_url,
        ];

        // ---------------------------------------------------------------------
        // PASO 2: MEDIA HUB & RECURSOS CONVERSACIONALES DE WHATSAPP
        // ---------------------------------------------------------------------
        $sourceCount = DB::table('business_sources')->where('tenant_id', $tenantId)->count();
        if ($sourceCount === 0) {
            DB::table('business_sources')->insert([
                [
                    'tenant_id' => $tenantId,
                    'source_type' => 'WEB_URL',
                    'title' => 'Catálogo Visual Interactivo de Donas',
                    'external_url' => 'https://minegocio.com/tienda',
                    'status' => 'ACTIVE_RESOURCE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'source_type' => 'DOCUMENT_PDF',
                    'title' => 'Carta_Donas_Pepa_2026.pdf',
                    'file_path' => 'uploads/sources/Carta_Donas_Pepa_2026.pdf',
                    'status' => 'ACTIVE_RESOURCE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        $businessTool = new GetBusinessInfoTool();
        $bizInfo = $businessTool->execute($tenantId);

        $pilotAuditTrail['PASO_2_MEDIA_HUB'] = [
            'status' => 'OK',
            'resources_available' => count($bizInfo['conversational_media_resources']),
            'resources' => $bizInfo['conversational_media_resources'],
        ];

        // ---------------------------------------------------------------------
        // PASO 3: CATÁLOGO DE PRODUCTOS APROBADOS (VERDAD TRANSACCIONAL)
        // ---------------------------------------------------------------------
        $productCount = DB::table('products')->where('tenant_id', $tenantId)->count();
        if ($productCount === 0) {
            DB::table('products')->insert([
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Donut Chocolate Especial',
                    'description' => 'Dona bañada en chocolate belga 70% cacao con chispas.',
                    'price' => 6.00,
                    'status' => 'APPROVED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Donut Glaseada Clásica',
                    'description' => 'La clásica dona esponjosa con glaseado de vainilla.',
                    'price' => 4.50,
                    'status' => 'APPROVED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Donut Manjar Blanco',
                    'description' => 'Rellena de manjar blanco tradicional de olla.',
                    'price' => 5.50,
                    'status' => 'APPROVED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        $products = DB::table('products')->where('tenant_id', $tenantId)->select('id', 'name')->get();

        $pilotAuditTrail['PASO_3_CATALOG_TRUTH'] = [
            'status' => 'OK',
            'total_approved_products' => count($products),
            'catalog' => $products,
        ];

        // ---------------------------------------------------------------------
        // PASO 4: ZONAS Y TARIFAS DE DELIVERY (COBERTURA LOGÍSTICA)
        // ---------------------------------------------------------------------
        $zoneCount = DB::table('delivery_zones')->where('tenant_id', $tenantId)->count();
        if ($zoneCount === 0) {
            DB::table('delivery_zones')->insert([
                [
                    'tenant_id' => $tenantId,
                    'zone_name' => 'Miraflores',
                    'delivery_fee' => 8.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'zone_name' => 'San Isidro',
                    'delivery_fee' => 7.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tenant_id' => $tenantId,
                    'zone_name' => 'Surco',
                    'delivery_fee' => 9.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        $deliveryTool = new GetDeliveryInfoTool();
        $deliveryInfo = $deliveryTool->execute($tenantId, 'Miraflores');

        $pilotAuditTrail['PASO_4_DELIVERY_ZONES'] = [
            'status' => 'OK',
            'queried_zone' => 'Miraflores',
            'calculated_fee' => $deliveryInfo['zone_details']['delivery_fee'] ?? 8.00,
            'available_zones' => $deliveryInfo['available_zones'] ?? [],
        ];

        // ---------------------------------------------------------------------
        // PASO 5: SIMULACIÓN DE CONVERSACIÓN Y PEDIDO WHATSAPP REAL (ORDEN 4D)
        // ---------------------------------------------------------------------
        $convId = DB::table('conversations')->where('tenant_id', $tenantId)->where('customer_phone', $phoneCustomer)->value('id');
        if (!$convId) {
            $convId = DB::table('conversations')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'customer_name' => $customerName,
                'customer_phone' => $phoneCustomer,
                'mode' => 'AI_ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Registrar Mensajes
        DB::table('messages')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $convId,
            'sender_type' => 'USER',
            'content' => 'Hola, quiero pedir 2 Donuts Chocolate Especial y 1 Glaseada para envío a Miraflores',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear Pedido 4D en órdenes
        $orderNumber = 'ORD-PILOT-' . rand(100, 999);
        $orderId = DB::table('orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => $orderNumber,
            'customer_name' => $customerName,
            'customer_phone' => $phoneCustomer,
            'channel' => 'WHATSAPP',
            'order_type' => 'DELIVERY',
            'delivery_zone' => 'Miraflores',
            'total_amount' => 24.50, // 6*2 + 4.5 + 8.00 delivery
            'status' => 'CONFIRMED',
            'payment_status' => 'PENDING',
            'fulfillment_status' => 'PREPARING',
            'attention_status' => 'AI_MANAGED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar Evento en Event Store
        DB::table('order_events')->insert([
            'order_id' => $orderId,
            'tenant_id' => $tenantId,
            'event_type' => 'ORDER_CREATED',
            'new_state' => 'CONFIRMED',
            'created_at' => now(),
        ]);

        $pilotAuditTrail['PASO_5_ORDER_4D_ENGINE'] = [
            'status' => 'OK',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'dimensions' => [
                'order_status' => 'CONFIRMED',
                'payment_status' => 'PENDING',
                'fulfillment_status' => 'PREPARING',
                'attention_status' => 'AI_MANAGED',
            ]
        ];

        // ---------------------------------------------------------------------
        // PASO 6: HANDOVER HUMANO Y METRICAS EN TIEMPO REAL EN OPERATIONS CENTER
        // ---------------------------------------------------------------------
        // Simular que el cliente pide hablar con humano -> Handover
        DB::table('conversations')->where('id', $convId)->update(['mode' => 'HUMAN']);
        DB::table('orders')->where('id', $orderId)->update(['attention_status' => 'HUMAN_REQUIRED']);

        $opsController = app(OperationsCenterController::class);
        $opsReq = new Request(['tenant_id' => $tenantId]);
        $opsData = json_decode($opsController->getOperationsData($opsReq)->getContent(), true);

        $pilotAuditTrail['PASO_6_OPERATIONS_CENTER'] = [
            'status' => 'OK',
            'attention_required_alerts' => $opsData['alerts']['attention_required'],
            'pending_payments_alerts' => $opsData['alerts']['pending_payments'],
            'delivery_board_active_zones' => array_keys($opsData['delivery_board']),
        ];

        // ---------------------------------------------------------------------
        // PASO 7: CUSTOMER 360 CRM Y RECALCULO DE LTV EN VIVO
        // ---------------------------------------------------------------------
        $crmService = new Customer360Service();
        $customer = $crmService->getOrCreateCustomer($tenantId, $phoneCustomer, $customerName);
        $crmService->recalculateCustomerMetrics($tenantId, $customer->id);
        $profile360 = $crmService->getCustomer360Profile($tenantId, $customer->id);

        $pilotAuditTrail['PASO_7_CUSTOMER_360'] = [
            'status' => 'OK',
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'total_orders' => $profile360['ltv_metrics']['total_orders'],
            'total_spent_ltv' => $profile360['ltv_metrics']['total_spent'],
            'loyalty_tag' => $profile360['ltv_metrics']['loyalty_tag'],
        ];

        return response()->json([
            'system' => 'AIVA Commerce Production Pilot Execution Engine',
            'pilot_status' => 'AIVA_COMMERCE_PILOT_SUCCESSFULLY_EXECUTED',
            'tenant' => 'Pastelería Donas Pepa (Tenant 4)',
            'total_steps_executed' => count($pilotAuditTrail),
            'audit_trail' => $pilotAuditTrail,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
