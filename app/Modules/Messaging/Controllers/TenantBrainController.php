<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Services\ConversationDomainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class TenantBrainController extends Controller
{
    protected ConversationDomainService $conversationDomainService;

    public function __construct(ConversationDomainService $conversationDomainService)
    {
        $this->conversationDomainService = $conversationDomainService;
    }

    /**
     * Obtener el Contexto y Prompt del Sistema dinámico para el Tenant del Gateway.
     */
    public function getBrainContext(Request $request)
    {
        try {
            $tenantId = (int)$request->input('tenant_id', $request->attributes->get('tenant_id', 0));
            if ($tenantId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'TENANT_UNRESOLVED',
                    'message' => 'Parámetro tenant_id requerido y no resuelto.',
                ], 422);
            }

        $tenantInfo = DB::table('tenants')->where('id', $tenantId)->first();
        $businessName = $tenantInfo->name ?? 'Restaurante / Negocio';
        $businessCategory = $tenantInfo->business_type ?? $tenantInfo->category ?? 'Gastronomía / Restaurante';

        if (Schema::hasTable('business_profiles')) {
            $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
            if ($profile) {
                if (!empty($profile->business_name)) {
                    $businessName = $profile->business_name;
                }
                if (!empty($profile->category)) {
                    $businessCategory = $profile->category;
                }
            }
        }

        $products = DB::table('products')->where('tenant_id', $tenantId)->get();

        $webCatalogSource = DB::table('business_sources')
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'WEB_URL')
            ->where('status', 'ACTIVE_RESOURCE')
            ->orderByDesc('id')
            ->first();

        $catalogLink = $webCatalogSource && !empty($webCatalogSource->external_url) ? trim($webCatalogSource->external_url) : null;

        // P3.14-07: Aislamiento estricto de recursos multimedia por tenant (cero fugas transversales)
        $mediaMenuSource = DB::table('business_sources')
            ->where('tenant_id', $tenantId)
            ->whereIn('source_type', ['IMAGE_MENU', 'DOCUMENT_PDF', 'IMAGE', 'PDF', 'EXCEL_CSV'])
            ->where('status', 'ACTIVE_RESOURCE')
            ->orderByDesc('id')
            ->first();

        $menuMediaUrl = null;
        if ($mediaMenuSource) {
            if (!empty($mediaMenuSource->external_url)) {
                $menuMediaUrl = trim($mediaMenuSource->external_url);
            } elseif (!empty($mediaMenuSource->file_path)) {
                $pathStr = ltrim($mediaMenuSource->file_path, '/');
                $menuMediaUrl = url($pathStr);
            }
        }

        // P3.14: Proyección canónica enriquecida del catálogo para LLM y Resolvers
        $catalogText = "";
        foreach ($products as $p) {
            $price = number_format((float)($p->base_price ?? $p->price ?? 0), 2);
            $compat = is_array($p->compatibility ?? null) ? $p->compatibility : json_decode($p->compatibility ?? '[]', true);
            $attrs = is_array($p->attributes ?? null) ? $p->attributes : json_decode($p->attributes ?? '[]', true);

            $metaParts = [];
            if (!empty($p->sku)) {
                $metaParts[] = "SKU: {$p->sku}";
            }
            if (!empty($compat['brand']) || !empty($compat['model'])) {
                $veh = trim(($compat['brand'] ?? '') . ' ' . ($compat['model'] ?? ''));
                $years = !empty($compat['year_raw']) ? " ({$compat['year_raw']})" : (!empty($compat['year_start']) ? " ({$compat['year_start']}-{$compat['year_end']})" : '');
                $metaParts[] = "Compatible: {$veh}{$years}";
            }
            if (!empty($attrs['side'])) {
                $posMap = ['FRONT' => 'Delantero', 'REAR' => 'Trasero', 'LEFT' => 'Izquierdo', 'RIGHT' => 'Derecho', 'BOTH' => 'Ambos / Par'];
                $metaParts[] = "Posición: " . ($posMap[$attrs['side']] ?? $attrs['side']);
            }
            if (!empty($attrs['presentation'])) {
                $metaParts[] = "Presentación: {$attrs['presentation']}";
            }
            if (!empty($attrs['color'])) {
                $metaParts[] = "Color: {$attrs['color']}";
            }
            if (!empty($attrs['material'])) {
                $metaParts[] = "Material: {$attrs['material']}";
            }

            $metaStr = !empty($metaParts) ? " [" . implode(' | ', $metaParts) . "]" : "";
            $catalogText .= "- {$p->name}{$metaStr}: S/ {$price}\n";
        }

        if (empty($catalogText)) {
            $catalogText = "- Sin productos registrados actualmente en el catálogo.";
        }

        $aiTone = 'FRIENDLY_EMPATHETIC';
        $customInstructions = "";
        $yapeInfo = 'No configurado';
        $plinInfo = 'No configurado';
        $bankInfo = 'No configurado';
        $storeAddress = '';

        if (Schema::hasTable('business_profiles')) {
            $profile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
            if ($profile) {
                if (isset($profile->custom_ai_instructions) && !empty($profile->custom_ai_instructions)) {
                    $customInstructions = trim($profile->custom_ai_instructions);
                }
                if (isset($profile->ai_tone) && !empty($profile->ai_tone)) {
                    $aiTone = strtoupper(trim($profile->ai_tone));
                }
                if (isset($profile->yape_info) && !empty($profile->yape_info)) {
                    $yapeInfo = trim($profile->yape_info);
                }
                if (isset($profile->plin_info) && !empty($profile->plin_info)) {
                    $plinInfo = trim($profile->plin_info);
                }
                if (isset($profile->bcp_info) && !empty($profile->bcp_info)) {
                    $bcpInfo = trim($profile->bcp_info);
                }
                if (isset($profile->bbva_info) && !empty($profile->bbva_info)) {
                    $bbvaInfo = trim($profile->bbva_info);
                }
                if (isset($profile->interbank_info) && !empty($profile->interbank_info)) {
                    $interbankInfo = trim($profile->interbank_info);
                }
                if (isset($profile->bank_account_info) && !empty($profile->bank_account_info)) {
                    $bankInfo = trim($profile->bank_account_info);
                }
                if (isset($profile->address) && !empty($profile->address)) {
                    $storeAddress = trim($profile->address);
                }
            }
        }

        $paymentDetailsLines = [];
        if (!empty($yapeInfo)) $paymentDetailsLines[] = "• Yape Oficial: " . $yapeInfo;
        if (!empty($plinInfo)) $paymentDetailsLines[] = "• Plin Oficial: " . $plinInfo;
        if (!empty($bcpInfo)) $paymentDetailsLines[] = "• Banco BCP: " . $bcpInfo;
        if (!empty($bbvaInfo)) $paymentDetailsLines[] = "• Banco BBVA: " . $bbvaInfo;
        if (!empty($interbankInfo)) $paymentDetailsLines[] = "• Banco Interbank: " . $interbankInfo;
        if (!empty($bankInfo)) $paymentDetailsLines[] = "• Cuenta Bancaria Adicional / CCI: " . $bankInfo;

        $paymentDetailsText = !empty($paymentDetailsLines)
            ? implode("\n", $paymentDetailsLines)
            : "• Yape Oficial: " . $yapeInfo . "\n• Plin Oficial: " . $plinInfo . "\n• Cuenta Bancaria CCI: " . $bankInfo;

        $toneDirectives = [
            'FRIENDLY_EMPATHETIC' => "Mantén una conversación sumamente amigable, cálida, empática, entusiasta y comercial con el cliente.",
            'FORMAL_CORPORATE' => "Mantén una conversación strictly formal, profesional, ejecutiva, respetuosa y corporativa sin informalismos.",
            'FAST_EXPRESS' => "Mantén una conversación ultra rápida, directa y concisa. Responde al grano en cada mensaje sin florituras.",
        ];
        $toneRule = $toneDirectives[$aiTone] ?? $toneDirectives['FRIENDLY_EMPATHETIC'];

        $customSection = "";
        if (!empty($customInstructions)) {
            $customSection = "\n\nREGLAS Y RESTRICCIONES ESPECÍFICAS DEL NEGOCIO (CUMPLIMIENTO ESTRICTO Y OBLIGATORIO):\n{$customInstructions}";
        }

        // Cargar Horarios de Atención Oficiales del Negocio desde MySQL
        $dayNames = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];

        $hoursRaw = DB::table('business_hours')
            ->where('tenant_id', $tenantId)
            ->orderBy('day_of_week')
            ->get();
        $hoursSummary = [];

        if ($hoursRaw->isNotEmpty()) {
            foreach ($hoursRaw as $bh) {
                $dayName = $dayNames[$bh->day_of_week] ?? "Día {$bh->day_of_week}";
                $isOpen = true;
                if (isset($bh->is_open)) {
                    $isOpen = (bool)$bh->is_open;
                } elseif (isset($bh->is_closed)) {
                    $isOpen = !(bool)$bh->is_closed;
                }

                if ($isOpen) {
                    $openFormatted = date("g:i a", strtotime($bh->open_time ?? '08:00'));
                    $closeFormatted = date("g:i a", strtotime($bh->close_time ?? '20:00'));
                    $hoursSummary[] = "• {$dayName}: {$openFormatted} a {$closeFormatted}";
                } else {
                    $hoursSummary[] = "• {$dayName}: CERRADO";
                }
            }
        }

        $hoursText = !empty($hoursSummary)
            ? implode("\n", $hoursSummary)
            : "• Lunes a Sábado: 8:00 am a 8:00 pm\n• Domingo: CERRADO";

        // Evaluación dinámica del tiempo real usando la Zona Horaria configurada para el Tenant (ej: America/Lima, America/Mexico_City, Europe/Madrid, etc.)
        $tenantTimezone = DB::table('business_settings')->where('tenant_id', $tenantId)->value('timezone')
            ?? (DB::table('tenants')->where('id', $tenantId)->value('timezone') ?? 'America/Lima');

        try {
            $nowTenant = \Carbon\Carbon::now($tenantTimezone);
        } catch (\Throwable $e) {
            $nowTenant = \Carbon\Carbon::now('America/Lima');
            $tenantTimezone = 'America/Lima';
        }

        $currentDayOfWeek = $nowTenant->dayOfWeek; // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
        $currentTimeStr = $nowTenant->format('H:i:s');
        $currentDateFormatted = $nowTenant->translatedFormat('l, d \d\e F \d\e Y, g:i a');

        $isCurrentlyOpen = false;
        $dayOfWeekIso = $currentDayOfWeek === 0 ? 7 : $currentDayOfWeek;
        $todayHours = $hoursRaw->isNotEmpty() ? $hoursRaw->firstWhere('day_of_week', $dayOfWeekIso) : null;

        if ($todayHours) {
            $dayOpen = true;
            if (isset($todayHours->is_open)) {
                $dayOpen = (bool)$todayHours->is_open;
            } elseif (isset($todayHours->is_closed)) {
                $dayOpen = !(bool)$todayHours->is_closed;
            }

            if ($dayOpen) {
                $openTime = $todayHours->open_time ?? '08:00:00';
                $closeTime = $todayHours->close_time ?? '20:00:00';
                if ($closeTime < $openTime) {
                    $isCurrentlyOpen = ($currentTimeStr >= $openTime || $currentTimeStr <= $closeTime);
                } else {
                    $isCurrentlyOpen = ($currentTimeStr >= $openTime && $currentTimeStr <= $closeTime);
                }
            }
        } elseif ($hoursRaw->isEmpty()) {
            // Horario por defecto: Lunes a Sábado 8am a 8pm
            if ($currentDayOfWeek >= 1 && $currentDayOfWeek <= 6) {
                $isCurrentlyOpen = ($currentTimeStr >= '08:00:00' && $currentTimeStr <= '20:00:00');
            }
        }

        $businessStatusText = $isCurrentlyOpen
            ? "ABIERTO (En este momento estamos en horario de atención)"
            : "CERRADO (En este momento estamos fuera del horario de atención)";

        // Cargar Zonas y Tarifas Oficiales de Delivery desde MySQL (delivery_zones)
        $deliveryZonesRaw = DB::table('delivery_zones')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $zonesSummary = [];
        if ($deliveryZonesRaw->isNotEmpty()) {
            foreach ($deliveryZonesRaw as $dz) {
                $feeStr = number_format((float)$dz->delivery_fee, 2);
                $timeStr = $dz->estimated_time_mins ? " (Tiempo estimado: {$dz->estimated_time_mins} mins)" : "";
                $minStr = (float)$dz->min_order_amount > 0 ? " (Pedido mínimo: S/ " . number_format((float)$dz->min_order_amount, 2) . ")" : "";
                $zonesSummary[] = "• {$dz->zone_name}: S/ {$feeStr}{$timeStr}{$minStr}";
            }
        }

        $deliveryContextText = !empty($zonesSummary)
            ? implode("\n", $zonesSummary)
            : "• No hay zonas de delivery específicas configuradas actualmente.";

        $promptLines = [
            "Eres AIVA, la Asistente Virtual Oficial de '" . $businessName . "' (Categoría: " . $businessCategory . ").",
            "",
            "FECHA Y HORA ACTUAL EN TIEMPO REAL (ZONA HORARIA DEL NEGOCIO: {$tenantTimezone}): " . $currentDateFormatted . ".",
            "ESTADO DE ATENCIÓN EN ESTE MOMENTO EXACTO: " . $businessStatusText . ".",
            "REGLA ESTRICTA DE ESTADO DE ATENCIÓN: Si el cliente pregunta '¿Aún están atendiendo?', '¿Están abiertos?', '¿Atienden ahora?' o cualquier consulta sobre el estado actual, DEBES verificar el ESTADO DE ATENCIÓN EN ESTE MOMENTO EXACTO indicado arriba ('" . $businessStatusText . "'). Si el estado es CERRADO, DEBES responder amablemente indicando que en este momento el negocio se encuentra CERRADO y señalar el horario en el que se reanuda la atención. NUNCA respondas que están atendiendo si el estado indica CERRADO.",
            "",
            "DIRECCIÓN FÍSICA Y SEDE PRINCIPAL CONFIGURADA PARA '" . $businessName . "':",
            "• Dirección Oficial de la Tienda / Sede Principal: " . $storeAddress,
            "REGLA DE DIRECCIÓN Y RECOGIDO EN TIENDA: Cuando el cliente consulte sobre la dirección, ubicación física de la tienda o punto de recojo, DEBES responder ÚNICAMENTE indicando la dirección oficial configurada arriba: '" . $storeAddress . "'. Queda ESTRICTAMENTE PROHIBIDO inventar o asumir distritos ficticios (como Miraflores, San Isidro, Surco, etc.).",
            "",
            "DATOS OFICIALES DE PAGO CONFIGURADOS PARA TRANSACCIONES DE '" . $businessName . "':",
            $paymentDetailsText,
            "",
            "HORARIOS OFICIALES DE ATENCIÓN DE '" . $businessName . "':",
            $hoursText,
            "REGLA DE HORARIOS: Cuando el cliente pregunte por los horarios de atención, responde ÚNICAMENTE en formato de 12 horas (ejemplo: '5:30 pm a 11:00 pm'). NUNCA muestres dos formatos ni agregues la hora en formato 24h entre paréntesis (como 17:30 - 23:00).",
            "",
            "ZONAS Y TARIFAS OFICIALES DE DELIVERY CONFIGURADAS PARA '" . $businessName . "':",
            $deliveryContextText,
            "REGLA DE TARIFAS DE DELIVERY Y COBERTURA: Cuando el cliente consulte sobre la cobertura, costo o tarifa de delivery a un distrito o zona específica, DEBES responder ÚNICAMENTE utilizando los precios oficiales configurados arriba para esa zona. NUNCA inventes montos o respondas con tarifas genéricas.",
            "",
            "REGLAS CONVERSACIONALES OBLIGATORIAS:",
            "1. " . $toneRule,
            "2. NUNCA muestres la lista completa de productos ni el catálogo a menos que el cliente lo pida explícitamente (ej: '¿Qué productos tienen?', 'Muéstrame el menú', 'Precios').",
            "3. Si el cliente hace una pregunta específica (ej: horario, dirección, costo de envío a un distrito, o un comentario como 'Ok' o 'No'), responde de forma directa, breve y enfocada ÚNICAMENTE a su mensaje.",
            "4. NUNCA vuelvas a repetir el saludo inicial ('¡Hola! Soy AIVA...') si ya estás en medio de una conversación fluida.",
            "5. Tu empresa se dedica a '" . $businessCategory . "'. Si piden cosas ajenas a tu rubro, indícalo amablemente.",
            "6. Si el cliente solicita explícitamente hablar con un asesor humano o persona (ej: 'quiero hablar con un asesor', 'pásame con un operador'), responde EXACTAMENTE: 'Entendido. En breve un asesor humano te atenderá por este mismo chat. Por favor aguarda unos momentos.' NUNCA proporciones números telefónicos ni correos electrónicos externos.",
            "7. NUNCA menciones ni expongas la cantidad o cifra numérica del stock interno (ej: 'Stock: 3 unidades', 'quedan 2') al enviar productos o hablar con el cliente. Limítate a mostrar el nombre y precio del producto.",
            (!empty($catalogLink)
                ? "8. Enlace del Catálogo Virtual Oficial: '" . $catalogLink . "'. Cuando el cliente solicite el catálogo virtual o link de la tienda, DEBES INCLUIR OBLIGATORIAMENTE el enlace oficial: " . $catalogLink . " en tu respuesta."
                : (!empty($menuMediaUrl)
                    ? "8. La Carta/Menú Oficial está subida en imagen/PDF. Cuando el cliente solicite la carta, menú o fotos de los productos, responde amablemente indicando que le envías la carta oficial a continuación (ejemplo: '¡Claro! Aquí tienes nuestra carta oficial:'). NUNCA inventes páginas web ni enlaces de tienda virtual que no han sido subidas."
                    : "8. NUNCA transcribas ni listes los productos y precios de la carta en texto plano si el cliente te pide la carta o menú. Las cartas se entregan exclusivamente como archivos o imágenes adjuntas. Si el cliente pide la carta, responde amablemente diciendo que se la envías de inmediato sin listar platos en el texto.")),
            "9. RECEPCIÓN DE COMPROBANTES DE PAGO POR CHAT: SÍ estás 100% capacitada para recibir capturas y fotos de comprobantes Yape/Plin. Cuando el cliente pregunte si puede enviarte el comprobante o foto, responde con amabilidad: '¡Sí, claro! Puedes enviar la captura o foto de tu comprobante de Yape o Plin directamente por este chat y yo me encargaré de verificarlo de inmediato.'",
            "10. DATOS Y REGLA DE PAGO ESTRICTA: Al indicar al cliente los datos para transferir o pagar su pedido, DEBES USAR OBLIGATORIAMENTE ESTOS DATOS OFICIALES DEL NEGOCIO: Yape/Plin: " . $yapeInfo . " (o CCI: " . $bankInfo . "). NUNCA inventes o menciones números telefónicos falsos.",
            "11. PROHIBICIÓN STRICTA DE VERIFICACIÓN FALSA DE PAGO: NUNCA des por verificado, aceptado o aprobado un pago si el cliente NO ha adjuntado una FOTO O CAPTURA DE PANTALLA real en ese mensaje. Si el cliente escribe únicamente texto diciendo 'Quiero que valides mi pago', 'Ya yapeé', 'Revisa mi pago' o 'Valídalo' SIN adjuntar una foto o imagen, responde OBLIGATORIAMENTE: 'Por favor, envía la captura o foto de tu comprobante de Yape o Plin por este chat para poder verificarlo de inmediato e iniciar la preparación.'",
            "12. PROHIBICIÓN STRICTA DE CIERRE DE VENTA PREMATURO O REGISTRO FALSO: NUNCA des por cerrado un pedido ni solicites la transferencia/comprobante de pago a menos que el cliente haya especificado explícitamente qué producto y cuántas unidades desea comprar (ejemplo: 'Quiero 1 pollo a la brasa' o '1 producto del menú'). Si el cliente solo envía un saludo, pregunta general o comentario suelto (ejemplo: 'Buen día', 'Por la tarde por favor', 'Hola'), pregúntale amablemente en qué producto o servicio específico está interesado antes de hablar de entregas o pagos.",
            "13. REGLA DE NATURALIDAD HUMANA Y USO DEL NOMBRE: Habla como una persona real en un chat de WhatsApp (cercano, empático, conciso y fluido). NUNCA repitas el nombre de la empresa ni del cliente en cada mensaje (ejemplo: NUNCA digas '¡Claro, " . $businessName . "!' ni repitas el nombre de forma mecánica en cada frase). Usa el nombre del cliente ÚNICAMENTE al saludar o de forma muy esporádica si la oración lo requiere naturalmente, evitando cualquier patrón mecánico o robótico.",
            "14. REGLA DE DIRECCIÓN Y ZONA DE REPARTO: Si el cliente NO ha indicado explícitamente su distrito, zona o dirección de envío en la conversación (ej: 'envíenlo a Miraflores' o 'vivo en Surco'), NUNCA inventes, asumas o selecciones una zona por defecto (como Ñaña, Chaclacayo, etc.). En ese caso, responde cotizando únicamente el valor de los productos y pregunta amablemente: '¿A qué distrito o dirección te gustaría que enviemos tu pedido?' para calcular el delivery exacto."
        ];

        if (!empty($customSection)) {
            $promptLines[] = $customSection;
        }

        // Inyectar Catálogo Canónico Semántico Versionado desde MySQL (RF-06, RF-08, RF-10, RF-31)
        try {
            $canonicalProjector = new \App\Modules\AI\Services\AIContextCatalogProjectorService();
            $canonicalContextText = $canonicalProjector->projectAIContext($tenantId);
            if ($canonicalContextText !== 'NO_ACTIVE_CATALOG_AVAILABLE') {
                $promptLines[] = "";
                $promptLines[] = $canonicalContextText;
                $promptLines[] = "REGLAS DE RIGOR COMERCIAL SOBRE EL CATÁLOGO PUBLICADO:";
                $promptLines[] = "1. DEBES utilizar ÚNICAMENTE los menús, platos e ítems autorizados en el 'CATÁLOGO DE MENÚS AUTORIZADO' mostrado arriba. Queda ESTRICTAMENTE PROHIBIDO inventar o sugerir categorías o nombres como 'menú ejecutivo', 'menú familiar', etc., si no están presentes en el catálogo.";
                $promptLines[] = "2. PROHIBICIÓN STRICTA DE SOLICITAR PRECIOS O CAPTURAS AL CLIENTE: NUNCA le pidas al cliente que envíe fotos o capturas del precio de los platos ni le preguntes cuánto cuesta un menú. Todos los precios de venta provienen 100% de la base de datos de MySQL.";
            }
        } catch (\Throwable $e) {}

        $promptLines[] = "";
        $promptLines[] = "Información de Referencia de Productos (Usar SOLO si preguntan por catálogo o precios):";
        $promptLines[] = $catalogText;

        $systemPrompt = implode("\n", $promptLines);

        return response()->json([
            'tenant_id' => $tenantId,
            'business_name' => $businessName,
            'business_category' => $businessCategory,
            'system_prompt' => $systemPrompt,
            'products' => $products,
            'catalog_link' => $catalogLink,
            'menu_media_url' => $menuMediaUrl,
        ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Procesar mensaje entrante desde el Gateway de WhatsApp (Node.js QR / Webhook).
     */
    public function processInboundMessage(Request $request)
    {
        $tenantContext = $request->attributes->get('tenant_context');
        $tenantId = (int)$request->attributes->get('tenant_id', 0);
        $correlationId = $request->attributes->get('correlation_id', '');

        if ($tenantId <= 0) {
            return response()->json([
                'status' => 'error',
                'code' => 'TENANT_UNRESOLVED',
                'message' => 'No fue posible resolver el Tenant de forma determinística (TCI-00).',
                'correlation_id' => $correlationId,
            ], 422);
        }

        if (!$tenantContext instanceof \App\Core\DTOs\TenantContextDTO) {
            $bizId = (int)(DB::table('business_profiles')->where('tenant_id', $tenantId)->value('id') ?? 0);
            $tenantContext = new \App\Core\DTOs\TenantContextDTO(
                tenantId: $tenantId,
                businessId: $bizId,
                catalogVersionId: null,
                correlationId: $correlationId
            );
        }

        $customerPhone = $request->input('phone');
        $customerName = $request->input('name');
        $messageText = $request->input('message', '');

        if (empty($customerPhone)) {
            return response()->json(['error' => 'Faltan parámetros requeridos (phone).'], 422);
        }

        $startTime = microtime(true);

        // Procesar imagen adjunta si existe
        $hasMedia = (bool) $request->input('has_media', false);
        $mediaBase64 = $request->input('media_base64');
        $mediaUrl = null;
        $voucherResult = null;

        if ($hasMedia && !empty($mediaBase64)) {
            try {
                $uploadDir = public_path('uploads/vouchers');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $filename = 'voucher_' . time() . '_' . rand(1000, 9999) . '.png';
                $filePath = $uploadDir . '/' . $filename;
                $rawBytes = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $mediaBase64));
                file_put_contents($filePath, $rawBytes);
                $mediaUrl = url('uploads/vouchers/' . $filename);

                // Obtener el pedido PENDING más reciente de este cliente para validar monto y destinatario
                $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
                $pendingOrder = DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->where('customer_phone', $cleanPhone)
                    ->orderByDesc('id')
                    ->first();

                $expectedAmount = (float)($pendingOrder->total_amount ?? 0.00);

                // Obtener teléfono y titular oficial de Yape del negocio desde business_profiles
                $bizProfile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
                $expectedYapePhone = $bizProfile->yape_info ?? '';
                $expectedYapeName = $bizProfile->commercial_name ?? 'Comercio Registrado';

                $voucherService = new \App\Modules\Messaging\Services\PaymentVoucherValidationService();
                $voucherResult = $voucherService->validateVoucher(
                    base64Image: $mediaBase64, 
                    captionText: $messageText,
                    expectedAmount: $expectedAmount,
                    expectedRecipientPhone: $expectedYapePhone,
                    expectedRecipientName: $expectedYapeName,
                    tenantId: $tenantId
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Error procesando imagen de comprobante: " . $e->getMessage());
            }
        }

        // 1. Extraer o refinar el nombre del cliente si se presenta en el texto ("Me llamo X", "Soy X", etc.)
        if (!empty($messageText) && preg_match('/(?:me llamo|mi nombre es|soy|hablas con|a nombre de)\s+([a-záéíóúñA-ZÁÉÍÓÚÑ]{2,20})/iu', $messageText, $matches)) {
            $extractedName = ucfirst(mb_strtolower(trim($matches[1])));
            $ignoreWords = ['un', 'una', 'el', 'la', 'hola', 'pedido', 'donas', 'cliente', 'gracias', 'por'];
            if (!in_array(mb_strtolower($extractedName), $ignoreWords)) {
                $customerName = $extractedName;
            }
        }

        // 1. Encontrar o crear la conversación aislada
        $conversation = $this->conversationDomainService->findOrCreateConversation($tenantId, $customerPhone, $customerName);
        $currentMode = $conversation->mode ?? $conversation->status ?? 'AI';

        // Centinela Constitucional de Integridad Multi-Tenant (TCI-07)
        $bizProfile = DB::table('business_profiles')->where('tenant_id', $tenantId)->first();
        \App\Modules\AI\Services\TenantContextIntegrityGuard::assertIntegrity($tenantContext, [
            'inbound_message' => $tenantId,
            'conversation'    => $conversation,
            'business'        => $bizProfile,
        ]);

        // Sincronizar o actualizar el nombre real del cliente en MySQL (conversations & customers)
        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (!empty($customerName)) {
            DB::table('conversations')->where('id', $conversation->id)->update(['customer_name' => $customerName]);

            $existingCust = DB::table('customers')->where('tenant_id', $tenantId)->where('phone', $cleanPhone)->first();
            if ($existingCust) {
                if ($existingCust->name === 'Cliente WhatsApp' || empty($existingCust->name) || ($customerName !== 'Cliente WhatsApp' && $existingCust->name !== $customerName)) {
                    DB::table('customers')->where('id', $existingCust->id)->update([
                        'name' => $customerName,
                        'updated_at' => now(),
                    ]);
                }
            } else {
                DB::table('customers')->insert([
                    'tenant_id' => $tenantId,
                    'name' => $customerName ?: 'Cliente WhatsApp',
                    'phone' => $cleanPhone,
                    'preferred_channel' => 'WHATSAPP',
                    'total_orders_count' => 0,
                    'total_spent_amount' => 0.00,
                    'tag' => 'NUEVO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Detectar si el cliente solicita un asesor humano explícitamente (Intención Directa)
        $msgLower = mb_strtolower($messageText);
        $wantsHuman = false;
        
        $explicitHumanTriggers = [
            'hablar con un asesor', 'hablar con asesor', 'pasame con un asesor', 'pásame con un asesor',
            'hablar con persona', 'pasame con una persona', 'pásame con una persona', 'quiero un operador',
            'atencion humana', 'atención humana', 'quiero hablar con un humano', 'conecta con asesor',
            'conectar con asesor', 'asesor humano por favor', 'quiero hablar con alguien'
        ];

        foreach ($explicitHumanTriggers as $trigger) {
            if (str_contains($msgLower, $trigger)) {
                $wantsHuman = true;
                break;
            }
        }

        // Detectar Intención Directa de Anulación/Cancelación de Pedido (CI-08, Race protection)
        $cancellationTriggers = [
            'anular', 'anulado', 'anulalo', 'anúlalo', 'anula', 'anulen', 'anulenlo',
            'cancelar', 'cancelado', 'cancelalo', 'cancélalo', 'cancela', 'cancelen', 'cancelenlo',
            'ya no deseo', 'deseo anular', 'deseo cancelar', 'cancelar mi pedido',
            'anular mi pedido', 'cancelen mi pedido', 'anulen mi pedido', 'borra mi pedido',
            'elimina mi pedido', 'ya no quiero', 'ya no voy a querer'
        ];

        $wantsCancel = false;
        foreach ($cancellationTriggers as $ctrigger) {
            if (str_contains($msgLower, $ctrigger)) {
                $wantsCancel = true;
                break;
            }
        }

        if (!$wantsCancel && (preg_match('/\b(cancela|cancelalo|cancélalo|anula|anulalo|anúlalo|cancelar|anular)\b/ui', $msgLower))) {
            $wantsCancel = true;
        }

        if ($wantsCancel) {
            $cancellationService = new \App\Modules\Orders\Services\OrderCancellationService();
            $cancellationResult = $cancellationService->cancelOrder(
                tenantId: $tenantId,
                customerPhone: $cleanPhone,
                cancelledBy: 'CUSTOMER',
                channel: 'WHATSAPP_AI',
                reason: 'Solicitud por chat de WhatsApp'
            );

            $replyText = \App\Modules\Orders\Mappers\CancellationResponseMapper::mapToWhatsAppMessage($cancellationResult);

            $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'CUSTOMER', $messageText ?: '[Solicitud de anulación]');
            $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'AI', $replyText, ['cancellation_decision' => $cancellationResult['decision']]);

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'reply_text' => $replyText,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-order-cancellation-engine',
            ]);
        }

        if ($wantsHuman) {
            DB::table('conversations')->where('id', $conversation->id)->update(['mode' => 'HUMAN', 'updated_at' => now()]);
            $currentMode = 'HUMAN';
        }

        // 2. Registrar el mensaje entrante del Cliente en MySQL con metadata de imagen
        $msgMetadata = [];
        if ($mediaUrl) {
            $msgMetadata['media_url'] = $mediaUrl;
        }
        if ($voucherResult) {
            $msgMetadata['voucher'] = $voucherResult;
        }

        $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'CUSTOMER', $messageText ?: '[Imagen / Comprobante]', $msgMetadata);

        if ($wantsHuman) {
            $humanWaitMessage = "Entendido. En breve un asesor humano te atenderá por este mismo chat. Por favor aguarda unos momentos.";
            
            $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'AI', $humanWaitMessage);
            DB::table('conversations')->where('id', $conversation->id)->update(['mode' => 'HUMAN', 'updated_at' => now()]);

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'reply_text' => $humanWaitMessage,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-handover-trigger',
            ]);
        }

        // 2. RESOLVER CONTEXTO CONVERSACIONAL CANÓNICO DESDE MYSQL (P2.11.y — CI-01, CI-02, CI-03)
        $contextResolver = new \App\Modules\Orders\Services\OrderContextResolver();
        $canonicalContext = $contextResolver->resolveContext($tenantId, $customerPhone);

        // EVALUACIÓN MULTIMODAL UNIVERSAL VÍA P3.12-R & MULTIMODAL RUNTIME BRIDGE (Master Order v2.3)
        if ($hasMedia && !empty($mediaBase64)) {
            $multimodalAdapter = new \App\Modules\Multimodal\Runtime\Services\MultimodalRuntimeAdapterService();
            $incomingMsgId = $request->input('message_id') ?? $request->input('id') ?? ('msg_' . (string)\Illuminate\Support\Str::uuid());
            
            $runtimeResponse = $multimodalAdapter->processInboundMedia(
                tenantId: $tenantId,
                conversationId: (string)$conversation->id,
                messageId: (string)$incomingMsgId,
                mediaData: $mediaBase64,
                caption: $messageText,
                correlationId: $correlationId,
                context: [
                    'order' => $canonicalContext['order'] ?? null,
                    'order_id' => $canonicalContext['order']['id'] ?? null,
                    'expected_amount' => $canonicalContext['order']['total'] ?? null,
                    'customer_phone' => $customerPhone,
                ]
            );

            /** @var \App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO $evidence */
            $evidence = $runtimeResponse['evidence'];
            $reconciliation = $runtimeResponse['reconciliation'];
            $replyText = $reconciliation['message'] ?? 'Hemos recibido tu imagen.';

            // P3.13: La coordinación comercial delega el resultado de P2.11 a Orders Application
            $activeOrderId = $canonicalContext['order']['id'] ?? null;
            if (!empty($activeOrderId) && !empty($reconciliation['decision']) && str_contains($reconciliation['decision'], 'PAYMENT')) {
                try {
                    $policyService = new \App\Modules\Orders\Services\OrderCommercialTransitionPolicyService();
                    $policyService->processPaymentResolution(
                        tenantId: $tenantId,
                        orderId: (int)$activeOrderId,
                        financialResult: $reconciliation
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("P3.13 OrderCommercialTransitionPolicy error: " . $e->getMessage());
                }
            }

            $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'AI', $replyText, [
                'multimodal_evidence' => $evidence->toArray(),
                'reconciliation' => $reconciliation,
            ]);
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'reply_text' => $replyText,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-multimodal-runtime-bridge',
                'evidence' => $evidence->toArray(),
                'reconciliation' => $reconciliation,
            ]);
        }

        // Si la conversación ya estaba en modo HUMAN previamente, la IA no responde
        if ($currentMode === 'HUMAN') {
            return response()->json([
                'status' => 'HANDOVER_ACTIVE',
                'reply_text' => null,
                'message' => 'Un operador humano tiene el control de la conversación.'
            ]);
        }

        // Formatear JID exacto para el Gateway de WhatsApp
        $formattedJid = str_contains($customerPhone, '@') 
            ? $customerPhone 
            : (strlen($cleanPhone) >= 14 ? $cleanPhone . '@lid' : $cleanPhone . '@s.whatsapp.net');

        // 3. ORQUESTACIÓN CONVERSACIONAL Y ARBITRAJE DE RESPUESTAS (V3 DEFINITIVA)
        $intentResolver = new \App\Modules\Messaging\Services\IntentResolverService();
        $candidateSet = $intentResolver->resolveCandidateActions($tenantId, (string)$conversation->id, $messageText);

        $arbitrator = new \App\Modules\Messaging\Services\ResponseArbitrationService();
        $plan = $arbitrator->arbitrate($tenantId, (string)$conversation->id, $candidateSet, $messageText);

        $resolvedIntent = $candidateSet['intent_details']['intent'] ?? 'GENERAL_QUERY';
        $detectedCategory = $candidateSet['intent_details']['category'] ?? null;

        // Comprobar historial previo de la conversación para AG-38 (Bienvenida Única)
        $historyMessages = $this->conversationDomainService->getMessagesForConversation($tenantId, $conversation->id);
        $hasPreviousAssistantMessages = $historyMessages->where('sender_type', '!=', 'CUSTOMER')->count() > 0;
        $isWelcomeForbidden = $hasPreviousAssistantMessages || ($conversation->welcome_sent_at !== null);

        if (!$isWelcomeForbidden) {
            DB::table('conversations')->where('id', $conversation->id)->update([
                'welcome_sent_at' => now(),
                'updated_at' => now(),
            ]);
            $conversation->welcome_sent_at = now();
        }

        // 3.1: CORRECCIÓN CONVERSACIONAL (P0 - MÁXIMA PRIORIDAD)
        if ($plan['primary_action'] === 'CONVERSATIONAL_CORRECTION') {
            $repairReply = $plan['repair_reply'];
            $this->conversationDomainService->recordMessage($tenantId, $conversation->id, 'AI', $repairReply, [
                'intent' => 'CONVERSATIONAL_CORRECTION',
                'rejected_concepts' => $plan['rejected_concepts'] ?? [],
            ]);
            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $repairReply,
                    type: 'TEXT'
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
            } catch (\Throwable $e) {}

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $repairReply,
                'intent' => 'CONVERSATIONAL_CORRECTION',
                'latency_ms' => $latencyMs,
                'model' => 'aiva-conversational-repair',
            ]);
        }

        // 3.2: DESPACHO DE MEDIO / CARTA VISUAL (P1 / P2 / P3 - MEDIA ACTION LOCK + STOP)
        if ($plan['primary_action'] === 'MEDIA_DISPATCH') {
            $targetQuery = $plan['target'] ?? $messageText;
            $mediaResolver = new \App\Modules\Business\Services\BusinessMediaResolverService();
            $canonicalMedia = $mediaResolver->resolveMenuMedia($tenantId, $targetQuery, (string)$conversation->id);

            $filePath = $canonicalMedia['file_path'] ?? null;
            if ($filePath) {
                if (file_exists(public_path($filePath))) {
                    $filePath = public_path($filePath);
                } elseif (file_exists(storage_path('app/' . $filePath))) {
                    $filePath = storage_path('app/' . $filePath);
                } elseif (file_exists($filePath)) {
                    $filePath = realpath($filePath) ?: $filePath;
                }
            }

            $sizeBytes = ($filePath && file_exists($filePath) && !is_dir($filePath)) ? @filesize($filePath) : 0;
            $physicalValid = ($canonicalMedia !== null && $filePath && file_exists($filePath) && !is_dir($filePath) && $sizeBytes > 0);

            // PROHIBICIÓN CONSTITUCIONAL DE DOWNGRADE SILENCIOSO MEDIA -> TEXT
            if (!$physicalValid) {
                if (!empty($plan['pending_action_id'])) {
                    $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
                    $stateManager->markPendingActionFailed($tenantId, (string)$conversation->id, 'INVALID_MEDIA_ASSET', false);
                }

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                return response()->json([
                    'status' => 'FAILED',
                    'execution_status' => 'EXECUTION_FAILED',
                    'reason' => 'INVALID_MEDIA_ASSET',
                    'error' => 'Recurso visual no disponible o archivo físico con 0 bytes',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => null,
                    'intent' => 'REQUEST_MEDIA',
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-media-integrity-guard',
                    'terminal' => true,
                ], 200);
            }

            // FUENTE ÚNICA DE CAPTION (CERO DUPLICACIÓN, RESPETA CAPTIONPOLICY)
            $caption = $canonicalMedia['caption'];

            // Si hay acciones secundarias (ej. precio en turno compuesto)
            if (!empty($plan['secondary_actions'])) {
                foreach ($plan['secondary_actions'] as $sec) {
                    if ($sec['type'] === 'PRICE_INQUIRY') {
                        $pData = $sec['data']['raw_text'] ?? '';
                        $pDataNorm = \Illuminate\Support\Str::of($pData)->ascii()->lower()->replace(['¿', '?', '¡', '!', ',', '.', ':'], ' ')->squish()->toString();
                        if (preg_match('/(?:cuanto|precio|costo|sale|a como)\s+(?:cuesta|esta|sale)?\s*(?:el|la|los|las)?\s*(.*)/iu', $pDataNorm, $mPrice)) {
                            $pData = trim($mPrice[1]);
                        }
                        $catResolver = new \App\Modules\Orders\Services\CatalogReferenceResolverService();
                        $pRes = $catResolver->resolveCustomerReference($tenantId, $pData);
                        if ($pRes->status === 'RESOLVED') {
                            $caption .= "\n\nNuestra {$pRes->entityName} tiene un precio oficial de S/ " . number_format((float)$pRes->price, 2) . " 😊";
                        }
                    }
                }
            }

            $attachments = [
                [
                    'type' => $canonicalMedia['type'],
                    'media_id' => $canonicalMedia['media_id'],
                    'purpose' => $canonicalMedia['purpose'],
                    'caption' => $caption,
                    'display_url' => $canonicalMedia['display_url'],
                    'file_path' => $filePath,
                    'file_name' => basename($filePath ?? 'carta.png'),
                    'size_bytes' => $sizeBytes,
                    'sha256' => $canonicalMedia['sha256'] ?? null,
                    'mime_type' => $canonicalMedia['mime_type'] ?? 'image/png',
                ]
            ];

            $this->conversationDomainService->recordMessage(
                $tenantId,
                $conversation->id,
                'AI',
                $caption,
                [
                    'attachments' => $attachments,
                    'intent' => 'REQUEST_MEDIA',
                    'terminal_dispatch' => true,
                ]
            );

            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $caption,
                    type: 'IMAGE',
                    mediaUrl: $canonicalMedia['display_url']
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);

                if (!empty($plan['pending_action_id'])) {
                    $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
                    $stateManager->markPendingActionExecuted($tenantId, (string)$conversation->id);
                }
            } catch (\Throwable $e) {
                if (!empty($plan['pending_action_id'])) {
                    $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
                    $stateManager->markPendingActionFailed($tenantId, (string)$conversation->id, $e->getMessage(), true);
                }
            }

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            // AG-37.1 TERMINAL STOP: ZERO LLM, ZERO CATALOG DUMP
            return response()->json([
                'status' => 'SUCCESS',
                'execution_status' => 'EXECUTED',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $caption,
                'intent' => 'REQUEST_MEDIA',
                'attachments' => $attachments,
                'media_url' => $canonicalMedia['display_url'],
                'latency_ms' => $latencyMs,
                'model' => 'aiva-media-terminal-dispatcher',
            ]);
        }

        // 3.3: CARRITO CONTINUO / ADD_TO_CART (ITEM_ADDED ≠ CHECKOUT)
        if ($plan['primary_action'] === 'ADD_TO_CART') {
            $orderResolver = new \App\Modules\Orders\Services\CatalogReferenceResolverService();
            $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();

            // AG-46-F: Recuperar contexto técnico activo previo (si viene de una clarificación de lado, año o color)
            $activeClarif = $stateManager->getValidTechnicalClarification($tenantId, (string)$conversation->id);
            $techContext = $activeClarif['entities'] ?? [];

            // Si no hay clarificación pendiente activa pero el cliente hace referencia anafórica ("el mismo", "la misma") o continuidad técnica,
            // hidratamos el contexto con el último producto técnico resuelto exitosamente en esta conversación (respetando precedencia E-C12)
            if (empty($techContext)) {
                $lastResolved = $stateManager->getLastResolvedTechnicalProduct($tenantId, (string)$conversation->id);
                if ($lastResolved !== null && !empty($lastResolved['technical_evidence'])) {
                    $compat = $lastResolved['technical_evidence'];
                    $lastAttrs = $lastResolved['attributes'] ?? [];
                    $techContext = [
                        'brand' => $compat['brand'] ?? null,
                        'model' => $compat['model'] ?? null,
                        'year' => $compat['year_start'] ?? null,
                        'side' => $lastAttrs['side'] ?? null,
                        'color' => $lastAttrs['color'] ?? null,
                        'presentation' => $lastAttrs['presentation'] ?? 'Lateral',
                    ];
                }
            }

            // Detectar si es una lista multilínea o contiene varios renglones
            $rawLines = preg_split('/\r\n|\r|\n/', trim($messageText));
            $orderLines = array_values(array_filter(array_map('trim', $rawLines), fn($l) => !empty($l)));

            $resolvedItems = [];
            $unresolvedItems = [];
            $lastTechResult = null;

            foreach ($orderLines as $line) {
                // Filtrar líneas que sean únicamente preámbulos conversacionales ("Ahora quiero lo siguiente:", "Anota:", etc.)
                if (preg_match('/^(?:(?:ahora\s+)?(?:quiero|deseo|anota|apunta|pido|voy\s+a\s+pedir)\s*(?:lo\s+siguiente|esto)?|lo\s+siguiente|mi\s+pedido|la\s+orden)[:\s-]*$/iu', $line)) {
                    continue;
                }

                $qty = 1;
                $dishQuery = $line;
                if (preg_match('/^([0-9]+)\s*(?:x|de)?\s+(.*)$/iu', $line, $mQty)) {
                    $qty = max(1, (int)$mQty[1]);
                    $dishQuery = trim($mQty[2]);
                } elseif (preg_match('/^(?:un|una)\s+(.*)$/iu', $line, $mQty)) {
                    $qty = 1;
                    $dishQuery = trim($mQty[1]);
                }

                // Quitar verbos y artículos conversacionales iniciales ("quiero el", "dame una", "anótame", "deseo", etc.)
                $dishQuery = preg_replace('/^(?:(?:ahora\s+)?(?:quiero|deseo|dame|deme|anotame|pido|voy\s+a\s+pedir|me\s+da|me\s+das|sirveme|agrega(?:me)?|necesito|busco)\s+)?(?:un\s+|una\s+|el\s+|la\s+|los\s+|las\s+)?/iu', '', $dishQuery);
                // Quitar "porción de", "orden de", etc.
                $dishQuery = preg_replace('/^(?:(?:1|una?)\s+)?(?:porcion|orden|plato)\s+(?:de\s+)?/iu', '', $dishQuery);
                $dishQuery = trim($dishQuery);

                $orderRes = $orderResolver->resolveCustomerReference($tenantId, $dishQuery, $techContext);
                $lastTechResult = $orderRes;

                if ($orderRes->status === 'RESOLVED') {
                    $stateManager->addItemToCart(
                        $tenantId,
                        (string)$conversation->id,
                        $orderRes->entityId,
                        $orderRes->entityName,
                        (float)$orderRes->price,
                        $qty
                    );
                    $resolvedItems[] = [
                        'name' => $orderRes->entityName,
                        'qty' => $qty,
                        'unit_price' => (float)$orderRes->price,
                        'subtotal' => (float)$orderRes->price * $qty,
                        'sku' => $orderRes->additionalData['sku'] ?? null,
                    ];
                    $stateManager->clearPendingTechnicalClarification($tenantId, (string)$conversation->id);
                    $stateManager->setLastResolvedTechnicalProduct($tenantId, (string)$conversation->id, [
                        'tenant_id' => $tenantId,
                        'catalog_version_id' => $orderRes->additionalData['catalog_version_id'] ?? null,
                        'product_id' => $orderRes->entityId,
                        'sku' => $orderRes->additionalData['sku'] ?? null,
                        'name' => $orderRes->entityName,
                        'base_price' => (string)$orderRes->price,
                        'stock' => $orderRes->additionalData['stock'] ?? null,
                        'technical_evidence' => $orderRes->additionalData['compatibility'] ?? [],
                        'attributes' => $orderRes->additionalData['attributes'] ?? [],
                    ]);
                } else {
                    $unresolvedItems[] = $line;
                }
            }

            if (!empty($resolvedItems)) {
                $cart = $stateManager->getCart($tenantId, (string)$conversation->id);
                $cartSubtotal = (float)($cart['subtotal'] ?? 0.0);
                $totalFmt = number_format($cartSubtotal, 2);

                if (count($resolvedItems) === 1 && empty($unresolvedItems)) {
                    $it = $resolvedItems[0];
                    $priceFmt = number_format($it['unit_price'], 2);
                    $cartReply = "¡Perfecto! 😊 Agregué {$it['qty']}x *{$it['name']}* por *S/ {$priceFmt}* a tu pedido.\n\n*Total acumulado: S/ {$totalFmt}*\n\n¿Deseas agregar algo más a tu orden o prefieres que coordinemos los datos de entrega?";
                } else {
                    $summaryLines = [];
                    foreach ($resolvedItems as $it) {
                        $pLine = number_format($it['subtotal'], 2);
                        $summaryLines[] = "• {$it['qty']}x *{$it['name']}* (S/ {$pLine})";
                    }
                    $itemsTxt = implode("\n", $summaryLines);

                    $cartReply = "¡Anotado! He agregado a tu pedido:\n{$itemsTxt}\n\n*Total acumulado: S/ {$totalFmt}*";

                    if (!empty($unresolvedItems)) {
                        $cartReply .= "\n\n_(Nota: No encontramos en la carta oficial: " . implode(', ', $unresolvedItems) . ")_";
                    }

                    $cartReply .= "\n\n¿Deseas agregar algo más a tu orden o prefieres que coordinemos los datos de entrega y dirección?";
                }

                $this->conversationDomainService->recordMessage(
                    $tenantId,
                    $conversation->id,
                    'AI',
                    $cartReply,
                    [
                        'intent' => 'ADD_TO_CART',
                        'cart_state' => 'OPEN',
                        'items_added' => $resolvedItems,
                        'subtotal' => $cartSubtotal,
                    ]
                );

                try {
                    $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: $cartReply,
                        type: 'TEXT'
                    );
                    (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
                } catch (\Throwable $e) {}

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                return response()->json([
                    'status' => 'SUCCESS',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => $cartReply,
                    'intent' => 'ADD_TO_CART',
                    'items_added' => $resolvedItems,
                    'cart_state' => 'OPEN',
                    'cart_subtotal' => $cartSubtotal,
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-multi-item-cart-orchestrator',
                ]);
            }

            // AG-46-F: Manejo comercial estricto de resultados técnicos cuando no se pudo agregar al carrito
            if (empty($resolvedItems) && $lastTechResult !== null) {
                // Caso F2: AMBIGUOUS (Falta lado, color o versión) -> Desambiguación interactiva
                if ($lastTechResult->status === 'AMBIGUOUS') {
                    $disambigPrompt = $lastTechResult->reason ?? "¿Podrías detallar las opciones requeridas?";
                    $stateManager->setPendingTechnicalClarification($tenantId, (string)$conversation->id, [
                        'status' => 'AMBIGUOUS',
                        'entities' => $lastTechResult->additionalData['normalized_entities'] ?? [],
                        'ambiguity_reason' => $lastTechResult->additionalData['ambiguity_reason'] ?? null,
                        'candidates' => $lastTechResult->candidates ?? [],
                        'disambiguation_prompt' => $disambigPrompt,
                    ]);

                    $this->conversationDomainService->recordMessage(
                        $tenantId,
                        $conversation->id,
                        'AI',
                        $disambigPrompt,
                        ['intent' => 'ADD_TO_CART', 'status' => 'AMBIGUOUS']
                    );

                    $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                    return response()->json([
                        'status' => 'SUCCESS',
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversation->id,
                        'phone' => $cleanPhone,
                        'jid' => $formattedJid,
                        'reply_text' => $disambigPrompt,
                        'intent' => 'ADD_TO_CART',
                        'resolution_status' => 'AMBIGUOUS',
                        'required_disambiguation' => $disambigPrompt,
                        'candidates' => $lastTechResult->candidates ?? [],
                        'attachments' => [],
                        'latency_ms' => $latencyMs,
                        'model' => 'aiva-technical-disambiguator',
                    ]);
                }

                // Caso F3: UNCONFIRMED_COMPATIBILITY (Año N/D -> Solicitar año/datos)
                if ($lastTechResult->status === 'UNCONFIRMED_COMPATIBILITY') {
                    $unconfPrompt = $lastTechResult->reason;
                    $stateManager->setPendingTechnicalClarification($tenantId, (string)$conversation->id, [
                        'status' => 'UNCONFIRMED_COMPATIBILITY',
                        'entities' => $lastTechResult->additionalData['normalized_entities'] ?? [],
                        'disambiguation_prompt' => $unconfPrompt,
                    ]);

                    $this->conversationDomainService->recordMessage(
                        $tenantId,
                        $conversation->id,
                        'AI',
                        $unconfPrompt,
                        ['intent' => 'ADD_TO_CART', 'status' => 'UNCONFIRMED_COMPATIBILITY']
                    );

                    $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                    return response()->json([
                        'status' => 'SUCCESS',
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversation->id,
                        'phone' => $cleanPhone,
                        'jid' => $formattedJid,
                        'reply_text' => $unconfPrompt,
                        'intent' => 'ADD_TO_CART',
                        'resolution_status' => 'UNCONFIRMED_COMPATIBILITY',
                        'attachments' => [],
                        'latency_ms' => $latencyMs,
                        'model' => 'aiva-unconfirmed-compatibility-handler',
                    ]);
                }

                // Caso F4: NOT_FOUND (Rechazo honesto con trazabilidad forense, sin alucinación)
                if ($lastTechResult->status === 'NOT_FOUND' && !empty($lastTechResult->additionalData['rejected_candidates'])) {
                    $stateManager->clearPendingTechnicalClarification($tenantId, (string)$conversation->id);
                    $rej = $lastTechResult->additionalData['rejected_candidates'][0] ?? [];
                    $reason = $rej['reason'] ?? '';
                    $entities = $lastTechResult->additionalData['normalized_entities'] ?? [];
                    $modelName = $entities['model'] ?? 'ese modelo';

                    if ($reason === 'YEAR_OUT_OF_RANGE') {
                        $qYear = $rej['details']['query_year'] ?? ($entities['year'] ?? '');
                        $range = $rej['details']['range'] ?? '';
                        $notMatchReply = "El carenado para {$modelName} año {$qYear} no es compatible con el rango disponible en catálogo ({$range}).";
                    } elseif ($reason === 'ATTRIBUTE_MISMATCH') {
                        $expAttr = $rej['details']['expected'] ?? '';
                        $notMatchReply = "El producto consultado para {$modelName} no está disponible con el atributo solicitado ({$expAttr}).";
                    } elseif ($reason === 'BRAND_MISMATCH' || $reason === 'MODEL_MISMATCH') {
                        $notMatchReply = "Por el momento no contamos con repuestos compatibles para esa marca o modelo en nuestro catálogo oficial.";
                    } else {
                        $notMatchReply = "No encontramos un producto compatible con las especificaciones solicitadas en nuestro catálogo.";
                    }

                    $this->conversationDomainService->recordMessage(
                        $tenantId,
                        $conversation->id,
                        'AI',
                        $notMatchReply,
                        ['intent' => 'ADD_TO_CART', 'status' => 'NO_MATCH', 'rejected_candidates' => $lastTechResult->additionalData['rejected_candidates']]
                    );

                    $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
                    return response()->json([
                        'status' => 'SUCCESS',
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversation->id,
                        'phone' => $cleanPhone,
                        'jid' => $formattedJid,
                        'reply_text' => $notMatchReply,
                        'intent' => 'ADD_TO_CART',
                        'resolution_status' => 'NO_MATCH',
                        'rejected_candidates' => $lastTechResult->additionalData['rejected_candidates'],
                        'attachments' => [],
                        'latency_ms' => $latencyMs,
                        'model' => 'aiva-honest-technical-rejection',
                    ]);
                }
            }
        }

        // 3.35: CÁLCULO DE TOTAL (CALCULATE_TOTAL - Inmunidad de Carrito OPEN)
        if ($plan['primary_action'] === 'CALCULATE_TOTAL') {
            $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
            $cart = $stateManager->getCart($tenantId, (string)$conversation->id);
            $items = $cart['items'] ?? [];
            $subtotal = (float)($cart['subtotal'] ?? 0.0);

            if (empty($items)) {
                $totalReply = "Aún no has agregado productos a tu pedido 😊. ¿Deseas que te comparta la carta para elegir?";
            } else {
                $lines = [];
                foreach ($items as $it) {
                    $uPrice = number_format((float)($it['unit_price'] ?? 0), 2);
                    $lines[] = "• {$it['quantity']}x *{$it['name']}* (S/ {$uPrice})";
                }
                $itemsText = implode("\n", $lines);
                $totalFmt = number_format($subtotal, 2);
                $totalReply = "Hasta el momento llevas en tu pedido:\n{$itemsText}\n\n*Total acumulado: S/ {$totalFmt}*\n\n¿Deseas agregar algo más a tu orden o prefieres que coordinemos la entrega?";
            }

            $this->conversationDomainService->recordMessage(
                $tenantId,
                $conversation->id,
                'AI',
                $totalReply,
                [
                    'intent' => 'CALCULATE_TOTAL',
                    'cart_state' => 'OPEN',
                    'subtotal' => $subtotal,
                ]
            );

            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $totalReply,
                    type: 'TEXT'
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
            } catch (\Throwable $e) {}

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $totalReply,
                'intent' => 'CALCULATE_TOTAL',
                'attachments' => [],
                'media_url' => null,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-cart-total-calculator',
            ]);
        }

        // 3.38: REINICIO DE CARRITO Y CANCELACIÓN CONVERSACIONAL (AG-44 / AG-41)
        if ($plan['primary_action'] === 'CART_RESET' || $resolvedIntent === 'CART_RESET_INTENT') {
            $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
            $stateManager->clearActiveCart($tenantId, (string)$conversation->id);
            $stateManager->invalidateVisualTarget($tenantId, (string)$conversation->id, 'CART_RESET');

            $resetReply = "¡Listo! He limpiado tu pedido anterior 😊. ¿Qué te gustaría consultar o pedir ahora?";

            $this->conversationDomainService->recordMessage(
                $tenantId,
                $conversation->id,
                'AI',
                $resetReply,
                ['intent' => 'CART_RESET_INTENT', 'cart_state' => 'CLEARED']
            );

            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $resetReply,
                    type: 'TEXT'
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
            } catch (\Throwable $e) {}

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $resetReply,
                'intent' => 'CART_RESET_INTENT',
                'attachments' => [],
                'media_url' => null,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-cart-reset-controller',
            ]);
        }

        // 3.39: CONSULTA DE ESTADO DE PEDIDO / TRACKING HISTÓRICO (AG-44)
        if ($plan['primary_action'] === 'ORDER_STATUS_INQUIRY' || $resolvedIntent === 'ORDER_STATUS_INQUIRY') {
            $orderResolver = new \App\Modules\Orders\Services\OrderContextResolver();
            $orderCtx = $orderResolver->resolveContext($tenantId, $customerPhone, $conversation->id, $messageText);

            if ($orderCtx['status'] === 'RESOLVED' && !empty($orderCtx['order'])) {
                $ord = $orderCtx['order'];
                $ordNum = $ord['id'] ?? $ord['order_number'] ?? 'N/A';
                $ordStatus = $ord['status'] ?? 'PENDING';
                $ordTotal = number_format((float)($ord['total'] ?? 0), 2);
                
                $statusLabels = [
                    'PENDING' => 'pendiente de confirmación',
                    'PENDING_PAYMENT' => 'pendiente de pago',
                    'PAID' => 'pagado y en preparación',
                    'CONFIRMED' => 'confirmado y en preparación',
                    'PREPARING' => 'en preparación en cocina',
                    'ON_THE_WAY' => 'en camino con el repartidor',
                    'DELIVERED' => 'entregado',
                    'CANCELLED' => 'cancelado',
                ];
                $statusHuman = $statusLabels[$ordStatus] ?? $ordStatus;

                $orderStatusReply = "¡Hola! Tu pedido #{$ordNum} por un total de S/ {$ordTotal} se encuentra actualmente *{$statusHuman}* 🛵.\n\n¿Deseas alguna información adicional sobre la entrega o necesitas coordinar algo más?";
            } else {
                $orderStatusReply = "No encontré un pedido reciente registrado con ese número o bajo este número telefónico. ¿Podrías confirmarme el número exacto de tu pedido o el nombre con el que se registró para ayudarte a revisarlo? 😊";
            }

            $this->conversationDomainService->recordMessage(
                $tenantId,
                $conversation->id,
                'AI',
                $orderStatusReply,
                ['intent' => 'ORDER_STATUS_INQUIRY', 'order_context' => $orderCtx]
            );

            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $orderStatusReply,
                    type: 'TEXT'
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
            } catch (\Throwable $e) {}

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $orderStatusReply,
                'intent' => 'ORDER_STATUS_INQUIRY',
                'attachments' => [],
                'media_url' => null,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-order-tracking-controller',
            ]);
        }

        // 3.4: CATEGORY_QUERY (Consulta de Categoría Unificada - AG-43)
        if (($plan['primary_action'] === 'CATEGORY_QUERY' || $plan['primary_action'] === 'CATEGORY_INQUIRY') && !empty($plan['category'])) {
            $detectedCategory = $plan['category'];
            $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
            $stateManager->setPendingAction(
                $tenantId,
                (string)$conversation->id,
                'MEDIA_DISPATCH',
                $detectedCategory,
                [],
                (string)$conversation->id,
                'CATEGORY_QUERY'
            );

            $categoryResponses = [
                'BEBIDAS' => "¡Sí, claro! 😊 Contamos con Chicha Morada, Maracuyá, Gaseosas (Inca Kola, Coca Cola) y Cervezas bien heladas. Si deseas, te puedo compartir la carta de bebidas para que elijas la que prefieras.",
                'PARRILLAS' => "¡Sí, claro! 😊 Contamos con Parrilla para Dos (S/ 80.00), Parrilla Familiar (S/ 150.00) y Parrilla Wallpa (S/ 180.00), además de pechuga parrillera y anticuchos. Si deseas, te puedo compartir la carta para que veas todas las opciones.",
                'PASTAS' => "¡Sí, claro! 😊 Contamos con Fetuccini a la Huancaína con Lomo o Chuleta (S/ 25.00). Si deseas, te puedo compartir la carta.",
                'SALCHIPAPAS' => "¡Sí, claro! 😊 Contamos con Salchipapa (S/ 18.00). ¿Te gustaría pedir una?",
                'CHANCHOS' => "¡Sí, claro! 😊 Contamos con Chancho al cilindro (250g, 500g, 1kg), Dúo Personal y Dúo Familiar. Si deseas, te puedo compartir la carta.",
                'ALITAS' => "¡Sí, claro! 😊 Tenemos Alitas BBQ y Alitas Crispy por 8 piezas (S/ 23.00). ¿Te gustaría pedir una porción?",
                'POLLOS' => "¡Sí, claro! 😊 Contamos con 1 Pollo (S/ 60.00), 1/2 Pollo (S/ 35.00), 1/4 Pollo (S/ 19.00), Mostritos y Promociones. ¿Deseas hacer un pedido?",
                'GUARNICIONES' => "¡Sí! 😊 Contamos con papas andinas, ensalada fresca, arroz chaufa y plátano frito. ¿Deseas agregar alguna?",
            ];

            $categoryReply = $categoryResponses[$detectedCategory] ?? "¡Sí, claro! 😊 Contamos con variadas opciones de {$detectedCategory}. Si deseas, te puedo compartir la carta para que veas todos los detalles.";

            $this->conversationDomainService->recordMessage(
                $tenantId,
                $conversation->id,
                'AI',
                $categoryReply,
                [
                    'intent' => 'CATEGORY_QUERY',
                    'category' => $detectedCategory,
                    'pending_action' => 'MEDIA_DISPATCH',
                ]
            );

            try {
                $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                    tenantId: $tenantId,
                    recipientPhone: $customerPhone,
                    content: $categoryReply,
                    type: 'TEXT'
                );
                (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
            } catch (\Throwable $e) {}

            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'SUCCESS',
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'phone' => $cleanPhone,
                'jid' => $formattedJid,
                'reply_text' => $categoryReply,
                'intent' => 'CATEGORY_QUERY',
                'attachments' => [],
                'media_url' => null,
                'latency_ms' => $latencyMs,
                'model' => 'aiva-category-resolver',
            ]);
        }

        // AG-35: PRODUCT_PRICE_INQUIRY (Consulta de Precio con Autoridad MySQL y Calificadores)
        if ($resolvedIntent === 'PRODUCT_PRICE_INQUIRY') {
            $resolver = new \App\Modules\Orders\Services\CatalogReferenceResolverService();
            $resolution = $resolver->resolveCustomerReference($tenantId, $messageText);

            if ($resolution->status === 'RESOLVED') {
                $priceFmt = number_format((float)$resolution->price, 2);
                $productReply = "¡Claro! Nuestra {$resolution->entityName} tiene un precio de S/ {$priceFmt} 😊 ¿Deseas realizar tu pedido?";

                $this->conversationDomainService->recordMessage(
                    $tenantId,
                    $conversation->id,
                    'AI',
                    $productReply,
                    [
                        'intent' => 'PRODUCT_PRICE_INQUIRY',
                        'product_id' => $resolution->entityId,
                        'price' => $resolution->price,
                    ]
                );

                try {
                    $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: $productReply,
                        type: 'TEXT'
                    );
                    (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
                } catch (\Throwable $e) {}

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'status' => 'SUCCESS',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => $productReply,
                    'intent' => 'PRODUCT_PRICE_INQUIRY',
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-price-authority-mysql',
                ]);
            } elseif ($resolution->status === 'AMBIGUOUS') {
                $candidates = $resolution->candidates ?? [];
                $productReply = "Tenemos varias opciones: " . implode(" y ", $candidates) . ". ¿Cuál de ellas te gustaría?";

                $this->conversationDomainService->recordMessage(
                    $tenantId,
                    $conversation->id,
                    'AI',
                    $productReply,
                    ['intent' => 'PRODUCT_PRICE_INQUIRY', 'status' => 'AMBIGUOUS']
                );

                try {
                    $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: $productReply,
                        type: 'TEXT'
                    );
                    (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
                } catch (\Throwable $e) {}

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'status' => 'SUCCESS',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => $productReply,
                    'intent' => 'PRODUCT_PRICE_INQUIRY',
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-ambiguity-handler',
                ]);
            } elseif ($resolution->status === 'NOT_FOUND') {
                $productReply = "Por el momento no contamos con ese producto en nuestra carta oficial 😊 ¿Deseas que te comparta la carta para ver nuestras opciones disponibles?";

                $this->conversationDomainService->recordMessage(
                    $tenantId,
                    $conversation->id,
                    'AI',
                    $productReply,
                    ['intent' => 'PRODUCT_PRICE_INQUIRY', 'status' => 'NOT_FOUND']
                );

                try {
                    $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: $productReply,
                        type: 'TEXT'
                    );
                    (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
                } catch (\Throwable $e) {}

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'status' => 'SUCCESS',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => $productReply,
                    'intent' => 'PRODUCT_PRICE_INQUIRY',
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-anti-hallucination',
                ]);
            }
        }

        // AG-35: ORDER_INTENT con Múltiples Ítems o Ambigüedad Competidora
        if ($resolvedIntent === 'ORDER_INTENT') {
            $orderRefResolver = new \App\Modules\Orders\Services\CatalogReferenceResolverService();
            $orderResolution = $orderRefResolver->resolveCustomerReference($tenantId, $messageText);
            if ($orderResolution->status === 'AMBIGUOUS') {
                $candidates = $orderResolution->candidates ?? [];
                $orderAmbiguityReply = "Mencionaste varias opciones: " . implode(" y ", $candidates) . ". ¿Deseas ordenar ambas o prefieres alguna de ellas? 😊";

                $this->conversationDomainService->recordMessage(
                    $tenantId,
                    $conversation->id,
                    'AI',
                    $orderAmbiguityReply,
                    ['intent' => 'ORDER_INTENT', 'status' => 'AMBIGUOUS']
                );

                try {
                    $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                        tenantId: $tenantId,
                        recipientPhone: $customerPhone,
                        content: $orderAmbiguityReply,
                        type: 'TEXT'
                    );
                    (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
                } catch (\Throwable $e) {}

                $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'status' => 'SUCCESS',
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'phone' => $cleanPhone,
                    'jid' => $formattedJid,
                    'reply_text' => $orderAmbiguityReply,
                    'intent' => 'ORDER_INTENT',
                    'attachments' => [],
                    'media_url' => null,
                    'latency_ms' => $latencyMs,
                    'model' => 'aiva-ambiguity-handler',
                ]);
            }
        }

        // 3. Obtener Prompt del Sistema e Inyectar Contexto Canónico Resuelto (CI-03)
        $brainRes = $this->getBrainContext($request)->getData(true);
        $systemPrompt = $brainRes['system_prompt'];
        $catalogLink = $brainRes['catalog_link'] ?? null;
        if (empty($catalogLink)) {
            $webCatalogSource = DB::table('business_sources')
                ->where('tenant_id', $tenantId)
                ->where(function($q) {
                    $q->where('source_type', 'WEB_URL')
                      ->orWhereNotNull('external_url');
                })
                ->where(function($q) {
                    $q->whereNull('status')
                      ->orWhereNotIn('status', ['ERROR', 'DELETED', 'INACTIVE']);
                })
                ->whereNotNull('external_url')
                ->where('external_url', '!=', '')
                ->orderByDesc('id')
                ->first();
            $catalogLink = $webCatalogSource && !empty($webCatalogSource->external_url) ? trim($webCatalogSource->external_url) : null;
        }

        // Inyectar Estado Operacional Resuelto desde MySQL en el Runtime Context de la IA
        if ($canonicalContext['order']['exists']) {
            $ordData = $canonicalContext['order'];

            $orderItems = DB::table('order_items')->where('order_id', $ordData['id'])->get();
            $itemsSummary = [];
            if ($orderItems->isNotEmpty()) {
                foreach ($orderItems as $item) {
                    $qty = (int)($item->quantity ?? 1);
                    $name = $item->product_name ?? $item->name ?? 'Producto';
                    $subtotal = number_format((float)($item->subtotal ?? $item->total_price ?? 0), 2);
                    $itemsSummary[] = "{$qty}x {$name} (S/ {$subtotal})";
                }
            }
            $itemsText = !empty($itemsSummary)
                ? implode(', ', $itemsSummary)
                : 'Sin productos registrados aún en el detalle';

            $orderContextText = "\n\n[CANONICAL RUNTIME CONTEXT - ESTADO OPERACIONAL DESDE MYSQL]:"
                . "\n• Order Number: {$ordData['order_number']}"
                . "\n• Status: {$ordData['status']}"
                . "\n• Total Amount en MySQL: S/ " . number_format((float)$ordData['total_amount'], 2)
                . "\n• Productos Registrados en MySQL: {$itemsText}"
                . "\n• Delivery Zone: " . ($ordData['delivery_zone'] ?: 'General')
                . "\n• Payment Status: " . $canonicalContext['payment']['status']
                . "\n• Conversation State: " . $canonicalContext['conversation']['state']
                . "\n• REGLA ESTRICTA DE CONSISTENCIA DE MONTO E ÍTEMS (PROHIBIDO CONTRADECIRSE):"
                . "\n  1. Debes ser 100% CONSISTENTE con la información de MySQL mostrada arriba. Queda ESTRICTAMENTE PROHIBIDO dar montos contradictorios en mensajes consecutivos (ejemplo: NUNCA menciones S/ 18.00 en una respuesta y luego S/ 6.00 o S/ 0.00 en la siguiente)."
                . "\n  2. Si el cliente aclara que su pedido es por '1 rollo de canela + delivery a Ñaña' (S/ 6.00), respóndele amablemente confirmando que el total exacto a transferir es S/ 6.00."
                . "\n  3. Si el monto registrado en MySQL difiere de lo que el cliente indica, aclara amablemente la actualización al monto correcto (S/ 6.00) de forma transparente y servicial, sin dar respuestas mecánicas ni números de orden inexistentes.";
            $systemPrompt .= $orderContextText;
        } elseif ($canonicalContext['conversation']['state'] === 'CONTEXT_CONFLICT') {
            $conflictText = "\n\n[CANONICAL RUNTIME CONTEXT - MULTIPLE ORDERS CONFLICT (CI-GATE-5)]:"
                . "\n• ATENCIÓN: El cliente tiene múltiples pedidos pendientes. NO SELECCIONES UN PEDIDO AUTOMÁTICAMENTE. Pregunta amablemente a cuál de los pedidos pendientes se refiere.";
            $systemPrompt .= $conflictText;
        }

        // Resolutor de Referencias Comerciales de Catálogo y Precios (P3.14 / AG-46 / P3.10-A)
        try {
            $exemptIntents = ['CATEGORY_QUERY', 'CATEGORY_INQUIRY', 'REQUEST_MEDIA', 'ORDER_STATUS_INQUIRY', 'CART_STATUS_INQUIRY', 'CART_RESET_INTENT', 'GREETING', 'LOCATION_INQUIRY', 'HOURS_INQUIRY'];
            if (!in_array($resolvedIntent, $exemptIntents)) {
                $stateManager = new \App\Modules\Messaging\Services\ConversationStateManager();
                $activeClarif = $stateManager->getValidTechnicalClarification($tenantId, (string)$conversation->id, $resolvedIntent);
                $techContext = $activeClarif['entities'] ?? [];
                if (!empty($activeClarif['candidate_scope'])) {
                    $techContext['candidate_scope'] = $activeClarif['candidate_scope'];
                }

                $refResolver = new \App\Modules\Orders\Services\CatalogReferenceResolverService();
                $refResult = $refResolver->resolveReference($tenantId, $messageText, $techContext);

                if ($refResult->status === 'RESOLVED') {
                    $stateManager->clearPendingTechnicalClarification($tenantId, (string)$conversation->id);
                    $priceStr = number_format($refResult->price, 2);
                    $systemPrompt .= "\n\n[RESOLUCIÓN AUTORIZADA DE CATÁLOGO (MYSQL - AG-46)]:"
                        . "\n• La frase del cliente ('{$messageText}') se resolvió a la opción de catálogo autorizada: '{$refResult->resolvedName}' (Precio oficial MySQL: S/ {$priceStr})."
                        . "\n• DEBES responder confirmando la opción '{$refResult->resolvedName}' al precio oficial de S/ {$priceStr} según el catálogo publicado.";
                } elseif ($refResult->status === 'AMBIGUOUS') {
                    $candsText = implode(', ', array_map(function($c) {
                        if (is_array($c)) {
                            return "{$c['name']} (S/ " . number_format($c['price'] ?? 0, 2) . ")";
                        }
                        return (string)$c;
                    }, $refResult->candidates ?? []));

                    // P3.14-02 & P3.14-04: Persistir TechnicalClarificationState con candidate_scope y prioridad
                    $dominantAttr = $stateManager->determineDominantAttribute([
                        'ambiguity_reason' => $refResult->additionalData['ambiguity_reason'] ?? null
                    ]);
                    $stateManager->setPendingTechnicalClarification($tenantId, (string)$conversation->id, [
                        'status' => 'AMBIGUOUS',
                        'intent' => $resolvedIntent,
                        'entities' => $refResult->additionalData['normalized_entities'] ?? $techContext,
                        'ambiguity_reason' => $refResult->additionalData['ambiguity_reason'] ?? null,
                        'dominant_clarification_attribute' => $dominantAttr,
                        'candidates' => $refResult->candidates ?? [],
                        'candidate_scope' => $refResult->additionalData['compatible_candidates'] ?? [],
                        'disambiguation_prompt' => $refResult->reason ?? $candsText,
                    ]);

                    $systemPrompt .= "\n\n[RESOLUCIÓN DE CATÁLOGO - AMBIGÜEDAD DETECTADA (P3.14-04)]:"
                        . "\n• ATENCIÓN: La frase del cliente ('{$messageText}') coincide con múltiples opciones: {$candsText}."
                        . "\n• PRIORIDAD DE CLARIFICACIÓN: Pregunta amablemente sobre la opción faltante ({$dominantAttr}) entre las opciones disponibles: {$candsText}."
                        . "\n• REGLA ESTRICTA DE AMBIGÜEDAD (P3.14-08): Queda TERMINANTEMENTE PROHIBIDO seleccionar una opción por defecto o adivinar. DEBES pedir la aclaración al cliente.";
                } elseif ($refResult->status === 'NOT_FOUND' && !empty($refResult->additionalData) && !($refResult->additionalData['commercial_substitution_allowed'] ?? true)) {
                    $systemPrompt .= "\n\n[RESOLUCIÓN DE REFERENCIA - PRODUCTO NO DISPONIBLE (AG-35)]:"
                        . "\n• La opción o repuesto consultado por el cliente ('{$messageText}') NO SE ENCUENTRA REGISTRADO en el catálogo oficial."
                        . "\n• REGLA CONSTITUCIONAL AG-35 (PROHIBICIÓN ESTRICTA DE SUSTITUCIÓN): Queda TERMINANTEMENTE PROHIBIDO sustituir este producto por otro de diferente especificación o precio. DEBES responder amablemente aclarando que actualmente no disponen de esa opción en el catálogo oficial, sin inventar ni ofrecer otra haciéndola pasar por la solicitada.";
                }
            }
        } catch (\Throwable $e) {}

        // Inyectar Directiva de Naturalidad Humana y Memoria de Nombre del Cliente
        $knownCustomer = DB::table('customers')->where('tenant_id', $tenantId)->where('phone', $cleanPhone)->first();
        $targetName = trim($customerName ?: ($knownCustomer->name ?? ''));
        $businessName = $brainRes['business_name'] ?? '';

        $invalidNames = ['oficial', 'admin', 'operator', 'cliente', 'system', 'donuts', mb_strtolower($businessName)];
        $isInvalid = false;
        if (!empty($targetName)) {
            $lowerTarget = mb_strtolower($targetName);
            foreach ($invalidNames as $inv) {
                if (!empty($inv) && str_contains($lowerTarget, $inv)) {
                    $isInvalid = true;
                    break;
                }
            }
        }

        if (!empty($targetName) && !$isInvalid && !str_starts_with($targetName, 'Cliente ')) {
            $naturalNameContext = "\n\n[MEMORIA DE IDENTIDAD Y REGLA DE NATURALIDAD HUMANA]:"
                . "\n• Nombre Confirmado del Cliente: {$targetName}"
                . "\n• Teléfono WhatsApp: {$customerPhone}"
                . "\n• REGLA DE INTERACCIÓN HUMANA NATURAL: Conoces el nombre del cliente ({$targetName}). Puedes usarlo al saludar o en momentos muy esporádicos si la oración lo requiere naturally. Queda ESTRICTAMENTE PROHIBIDO repetirlo en cada respuesta o forzarlo en frases donde suena robótico o artificial. La conversación debe sentirse 100% natural, tal como respondería una persona real por WhatsApp.";

            $systemPrompt .= $naturalNameContext;
        }

        // 4. Construir Historial de Memoria Conversacional (Últimos 20 turnos para mantener contexto completo)
        $historyMessages = $this->conversationDomainService->getMessagesForConversation($tenantId, $conversation->id);
        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        $recentTurnMessages = $historyMessages->take(-20);
        foreach ($recentTurnMessages as $m) {
            $role = $m->sender_type === 'CUSTOMER' ? 'user' : 'assistant';
            $cleanContent = preg_replace('#https?://[^\s]+#', '', $m->content);
            $messagesPayload[] = [
                'role' => $role,
                'content' => trim($cleanContent) ?: $m->content
            ];
        }

        // 5. Ejecutar IA (Groq LPU o OpenAI según la API Key configurada)
        $groqApiKey = trim(env('GROQ_API_KEY', ''));
        $openAiApiKey = trim(env('OPENAI_API_KEY', ''));
        
        $aiReply = "Entendido. ¿En qué más te puedo ayudar?";
        $usedModel = 'fallback-static';
        $tokensInput = 0;
        $tokensOutput = 0;

        // Determinar endpoint y llave de API
        $apiKey = !empty($groqApiKey) ? $groqApiKey : $openAiApiKey;
        $isSim = $request->header('X-Simulation') || $request->attributes->get('is_simulation');

        if (!empty($apiKey) && !$isSim) {
            $isGroqKey = str_starts_with($apiKey, 'gsk_');
            $endpoint = $isGroqKey ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
            $models = $isGroqKey 
                ? ['openai/gpt-oss-120b', 'llama-3.1-8b-instant', 'gemma2-9b-it'] 
                : ['gpt-4o-mini', 'gpt-3.5-turbo'];

            foreach ($models as $modelId) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ])->timeout(10)->post($endpoint, [
                        'model' => $modelId,
                        'messages' => $messagesPayload
                    ]);

                    if ($response->successful()) {
                        $resData = $response->json();
                        $replyText = $resData['choices'][0]['message']['content'] ?? null;
                        if (!empty($replyText)) {
                            $aiReply = $replyText;
                            $usedModel = $modelId;
                            $tokensInput = $resData['usage']['prompt_tokens'] ?? 0;
                            $tokensOutput = $resData['usage']['completion_tokens'] ?? 0;
                            break;
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning("AI API Model {$modelId} failed [{$endpoint}]: " . $response->status() . ' - ' . $response->body());
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("AI API Exception on {$modelId}: " . $e->getMessage());
                }
            }
        }

        // Si la llamada externa falló o la API Key no es válida, usar el Motor Comercial RAG Inteligente Local de AIVA (Intent-Aware - AG-45)
        if ($usedModel === 'fallback-static') {
            $msgLower = mb_strtolower($messageText);
            $businessName = $brainRes['business_name'] ?? 'Wallpa Brasa';
            $businessCategory = $brainRes['business_category'] ?? 'Restaurante';
            
            if ($resolvedIntent === 'ORDER_STATUS_INQUIRY') {
                $aiReply = "En este momento no puedo consultar el estado de tu pedido debido a una intermitencia técnica. Por favor indícame tu número de pedido o intenta nuevamente en unos minutos.";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif ($resolvedIntent === 'CART_STATUS_INQUIRY') {
                $aiReply = "En este momento no puedo recuperar el detalle de tu carrito. Por favor confírmame los platos que deseas verificar.";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif ($resolvedIntent === 'CART_RESET_INTENT') {
                $aiReply = "Listo, he limpiado tu selección anterior. ¿Qué te gustaría consultar o pedir ahora?";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif ($resolvedIntent === 'CATEGORY_QUERY' || $resolvedIntent === 'CATEGORY_INQUIRY') {
                $aiReply = "Contamos con una variedad de opciones en esa categoría. ¿Te gustaría que te comparta la carta completa para revisarla?";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif ($resolvedIntent === 'ORDER_INTENT' || $resolvedIntent === 'ADD_TO_CART') {
                $aiReply = "Disculpa la demora técnica. Por favor indícame nuevamente qué platos y cantidades deseas para registrar tu pedido.";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif ($resolvedIntent === 'PRODUCT_PRICE_INQUIRY') {
                $aiReply = "Disculpa la intermitencia momentánea. ¿Podrías indicarme qué plato deseas consultar para darte su precio exacto?";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif (str_contains($msgLower, 'hola') || str_contains($msgLower, 'buenas') || str_contains($msgLower, 'saludos') || str_contains($msgLower, 'heyy') || str_contains($msgLower, 'holas')) {
                // FALLBACK CONTEXT-AWARE BASADO EN ESTADO CANÓNICO COMPLETO (Sección 29 y 30)
                $hasActiveOrder = !empty($canonicalContext['order']['exists']) && $canonicalContext['order']['exists'] === true;
                if ($hasActiveOrder) {
                    $orderNum = $canonicalContext['order']['order_number'] ?? 'en curso';
                    $orderTotal = $canonicalContext['order']['total'] ?? '0.00';
                    $payStatus = strtoupper($canonicalContext['order']['payment_status'] ?? 'UNPAID');
                    $ordStatus = strtoupper($canonicalContext['order']['status'] ?? 'PENDING');
                    $pendingAmt = $canonicalContext['order']['pending_amount'] ?? $orderTotal;

                    if ($payStatus === 'PAID' || $ordStatus === 'PAID' || $pendingAmt === '0.00') {
                        $aiReply = "¡Hola! Tu pedido N° {$orderNum} ya se encuentra pagado y está en preparación. ¿En qué más te puedo ayudar?";
                    } else {
                        $aiReply = "¡Hola! Sigo aquí contigo coordinando tu pedido N° {$orderNum} por un total de S/ {$orderTotal} (saldo pendiente: S/ {$pendingAmt}). ¿Deseas enviar tu comprobante de pago o necesitas ayuda?";
                    }
                } elseif ($isWelcomeForbidden) {
                    $aiReply = "¡Hola de nuevo! 😊 ¿Qué te gustaría consultar o pedir hoy?";
                } else {
                    $aiReply = "¡Hola! 👋 Bienvenido a {$businessName}. ¿En qué te puedo ayudar hoy? Te ofrecemos nuestra variada carta y especialidades.";
                }
                $usedModel = 'aiva-local-commercial-brain';
            } elseif (str_contains($msgLower, 'no me llega') || str_contains($msgLower, 'no llega') || str_contains($msgLower, 'dónde') || str_contains($msgLower, 'donde') || str_contains($msgLower, 'catálogo') || str_contains($msgLower, 'catalogo') || str_contains($msgLower, 'virtual') || str_contains($msgLower, 'link') || str_contains($msgLower, 'enlace') || str_contains($msgLower, 'tienda') || str_contains($msgLower, 'productos') || str_contains($msgLower, 'menú') || str_contains($msgLower, 'menu') || str_contains($msgLower, 'carta') || str_contains($msgLower, 'platos')) {
                if (!empty($catalogLink)) {
                    $aiReply = "¡Claro que sí! 📋 Puedes explorar nuestro catálogo virtual oficial en el siguiente enlace:\n\n🔗 {$catalogLink}\n\n¿Deseas realizar algún pedido o tienes alguna consulta?";
                } else {
                    $aiReply = "¡Claro que sí! 😊 Te puedo compartir la carta de nuestro negocio o informarte sobre nuestros platos y precios. ¿Qué te gustaría consultar?";
                }
                $usedModel = 'aiva-local-commercial-brain';
            } elseif (str_contains($msgLower, 'delivery') || str_contains($msgLower, 'envío') || str_contains($msgLower, 'envio') || str_contains($msgLower, 'costo') || str_contains($msgLower, 'tarifa') || str_contains($msgLower, 'cobertura')) {
                $zones = DB::table('delivery_zones')->where('tenant_id', $tenantId)->where('is_active', true)->get();
                $zStr = $zones->map(fn($z) => "• " . $z->zone_name . ": S/ " . number_format((float)$z->delivery_fee, 2))->join("\n");
                $aiReply = "🚚 Contamos con servicio de delivery oficial para las siguientes zonas de cobertura:\n\n" . ($zStr ?: "• Consultar cobertura directamente con nuestro operador.") . "\n\n¿A qué distrito te gustaría que enviemos tu pedido?";
                $usedModel = 'aiva-local-commercial-brain';
            } elseif (str_contains($msgLower, 'dirección') || str_contains($msgLower, 'direccion') || str_contains($msgLower, 'ubicación') || str_contains($msgLower, 'ubicacion') || str_contains($msgLower, 'recoger') || str_contains($msgLower, 'recogida') || str_contains($msgLower, 'quedan') || str_contains($msgLower, 'local')) {
                $profAddr = DB::table('business_profiles')->where('tenant_id', $tenantId)->value('address');
                $realAddr = !empty($profAddr) ? trim($profAddr) : 'Consultar dirección exacta con nuestro operador.';
                $aiReply = "📍 Nuestra sede principal y punto de venta se encuentra ubicada en:\n\n{$realAddr}\n\n¿Te gustaría pasar a recoger tu pedido o prefieres envío a domicilio?";
                $usedModel = 'aiva-local-commercial-brain';
            } else {
                if ($isWelcomeForbidden) {
                    $aiReply = "¿En qué te puedo ayudar hoy con tu pedido o consulta?";
                } else {
                    $aiReply = "¡Gracias por comunicarte con {$businessName}! Te puedo brindar información de nuestro menú, precios, zonas de delivery o tomar tu pedido. ¿Qué te gustaría consultar?";
                }
                $usedModel = 'aiva-local-commercial-brain';
            }
        }

        // 4. Resolutor Híbrido de Intención y Adjuntos Multimedia Canónicos (P2.MEDIA Contract)
        $attachments = [];
        if ($resolvedIntent === 'REQUEST_MEDIA' || $resolvedIntent === 'MENU_REQUEST') {
            $mediaResolver = new \App\Modules\Business\Services\BusinessMediaResolverService();
            $canonicalMedia = $mediaResolver->resolveMenuMedia($tenantId, $messageText, (string)$conversation->id);
            if ($canonicalMedia) {
                $filePath = $canonicalMedia['file_path'] ?? null;
                if ($filePath) {
                    if (file_exists(public_path($filePath))) {
                        $filePath = public_path($filePath);
                    } elseif (file_exists(storage_path('app/' . $filePath))) {
                        $filePath = storage_path('app/' . $filePath);
                    } elseif (file_exists($filePath)) {
                        $filePath = realpath($filePath) ?: $filePath;
                    }
                }
                $attachments[] = [
                    'type' => $canonicalMedia['type'],
                    'media_id' => $canonicalMedia['media_id'],
                    'purpose' => $canonicalMedia['purpose'],
                    'caption' => $canonicalMedia['caption'],
                    'display_url' => $canonicalMedia['display_url'],
                    'file_path' => $filePath,
                    'file_name' => basename($filePath ?? 'carta.png'),
                ];

                if (empty($aiReply) || strlen($aiReply) < 15 || str_contains($aiReply, 'carta oficial: 📄✨') || str_contains($aiReply, 'carta oficial:') || str_contains($aiReply, 'Te puedo brindar información') || str_contains($aiReply, 'Gracias por comunicarte')) {
                    $aiReply = "¡Claro! Aquí tienes nuestra {$canonicalMedia['caption']} 😊";
                }
            }
        }

        // AG-45 Response Governance: si la bienvenida está prohibida, remover únicamente el saludo inicial repetitivo sin destruir la respuesta útil del modelo
        if ($isWelcomeForbidden) {
            $aiReply = preg_replace('/^(?:¡?hola!?\s*)*(?:¡?gracias\s+por\s+comunicarte\s+con[^.!?]+[.!?]*|bienvenido(?:\s+de\s+nuevo)?\s+a[^.!?]+[.!?]*)\s*/iu', '', $aiReply);
            $aiReply = trim($aiReply);
            if (empty($aiReply)) {
                $aiReply = "¿En qué te puedo ayudar hoy con tu consulta o pedido? 😊";
            }
        }

        // Limpiar sintaxis de markdown o rutas locales internas del mensaje antes de persistir
        $aiReply = preg_replace('#!\[[^\]]*\]\([^)]*\)#', '', $aiReply);
        $aiReply = preg_replace('#\*\([^)]+\)\*#', '', $aiReply);
        // Limpiar únicamente URLs locales de uploads (ej: http://.../uploads/...) para no duplicar si se envía como media
        $aiReply = preg_replace('#https?://[^\s]+/uploads/[^\s]+#', '', $aiReply);
        $aiReply = trim(str_replace(['Carta en imagen:', 'Carta / Menú Oficial:', 'enlace oficial:', '  '], ' ', $aiReply));

        // Si el cliente consultó por catálogo/link y existe catalogLink configurado pero la IA no lo incluyó en el texto:
        $msgLowerClean = mb_strtolower($messageText);
        $isCatalogIntent = str_contains($msgLowerClean, 'catálogo') || str_contains($msgLowerClean, 'catalogo') || str_contains($msgLowerClean, 'link') || str_contains($msgLowerClean, 'enlace') || str_contains($msgLowerClean, 'tienda') || str_contains($msgLowerClean, 'donde') || str_contains($msgLowerClean, 'dónde') || str_contains($msgLowerClean, 'no llega') || str_contains($msgLowerClean, 'no me llega') || str_contains($msgLowerClean, 'carta') || str_contains($msgLowerClean, 'menú') || str_contains($msgLowerClean, 'menu');
        if (!empty($catalogLink) && $isCatalogIntent && !str_contains($aiReply, $catalogLink)) {
            $aiReply .= "\n\n🔗 Catálogo Virtual: " . $catalogLink;
        }

        if (!empty($catalogLink) && str_contains($aiReply, 'carta oficial:') && !str_contains($aiReply, $catalogLink)) {
            $aiReply .= "\n\n🔗 Catálogo Virtual: " . $catalogLink;
        }

        if (empty($aiReply) || strlen($aiReply) < 5) {
            if (!empty($catalogLink)) {
                $aiReply = "¡Claro! Puedes ver nuestro catálogo virtual oficial aquí:\n\n🔗 " . $catalogLink;
            } else {
                $aiReply = "¿En qué te puedo ayudar hoy? Puedes consultarme sobre nuestras cartas o cualquier plato.";
            }
        }

        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

        // 5. Registrar la respuesta de la IA con Metadata enriquecida y Attachments canónicos en MySQL
        $aiMessage = $this->conversationDomainService->recordMessage(
            $tenantId,
            $conversation->id,
            'AI',
            $aiReply,
            [
                'provider' => 'groq',
                'model' => $usedModel,
                'latency_ms' => $latencyMs,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'attachments' => $attachments,
                'intent' => $resolvedIntent,
                'tool_calls' => ['search_catalog']
            ]
        );

        // Sincronizar automáticamente el pedido SQL en el Centro de Operaciones (Matrix 4D) si hay confirmación de venta
        try {
            $orderParser = new \App\Modules\Orders\Services\AutomaticOrderParserService();
            $orderParser->syncOrderFromConversation($tenantId, $customerPhone, $customerName, $aiReply, $messageText);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error sincronizando pedido desde conversación: " . $e->getMessage());
        }

        // Despacho activo al celular vía Meta WhatsApp API si está configurado
        try {
            $outgoingDTO = new \App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO(
                tenantId: $tenantId,
                recipientPhone: $customerPhone,
                content: $aiReply,
                type: 'TEXT'
            );
            (new \App\Modules\Messaging\Providers\MetaWhatsAppClient())->sendMessage($outgoingDTO);
        } catch (\Throwable $e) {}

        // Formatear JID exacto para el Gateway de WhatsApp (Baileys / Node.js)
        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        $formattedJid = str_contains($customerPhone, '@') 
            ? $customerPhone 
            : (strlen($cleanPhone) >= 14 ? $cleanPhone . '@lid' : $cleanPhone . '@s.whatsapp.net');



        return response()->json([
            'status' => 'SUCCESS',
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'phone' => $cleanPhone,
            'jid' => $formattedJid,
            'reply_text' => $aiReply,
            'intent' => $resolvedIntent,
            'attachments' => $attachments,
            'media_url' => !empty($attachments) ? $attachments[0]['display_url'] : null,
            'latency_ms' => $latencyMs,
            'model' => $usedModel,
        ]);
    }
}
