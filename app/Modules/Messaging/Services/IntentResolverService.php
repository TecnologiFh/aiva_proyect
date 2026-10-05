<?php

namespace App\Modules\Messaging\Services;

class IntentResolverService
{
    /**
     * Resolver la intención del mensaje del cliente bajo el estándar AG-37 de P3.11:
     * - REQUEST_MEDIA / MENU_REQUEST: Cliente pide VER/ENVIAR la carta física o foto.
     * - CATEGORY_INQUIRY: Cliente pregunta si tienen o qué opciones tienen de una categoría.
     * - PRODUCT_PRICE_INQUIRY: Cliente consulta precio o cotización de un producto/plato específico.
     * - ORDER_INTENT: Cliente expresa deseo de comprar/pedir un producto.
     * - GENERAL_QUERY: Saludos, delivery, dirección, medios de pago, etc.
     */
    public function resolveIntent(string $messageText, ?string $aiReply = null): string
    {
        $details = $this->resolveIntentDetails($messageText);
        return $details['intent'];
    }

    /**
     * Resolución detallada de la intención con extracción de categoría si aplica.
     */
    public function resolveIntentDetails(string $messageText, ?int $tenantId = null): array
    {
        $textRaw = mb_strtolower(trim($messageText));

        // Normalización de acentos para comparación determinística
        $normalized = strtr($textRaw, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n', 'ü' => 'u'
        ]);
        // Quitar signos de interrogación y exclamación
        $normalized = trim(preg_replace('/[¿?¡!.,;:]+/', ' ', $normalized));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Normalización de caracteres y vocales repetidas ("cartaaaaa" -> "carta", "holaaaa" -> "hola", "porfaaaa" -> "porfa")
        $normalized = preg_replace('/([a-z])\1{2,}/i', '$1', $normalized);

        // AG-42: Normalización léxica de typos comunes en consultas
        $typoMap = [
            '/\bpars\b/i' => 'para',
            '/\btines\b/i' => 'tienes',
            '/\bbebids\b/i' => 'bebidas',
            '/\bpeddo\b/i' => 'pedido',
            '/\bpeddos\b/i' => 'pedidos',
            '/\bgasiosa\b/i' => 'gaseosa',
            '/\bgasiosas\b/i' => 'gaseosas',
        ];
        foreach ($typoMap as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        // AG-39 / AG-44: 0.1 DESVINCULACIÓN / REINICIO DE CARRITO (CART_RESET_INTENT)
        // Casos: "Aún no te he pedido nada", "no te he pedido nada", "borra mi carrito", "cancela mi pedido"
        if (preg_match('/\b(aun\s+no\s+(?:te\s+)?(?:he\s+)?(?:pedido|pido)\s+nada|no\s+(?:te\s+)?(?:he\s+)?pedido\s+nada|borra\s+(?:el|mi)\s+(?:carrito|pedido)|cancela\s+(?:el|mi)\s+(?:carrito|pedido)|reinicia\s+(?:el|mi)\s+pedido|empecemos\s+de\s+nuevo|no\s+quiero\s+nada\s+de\s+(?:eso|antes)|deja\s+(?:el|mi)\s+pedido\s+en\s+cero)\b/i', $normalized)) {
            return [
                'intent' => 'CART_RESET_INTENT',
                'category' => null,
                'normalized' => $normalized
            ];
        }

        // AG-39: 0.2 CONSULTA DE ESTADO DE ORDEN OPERACIONAL (ORDER_STATUS_INQUIRY)
        // Casos: "Consulta si tengo algún pedido pendiente", "Quiero saber si tengo algún pedido pendiente", "¿en qué estado está mi pedido?"
        if (preg_match('/\b(tengo\s+(?:algun\s+)?pedido\s+pendiente|consulta\s+si\s+tengo\s+(?:algun\s+)?pedido|quiero\s+saber\s+si\s+tengo\s+(?:algun\s+)?pedido|en\s+que\s+estado\s+esta\s+mi\s+pedido|donde\s+esta\s+mi\s+pedido|como\s+va\s+mi\s+pedido|informame\s+sobre\s+mi\s+pedido|saber\s+de\s+mi\s+pedido|estado\s+de\s+mi\s+(?:pedido|orden))\b/i', $normalized)) {
            return [
                'intent' => 'ORDER_STATUS_INQUIRY',
                'category' => null,
                'normalized' => $normalized
            ];
        }

        // AG-39: 0.3 CONSULTA DE ESTADO DE CARRITO ACTUAL (CART_STATUS_INQUIRY)
        // Casos: "¿qué tengo en mi pedido?", "¿qué llevo?", "revisar carrito", "ver carrito"
        if (preg_match('/\b(que\s+tengo\s+en\s+mi\s+pedido|que\s+llevo|que\s+productos\s+tengo\s+agregados|revisar\s+carrito|ver\s+carrito|que\s+hay\s+en\s+el\s+carrito)\b/i', $normalized)) {
            return [
                'intent' => 'CART_STATUS_INQUIRY',
                'category' => null,
                'normalized' => $normalized
            ];
        }

        // AG-39: 0.4 CONSULTA DE HISTORIAL DE PEDIDOS (ORDER_HISTORY_INQUIRY)
        // Casos: "¿he pedido antes aquí?", "mis pedidos anteriores", "pedidos pasados"
        if (preg_match('/\b(he\s+pedido\s+antes|mis\s+pedidos\s+anteriores|pedidos\s+pasados|historial\s+de\s+pedidos)\b/i', $normalized)) {
            return [
                'intent' => 'ORDER_HISTORY_INQUIRY',
                'category' => null,
                'normalized' => $normalized
            ];
        }

        $detectedCat = $this->detectCategory($normalized, $tenantId);

        // 0.5 Protecciones de exclusión estricta contra falsos positivos:
        $hasAction = false;
        $mediaRequestActions = [
            'pasa', 'pasame', 'pasamela', 'paseme', 'pasemela',
            'envia', 'enviame', 'enviamela', 'envie', 'envieme', 'enviemela',
            'manda', 'mandame', 'mandamela', 'mande', 'mandeme', 'mandemela',
            'muestra', 'muestrame', 'muestramela', 'muestre', 'muestreme', 'muestremela',
            'comparte', 'comparteme', 'compartela', 'comparta', 'compartame', 'compartamela',
            'ver', 'foto', 'dame la foto', 'enseña', 'enseñame', 'enseñamela', 'enseñe', 'enseñeme', 'enseñemela',
            'dame', 'deme', 'damela', 'demela'
        ];
        foreach ($mediaRequestActions as $act) {
            if (preg_match('/\b' . preg_quote($act, '/') . '\b/i', $normalized)) {
                $hasAction = true;
                break;
            }
        }

        $isPurePriceQuery = (bool) preg_match('/\b(cuanto cuesta|cuanto esta|cuanto sale|a como|acomo|precio|costo|valor)\b/i', $normalized) && !$hasAction;
        $isPureCatalogInquiry = (bool) preg_match('/\b(que tienen|que hay|que opciones|que platos tienen|cuales son|que me recomiendas|venden|preparan)\b/i', $normalized) && !$hasAction;

        // 1. SOLICITUD DE MEDIO / CARTA VISUAL (REQUEST_MEDIA)
        $menuTerms = ['carta', 'menu', 'catalogo'];
        $hasMenuTerm = false;
        foreach ($menuTerms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $normalized)) {
                $hasMenuTerm = true;
                break;
            }
        }

        $isFormaA = $hasAction && ($detectedCat !== null || $hasMenuTerm || preg_match('/\b(la de|el de|las de|los de|la seccion de|el area de)\b/i', $normalized));
        $isFormaB = (bool) preg_match('/^(?:la|el|las|los)\s+(?:carta\s+|menu\s+|seccion\s+)?(?:de\s+)?([a-z0-9\s]+)$/i', $normalized) && ($detectedCat !== null);

        $isCanonicalCarta = ($hasMenuTerm && ($hasAction || str_contains($normalized, 'quiero') || strlen($normalized) <= 15))
            || preg_match('/\b(si\s+)?(pasamela|pasemela|enviame(la)?|envieme(la)?|mandamela|mandemela|compartela|compartamela)\b/i', $normalized)
            || preg_match('/\b(aquella que tiene|la que tiene|el que tiene|la que viene con|donde estan|la de las?)\s+(.*)\b/i', $normalized)
            || preg_match('/\b(carta\s*4|la\s*4|la\s*numero\s*4|numero\s*4|carta\s*1|la\s*1|la\s*numero\s*1|carta\s*2|la\s*2|la\s*numero\s*2|carta\s*3|la\s*3|la\s*numero\s*3)\b/i', $normalized)
            || preg_match('/\bfoto de (la|el)?\s*(carta|menu)\b/i', $normalized)
            || in_array($normalized, ['la carta', 'el menu', 'carta', 'menu', 'ver carta', 'ver menu', 'pasame la carta', 'enviame la carta', 'quiero la carta']);

        if (($isFormaA || $isFormaB || $isCanonicalCarta) && !$isPurePriceQuery && !$isPureCatalogInquiry) {
            return [
                'intent' => 'REQUEST_MEDIA',
                'category' => $detectedCat,
                'normalized' => $normalized
            ];
        }

        // 2. INTENCIÓN DE COMPRA / PEDIDO (ORDER_INTENT)
        $orderNorm = trim(preg_replace('/^(?:ahora\s+|ya\s+|entonces\s+|bueno\s+|ok\s+|por\s+favor\s+)+/i', '', $normalized));
        $orderVerbs = ['quiero', 'deseo', 'dame', 'deme', 'anotame', 'voy a pedir', 'pedir', 'ordenar', 'pido', 'me da', 'me das', 'me trae', 'me traes', 'sirveme', 'necesito', 'busco'];
        $isOrder = false;
        foreach ($orderVerbs as $ov) {
            if (preg_match('/^' . preg_quote($ov, '/') . '\b/i', $orderNorm) && !str_contains($orderNorm, 'carta') && !str_contains($orderNorm, 'menu') && !str_contains($orderNorm, 'informacion') && !str_contains($orderNorm, 'pendiente') && !str_contains($orderNorm, 'saber')) {
                $isOrder = true;
                break;
            }
        }
        if (!$isOrder && preg_match('/^(?:un|una|unos|unas)\s+[a-z0-9]/i', $orderNorm) && !str_contains($orderNorm, 'carta') && !str_contains($orderNorm, 'menu') && !str_contains($orderNorm, 'foto') && !str_contains($orderNorm, 'pregunta')) {
            $isOrder = true;
        }

        // Casos con números o viñetas
        if (!$isOrder && (preg_match('/^(?:[0-9]+(?:\s*x|\s+de)?|[•\-\*]\s*[0-9]*)\s+[a-z]/i', $orderNorm) || preg_match('/(?:^|\n)\s*[0-9]+\s+[a-z]/i', $messageText)) && !preg_match('/\b(cuanto|precio|costo|sale|a como|carta|menu|foto|link)\b/i', $normalized)) {
            $isOrder = true;
        }

        if ($isOrder) {
            return [
                'intent' => 'ORDER_INTENT',
                'category' => $detectedCat,
                'normalized' => $normalized
            ];
        }

        // 3. CONSULTA DE PRECIO O PLATO ESPECÍFICO (PRODUCT_PRICE_INQUIRY)
        if (preg_match('/\b(cuanto cuesta|cuanto esta|cuanto sale|a como|acomo|precio|costo|valor)\b/i', $normalized)
            || preg_match('/\b(familiar|para dos|wallpa|1\/4|1\/2|entero|personal|bbq|crispy|mostrito|mostro)\b/i', $normalized)
        ) {
            return [
                'intent' => 'PRODUCT_PRICE_INQUIRY',
                'category' => $detectedCat,
                'normalized' => $normalized
            ];
        }

        // 4. CONSULTA DE CATEGORÍA (CATEGORY_QUERY - Nomenclatura Unificada AG-43)
        // Casos: "¿qué tienen en parrillas?", "pastas?", "algo para tomar", "tienen bebidas"
        if ($detectedCat !== null) {
            return [
                'intent' => 'CATEGORY_QUERY',
                'category' => $detectedCat,
                'normalized' => $normalized
            ];
        }

        // 5. CONSULTA GENERAL (GENERAL_QUERY)
        return [
            'intent' => 'GENERAL_QUERY',
            'category' => null,
            'normalized' => $normalized
        ];
    }

    /**
     * Resolver conjunto de intenciones candidatas para ResponseArbitration (P0 a P5)
     */
    public function resolveCandidateActions(int $tenantId, string $conversationId, string $messageText): array
    {
        $textRaw = mb_strtolower(trim($messageText));
        $normalized = $this->normalizeText($textRaw);

        $stateManager = new ConversationStateManager();
        $stateManager->incrementTurn($tenantId, $conversationId);

        // 1. P0: Detección Semántica de Corrección
        $correction = $this->detectCorrection($normalized, $tenantId);

        // 2. P4.1: Disparador de Checkout de Alta Confianza vs Cálculo de Total
        $isCheckout = $this->isHighConfidenceCheckout($normalized);
        $isTotal = $this->isCalculateTotal($normalized);

        // 3. P1: Detección de Confirmación de Promesa Pendiente
        $pendingAction = $stateManager->getValidPendingAction($tenantId, $conversationId);
        $pendingConfirmation = null;
        if ($pendingAction) {
            if ($isCheckout) {
                $stateManager->supersedePendingAction($tenantId, $conversationId, 'CHECKOUT_REQUESTED');
            } else {
                $continuation = $this->resolvePendingActionContinuation($pendingAction, $normalized, $tenantId);
                if ($continuation['status'] === 'CONTINUATION_CONFIRMED') {
                    $pendingConfirmation = [
                        'confirmed' => true,
                        'action' => $pendingAction,
                        'target' => $continuation['target'] ?? ($pendingAction['target'] ?? null),
                    ];
                } elseif ($continuation['status'] === 'SUPERSEDED_WITH_NEW_TARGET') {
                    $stateManager->supersedePendingAction($tenantId, $conversationId, 'NEW_CATEGORY_REQUESTED');
                }
            }
        }

        // 4. P2/P3: Candidato de Medio / Carta Visual
        $mediaCandidate = null;
        $details = $this->resolveIntentDetails($messageText, $tenantId);
        if ($details['intent'] === 'REQUEST_MEDIA') {
            $mediaCandidate = [
                'is_media' => true,
                'category' => $details['category'],
                'target' => $details['category'] ?? $normalized,
            ];
        }

        // 5. P4.2: Candidato de Orden / Compra
        $orderCandidate = null;
        if ($details['intent'] === 'ORDER_INTENT') {
            $orderCandidate = [
                'is_order' => true,
                'raw_text' => $messageText,
                'category' => $details['category'],
            ];
        } else {
            // AG-46-F: Continuación de clarificación técnica (ej. cliente responde "derecho", "2018", etc.)
            $pendingTechClarif = $stateManager->getValidTechnicalClarification($tenantId, $conversationId);
            if ($pendingTechClarif && !$isCheckout && !$isTotal && ($mediaCandidate === null)) {
                $orderCandidate = [
                    'is_order' => true,
                    'raw_text' => $messageText,
                    'category' => null,
                    'is_clarification_continuation' => true,
                    'clarification_context' => $pendingTechClarif,
                ];
            }
        }

        // 6. P4.3: Candidato de Precio (o turno compuesto que incluya consulta de precio)
        $priceCandidate = null;
        if ($details['intent'] === 'PRODUCT_PRICE_INQUIRY' || preg_match('/\b(cuanto|precio|costo|sale|a como)\b/i', $normalized)) {
            $priceCandidate = [
                'is_price' => true,
                'raw_text' => $messageText,
                'category' => $details['category'],
            ];
        }

        // 7. P4.4: Candidato de Categoría (AG-43: Nomenclatura Unificada CATEGORY_QUERY)
        $categoryCandidate = null;
        if (($details['intent'] === 'CATEGORY_QUERY' || $details['intent'] === 'CATEGORY_INQUIRY') && !empty($details['category'])) {
            if (!$correction || !in_array($details['category'], $correction['rejected_concepts'] ?? [])) {
                $categoryCandidate = [
                    'category' => $details['category'],
                ];
            }
        }

        // AG-39: Candidatos de Estado de Orden, Carrito y Reseteo
        $orderStatusCandidate = ($details['intent'] === 'ORDER_STATUS_INQUIRY') ? ['is_order_status' => true] : null;
        $cartStatusCandidate = ($details['intent'] === 'CART_STATUS_INQUIRY') ? ['is_cart_status' => true] : null;
        $orderHistoryCandidate = ($details['intent'] === 'ORDER_HISTORY_INQUIRY') ? ['is_order_history' => true] : null;
        $cartResetCandidate = ($details['intent'] === 'CART_RESET_INTENT') ? ['is_cart_reset' => true] : null;

        // AG-39 & AG-41: Intent Authority & Context Non-Resurrection
        // Si hay una intención explícita de orden, carrito o consulta general, invalidar targets antiguos
        if ($orderStatusCandidate || $cartStatusCandidate || $cartResetCandidate || $orderHistoryCandidate) {
            $stateManager->invalidateVisualTarget($tenantId, $conversationId, 'NEW_EXPLICIT_INTENT');
            if ($pendingAction) {
                $stateManager->supersedePendingAction($tenantId, $conversationId, 'NEW_EXPLICIT_INTENT');
            }
        } elseif ($pendingAction && !$pendingConfirmation && ($isCheckout || $orderCandidate || ($priceCandidate && !$mediaCandidate))) {
            $stateManager->supersedePendingAction($tenantId, $conversationId, 'INCOMPATIBLE_USER_INTENT');
        }

        return [
            'correction' => $correction,
            'pending_confirmation' => $pendingConfirmation,
            'checkout_candidate' => $isCheckout ? ['is_checkout' => true] : null,
            'total_candidate' => $isTotal ? ['is_total' => true] : null,
            'order_status_candidate' => $orderStatusCandidate,
            'cart_status_candidate' => $cartStatusCandidate,
            'order_history_candidate' => $orderHistoryCandidate,
            'cart_reset_candidate' => $cartResetCandidate,
            'media_candidate' => $mediaCandidate,
            'order_candidate' => $orderCandidate,
            'price_candidate' => $priceCandidate,
            'category_candidate' => $categoryCandidate,
            'intent_details' => $details,
        ];
    }

    /**
     * Detección Semántica Estructural de Correcciones (P0) con Estructura Sintáctica Negation + Replacement
     */
    public function detectCorrection(string $normalized, ?int $tenantId = null): ?array
    {
        $isCorrection = false;
        $rejectedConcepts = [];
        $replacementTarget = null;
        $correctionScope = 'GENERAL';

        // 1. Inmunidad contra preguntas que contengan "no": ej. "¿no tienen parrillas?", "¿no venden pastas?"
        if (preg_match('/^(?:no\s+(?:tienen|venden|hay|preparan)|acaso\s+no)\b/i', $normalized)) {
            return null;
        }

        // Inmunidad contra directivas de cambio de tema o consultas: ej. "no quiero saber...", "no dime si..."
        if (preg_match('/\b(?:saber|consultar|preguntar|dime|decirme|informarme|conocer)\b/i', $normalized) || preg_match('/\b(?:pedido|orden|cuenta|total)\b/i', $normalized)) {
            return null;
        }

        // 2. Patrón de Rechazo Léxico ("no dije [X]", "no era [X]", "no me referia a [X]", "no pedi [X]", "no quiero [X]")
        if (preg_match('/^(?:no\s+(?:dije|era|quise\s+decir|queria|quiero|pedi|hablaba\s+de)|me\s+referia\s+a)\s+(.*)/i', $normalized, $matches)) {
            $isCorrection = true;
            $tail = trim($matches[1] ?? '');

            // ¿Contiene reemplazo explícito? ej. "no dije parrillas, sino pastas" o "no era parrillas, pásame las alitas"
            if (preg_match('/^(.*?)\s*(?:,\s*|\s+)(?:sino|mejor|queria|quiero|pasame|mandame|muestrame|la\s+de|el\s+de|las\s+de|era)\s+(.*)$/i', $tail, $splitMatches)) {
                $rejPart = trim($splitMatches[1]);
                $repPart = trim($splitMatches[2]);
                $catRej = $this->detectCategory($rejPart, $tenantId);
                if ($catRej) $rejectedConcepts[] = $catRej;
                $catRep = $this->detectCategory($repPart, $tenantId);
                if ($catRep) {
                    $replacementTarget = $catRep;
                    $correctionScope = 'MEDIA';
                }
            } else {
                // Solo rechazo sin reemplazo: ej. "no dije parrillas"
                $cat = $this->detectCategory($tail, $tenantId);
                if ($cat) {
                    $rejectedConcepts[] = $cat;
                }
            }
        }
        // 3. Patrón de Descarte con Reemplazo Positivo Directo ("esta no, la de parrillas", "no, mejor la de parrillas", "esa no, la de alitas", "no quiero esa, mándame la de parrillas")
        elseif (preg_match('/^(?:esta|esa|este|ese)?\s*no\b(?:\s*,)?\s*(?:quiero\s+esa\s*,?\s*|gracias\s*,?\s*)?(?:mejor\s+|pasame\s+|mandame\s+|muestrame\s+|la\s+de\s+|el\s+de\s+|las\s+de\s+|carta\s+de\s+)(.*)/i', $normalized, $matches)) {
            $tail = trim($matches[1] ?? '');
            $repCat = $this->detectCategory($tail, $tenantId);
            if ($repCat) {
                $isCorrection = true;
                $replacementTarget = $repCat;
                $correctionScope = 'MEDIA';
            }
        }
        // 4. Patrón coloquial directo ("está no la de parrillas")
        elseif (preg_match('/^(?:esta|esa)\s+no\s+(?:la\s+de\s+)?(.*)/i', $normalized, $matches)) {
            $tail = trim($matches[1] ?? '');
            $repCat = $this->detectCategory($tail, $tenantId);
            if ($repCat) {
                $isCorrection = true;
                $replacementTarget = $repCat;
                $correctionScope = 'MEDIA';
            }
        }
        // 5. Rechazo pospuesto: "parrillas no"
        elseif (preg_match('/\b(?:parrillas|pastas|alitas|chanchos|pollos)\s+no\b/i', $normalized)) {
            $isCorrection = true;
            $cat = $this->detectCategory($normalized, $tenantId);
            if ($cat) {
                $rejectedConcepts[] = $cat;
            }
        }

        if ($isCorrection) {
            return [
                'is_correction' => true,
                'rejected_concepts' => array_unique($rejectedConcepts),
                'replacement_target' => $replacementTarget,
                'confidence' => 1.0,
                'correction_scope' => $correctionScope,
            ];
        }

        return null;
    }

    public function resolvePendingActionContinuation(array $pendingAction, string $normalized, ?int $tenantId = null): array
    {
        $pendingTarget = $pendingAction['target'] ?? null;
        $pendingType = $pendingAction['type'] ?? '';
        $detectedInMsg = $this->detectCategory($normalized, $tenantId);

        // Si la acción pendiente es MEDIA_DISPATCH
        if ($pendingType === 'MEDIA_DISPATCH') {
            // Si el usuario confirma la acción pendiente (ej. "sí porfa", "sí, pero cuánto cuesta X")
            if ($this->isMediaContinuationUtterance($normalized)) {
                return [
                    'status' => 'CONTINUATION_CONFIRMED',
                    'target' => $pendingTarget,
                    'action' => $pendingAction,
                ];
            }

            // Si el usuario pide explícitamente una categoría distinta a la prometida (ej. "pásame la carta de alitas")
            if ($detectedInMsg && $pendingTarget && $detectedInMsg !== $pendingTarget) {
                return [
                    'status' => 'SUPERSEDED_WITH_NEW_TARGET',
                    'new_target' => $detectedInMsg,
                    'previous_target' => $pendingTarget,
                ];
            }
        }

        return ['status' => 'NOT_A_CONTINUATION'];
    }

    public function isMediaContinuationUtterance(string $normalized): bool
    {
        $trimmed = trim($normalized);

        // 1. Afirmaciones directas
        if (in_array($trimmed, [
            'si', 'si porfa', 'si por favor', 'claro', 'dale', 'porfa', 'si claro', 'por favor',
            'bueno', 'ok', 'ya', 'listo', 'de acuerdo', 'porfa si', 'si dale', 'si gracias'
        ]) || preg_match('/^si\b/iu', $trimmed)) {
            return true;
        }

        // 2. Solicitudes directas o anafóricas de visualización/envío de la carta
        if (preg_match('/^(?:si\s+)?(?:quiero\s+)?(?:ver|verla|revisar|mirar)(?:\s+(?:la|el|esa|dicha|su)?\s*(?:carta|menu))?(?:\s*(?:porfa|por\s*favor))?$/iu', $trimmed)) {
            return true;
        }
        if (preg_match('/^(?:si\s+)?(?:pasame|paseme|mandame|mandeme|enviame|envieme|comparteme|compartame|pasa|pase|manda|mande|envia|envie|comparte|comparta|ver|mostrar|muestre|dame|deme|enseña|enseñe)\s*(?:me)?\s*(?:la|el|esa|dicha|nuestra|su)?\s*(?:carta|menu|foto)(?:\s*(?:porfa|por\s*favor|pe))?$/iu', $trimmed)) {
            return true;
        }
        if (preg_match('/^(?:si\s+)?(?:pasamela|pasemela|mandamela|mandemela|enviamela|enviemela|compartela|compartamela|muestramela|muestremela|enseñamela|enseñemela|damela|demela|mandala|mandela|enviala|enviela|pasala|pasela)(?:\s*(?:porfa|por\s*favor|pe))?$/iu', $trimmed)) {
            return true;
        }

        return false;
    }

    public function isCalculateTotal(string $normalized): bool
    {
        return preg_match('/\b(dame el total|pasame el total|cuanto seria todo|cuanto va|cuanto llevo|cual es el total|cuanto es el total|cuanto es en total|total del pedido|subtotal|que tengo en el carrito|que llevo|mi cuenta)\b/i', $normalized) === 1;
    }

    public function isHighConfidenceCheckout(string $normalized): bool
    {
        return preg_match('/\b(eso seria todo|ya no quiero nada mas|eso es todo|quiero pagar|quiero cerrar el pedido|cerrar pedido|voy a pagar|donde pago|como pago|cobrame|terminar pedido|listo para pagar)\b/i', $normalized) === 1;
    }

    private static array $tenantCategoryCache = [];

    /**
     * Detección de Categoría Comercial desacoplada dinámicamente mediante Memoria Semántica del Tenant.
     */
    public function detectCategory(string $normalized, ?int $tenantId = null): ?string
    {
        // 1. Consulta dinámica a conceptos semánticos y categorías del Tenant si está provisto
        if ($tenantId !== null) {
            $tenantCategories = $this->getTenantEligibleCategories($tenantId);
            foreach ($tenantCategories as $catKey => $aliases) {
                foreach ($aliases as $alias) {
                    if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $normalized)) {
                        return $catKey;
                    }
                }
            }
        }

        // 2. Base de conceptos canónicos (fixtures/benchmark para pruebas y default de la industria)
        $canonicalMap = [
            'PARRILLAS' => ['parrilla', 'parrillas', 'anticucho', 'anticuchos', 'bife', 'chuleta', 'churrasco'],
            'PASTAS' => ['pasta', 'pastas', 'fetuccini', 'fettuccine', 'tallarin', 'tallarines', 'huancaina'],
            'SALCHIPAPAS' => ['salchipapa', 'salchipapas', 'salchimix'],
            'ALITAS' => ['alita', 'alitas', 'broaster', 'crispy', 'bbq'],
            'CHANCHOS' => ['chancho', 'chanchos', 'cilindro'],
            'POLLOS' => ['pollo', 'pollos', 'mostrito', 'mostro', 'brasa'],
            'GUARNICIONES' => ['guarnicion', 'guarniciones', 'papas andinas', 'ensalada', 'platano', 'chaufa', 'arroz chaufa'],
            'MARISCOS' => ['marisco', 'mariscos', 'ceviche', 'ceviches', 'tiradito', 'tiraditos', 'maki', 'makis'],
            'PIZZAS' => ['pizza', 'pizzas'],
            'HAMBURGUESAS' => ['hamburguesa', 'hamburguesas', 'burger'],
            'BEBIDAS' => ['bebida', 'bebidas', 'gaseosa', 'gaseosas', 'chicha', 'limonada', 'refresco', 'tomar', 'beber', 'trago', 'tragos', 'cerveza', 'cervezas', 'jugo', 'jugos'],
            'POSTRES' => ['postre', 'postres', 'torta', 'helado']
        ];

        foreach ($canonicalMap as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $normalized)) {
                    return $cat;
                }
            }
        }

        return null;
    }

    private function getTenantEligibleCategories(int $tenantId): array
    {
        if (isset(self::$tenantCategoryCache[$tenantId])) {
            return self::$tenantCategoryCache[$tenantId];
        }

        $categories = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('media_assets')) {
                $assets = \App\Modules\Business\Models\MediaAsset::where('tenant_id', $tenantId)
                    ->where('status', 'ACTIVE')
                    ->get(['category', 'title']);
                foreach ($assets as $a) {
                    if (!empty($a->category)) {
                        $key = strtoupper(trim($a->category));
                        $categories[$key][] = strtolower(trim($a->category));
                        if (!empty($a->title)) {
                            $cleanTitle = strtolower(trim(preg_replace('/[¿?¡!.,;:]+/', ' ', $a->title)));
                            $categories[$key][] = $cleanTitle;
                        }
                    }
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('categories')) {
                $dbCats = \Illuminate\Support\Facades\DB::table('categories')
                    ->where('tenant_id', $tenantId)
                    ->pluck('name');
                foreach ($dbCats as $name) {
                    $key = strtoupper(\Illuminate\Support\Str::slug($name, '_'));
                    $categories[$key][] = strtolower(trim($name));
                }
            }
        } catch (\Throwable $e) {}

        self::$tenantCategoryCache[$tenantId] = $categories;
        return $categories;
    }

    private function normalizeText(string $text): string
    {
        $norm = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n', 'ü' => 'u'
        ]);
        $norm = trim(preg_replace('/[¿?¡!.,;:]+/', ' ', $norm));
        $norm = preg_replace('/\s+/', ' ', $norm);
        return preg_replace('/([a-z])\1{2,}/i', '$1', $norm);
    }
}


