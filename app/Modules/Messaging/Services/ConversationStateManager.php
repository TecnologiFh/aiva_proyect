<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ConversationStateManager
{
    private const TTL_SECONDS = 86400; // 24 horas retención en caché
    public const SESSION_INACTIVITY_TTL = 14400; // 4 horas de frontera de sesión comercial (AG-44)
    public const VISUAL_TARGET_TTL_SECONDS = 600; // 10 minutos para objetivo visual (AG-40)
    public const VISUAL_TARGET_MAX_TURNS = 2; // Máximo 2 turnos para objetivo visual (AG-40)

    private function getCacheKey(int $tenantId, string $conversationId): string
    {
        return "aiva_conv_state_{$tenantId}_{$conversationId}";
    }

    public function getState(int $tenantId, string $conversationId): array
    {
        $state = Cache::get($this->getCacheKey($tenantId, $conversationId), null);
        $now = time();

        if ($state === null) {
            $state = [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'session_id' => (string) Str::uuid(),
                'session_started_at' => $now,
                'last_activity_at' => $now,
                'turn_count' => 0,
                'pending_action' => null,
                'cart' => [
                    'items' => [],
                    'subtotal' => 0.0,
                    'state' => 'EMPTY',
                ],
                'last_bot_intent' => null,
                'last_customer_intent' => null,
                'correction_context' => null,
                'previous_visual_target' => null,
                'previous_visual_target_turn' => 0,
                'previous_visual_target_at' => 0,
                'previous_visual_target_status' => 'NONE', // 'ACTIVE' | 'EXPIRED' | 'INVALIDATED'
            ];
            $this->saveState($tenantId, $conversationId, $state);
            return $state;
        }

        // AG-44: Session Boundary Check (Inactividad > 4 horas)
        $lastActivity = $state['last_activity_at'] ?? $now;
        if (($now - $lastActivity) > self::SESSION_INACTIVITY_TTL) {
            // Se cierra la sesión comercial previa sin borrar órdenes físicas de la BD
            $state['session_id'] = (string) Str::uuid();
            $state['session_started_at'] = $now;
            $state['turn_count'] = 0;
            $state['pending_action'] = null;
            $state['cart'] = [
                'items' => [],
                'subtotal' => 0.0,
                'state' => 'EMPTY',
            ];
            $state['previous_visual_target'] = null;
            $state['previous_visual_target_status'] = 'INVALIDATED';
            $state['correction_context'] = null;
        }

        $state['last_activity_at'] = $now;
        return $state;
    }

    public function saveState(int $tenantId, string $conversationId, array $state): void
    {
        $state['last_activity_at'] = time();
        Cache::put($this->getCacheKey($tenantId, $conversationId), $state, self::TTL_SECONDS);
    }

    public function incrementTurn(int $tenantId, string $conversationId): int
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['turn_count'] = ($state['turn_count'] ?? 0) + 1;
        $now = time();

        // Comprobar expiración por turnos y tiempo de pending_action
        if (!empty($state['pending_action']) && ($state['pending_action']['status'] ?? '') === 'PENDING') {
            $state['pending_action']['turns_lived'] = ($state['pending_action']['turns_lived'] ?? 0) + 1;
            if ($state['pending_action']['turns_lived'] > ($state['pending_action']['expires_after_turns'] ?? self::VISUAL_TARGET_MAX_TURNS)) {
                $state['pending_action']['status'] = 'EXPIRED';
            }
            if ($now > ($state['pending_action']['expires_at'] ?? 0)) {
                $state['pending_action']['status'] = 'EXPIRED';
            }
        }

        // AG-40: Comprobar caducidad de previous_visual_target
        if (!empty($state['previous_visual_target']) && ($state['previous_visual_target_status'] ?? '') === 'ACTIVE') {
            $turnDist = $state['turn_count'] - ($state['previous_visual_target_turn'] ?? 0);
            $timeAge = $now - ($state['previous_visual_target_at'] ?? 0);
            if ($turnDist > self::VISUAL_TARGET_MAX_TURNS || $timeAge > self::VISUAL_TARGET_TTL_SECONDS) {
                $state['previous_visual_target_status'] = 'EXPIRED';
            }
        }

        $this->saveState($tenantId, $conversationId, $state);
        return $state['turn_count'];
    }

    public function getConversationTurn(int $tenantId, string $conversationId): int
    {
        $state = $this->getState($tenantId, $conversationId);
        return $state['turn_count'] ?? 0;
    }

    /**
     * Registrar una promesa o acción pendiente del bot con linaje auditable
     */
    public function setPendingAction(
        int $tenantId,
        string $conversationId,
        string $type,
        string $target,
        array $payload = [],
        ?string $sourceTurnId = null,
        ?string $sourceIntent = null,
        int $expiresInSeconds = 900,
        int $expiresAfterTurns = 2
    ): array {
        $state = $this->getState($tenantId, $conversationId);

        $action = [
            'id' => (string) Str::uuid(),
            'type' => $type, // ej. 'MEDIA_DISPATCH'
            'target' => $target, // ej. 'PASTAS'
            'payload' => $payload,
            'source_turn_id' => $sourceTurnId ?? ($state['turn_count'] ?? 1),
            'source_intent' => $sourceIntent ?? 'CATEGORY_INQUIRY',
            'created_at' => time(),
            'expires_at' => time() + $expiresInSeconds,
            'expires_after_turns' => $expiresAfterTurns,
            'turns_lived' => 0,
            'status' => 'PENDING',
            'superseded_by' => null,
        ];

        $state['pending_action'] = $action;
        $state['previous_visual_target'] = $target;
        $this->saveState($tenantId, $conversationId, $state);

        return $action;
    }

    /**
     * Obtener la acción pendiente activa si aún es válida
     */
    public function getValidPendingAction(int $tenantId, string $conversationId): ?array
    {
        $state = $this->getState($tenantId, $conversationId);
        $action = $state['pending_action'] ?? null;

        if (!$action) {
            return null;
        }

        if ($action['status'] !== 'PENDING') {
            return null;
        }

        if (time() > ($action['expires_at'] ?? 0)) {
            $action['status'] = 'EXPIRED';
            $state['pending_action'] = $action;
            $this->saveState($tenantId, $conversationId, $state);
            return null;
        }

        if (($action['turns_lived'] ?? 0) > ($action['expires_after_turns'] ?? 2)) {
            $action['status'] = 'EXPIRED';
            $state['pending_action'] = $action;
            $this->saveState($tenantId, $conversationId, $state);
            return null;
        }

        return $action;
    }

    /**
     * Invalidar acción pendiente por cambio de tema o intención incompatible
     */
    public function supersedePendingAction(int $tenantId, string $conversationId, string $reason): void
    {
        $state = $this->getState($tenantId, $conversationId);
        if (!empty($state['pending_action']) && $state['pending_action']['status'] === 'PENDING') {
            $state['pending_action']['status'] = 'SUPERSEDED';
            $state['pending_action']['superseded_by'] = $reason;
            $this->saveState($tenantId, $conversationId, $state);
        }
    }

    /**
     * Reclamar atómicamente la acción pendiente para ejecución concurrente segura
     * Transición de estado: PENDING -> EXECUTING
     * Retorna el payload de la acción si se reclamó con éxito, o null si ya fue tomada/ejecutada
     */
    public function claimPendingActionForExecution(int $tenantId, string $conversationId): ?array
    {
        $lockKey = "lock_conv_state_{$tenantId}_{$conversationId}";
        $lock = Cache::lock($lockKey, 5);

        try {
            // Intentar adquirir el lock (espera hasta 2 segundos)
            $acquired = $lock->block(2);
            if (!$acquired) {
                return null;
            }

            $state = $this->getState($tenantId, $conversationId);
            $action = $state['pending_action'] ?? null;

            if (!$action || ($action['status'] ?? '') !== 'PENDING') {
                return null;
            }

            // Transición atómica a EXECUTING
            $action['status'] = 'EXECUTING';
            $action['executing_since'] = time();
            $state['pending_action'] = $action;
            $this->saveState($tenantId, $conversationId, $state);

            return $action;
        } catch (\Throwable $e) {
            return null;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Confirmar y marcar como exitosamente ejecutada la acción pendiente
     * Transición: EXECUTING (o PENDING) -> EXECUTED
     */
    public function markPendingActionExecuted(int $tenantId, string $conversationId): void
    {
        $lockKey = "lock_conv_state_{$tenantId}_{$conversationId}";
        $lock = Cache::lock($lockKey, 5);

        try {
            optional($lock)->block(2);
            $state = $this->getState($tenantId, $conversationId);
            if (!empty($state['pending_action'])) {
                $state['pending_action']['status'] = 'EXECUTED';
                $state['pending_action']['executed_at'] = time();
                $this->saveState($tenantId, $conversationId, $state);
            }
        } catch (\Throwable $e) {
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Registrar fallo de ejecución si WhatsApp o la red fallan
     * Transición: EXECUTING -> EXECUTION_FAILED (con bandera retryable)
     */
    public function markPendingActionFailed(int $tenantId, string $conversationId, string $errorMessage, bool $retryable = true): void
    {
        $lockKey = "lock_conv_state_{$tenantId}_{$conversationId}";
        $lock = Cache::lock($lockKey, 5);

        try {
            optional($lock)->block(2);
            $state = $this->getState($tenantId, $conversationId);
            if (!empty($state['pending_action'])) {
                $state['pending_action']['status'] = 'EXECUTION_FAILED';
                $state['pending_action']['error_message'] = $errorMessage;
                $state['pending_action']['retryable'] = $retryable;
                $state['pending_action']['failed_at'] = time();
                $this->saveState($tenantId, $conversationId, $state);
            }
        } catch (\Throwable $e) {
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Manejo de Carrito Continuo
     */
    public function addItemToCart(int $tenantId, string $conversationId, ?int $productId, string $name, float $price, int $quantity = 1): array
    {
        $lockKey = "lock_conv_state_{$tenantId}_{$conversationId}";
        $lock = Cache::lock($lockKey, 5);
        $acquired = false;

        try {
            $acquired = $lock->block(2);
            $state = $this->getState($tenantId, $conversationId);
            $items = $state['cart']['items'] ?? [];

            $found = false;
            foreach ($items as &$it) {
                $isSame = ($productId !== null && isset($it['product_id']) && $it['product_id'] !== null)
                    ? ((int)$it['product_id'] === (int)$productId)
                    : ($it['name'] === $name);

                if ($isSame) {
                    $it['quantity'] += $quantity;
                    $it['subtotal'] = round($it['quantity'] * $it['unit_price'], 2);
                    $found = true;
                    break;
                }
            }
            unset($it);

            if (!$found) {
                $items[] = [
                    'product_id' => $productId,
                    'name' => $name,
                    'unit_price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => round($price * $quantity, 2),
                ];
            }

            $subtotal = 0.0;
            foreach ($items as $it) {
                $subtotal += $it['subtotal'];
            }

            $state['cart']['items'] = $items;
            $state['cart']['subtotal'] = round($subtotal, 2);
            $state['cart']['state'] = 'OPEN'; // Inmunidad conversacional activa

            $this->saveState($tenantId, $conversationId, $state);
            return $state['cart'];
        } finally {
            if ($acquired) {
                optional($lock)->release();
            }
        }
    }

    public function setCartState(int $tenantId, string $conversationId, string $cartState): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['cart']['state'] = $cartState;
        $this->saveState($tenantId, $conversationId, $state);
    }

    public function getCart(int $tenantId, string $conversationId): array
    {
        $state = $this->getState($tenantId, $conversationId);
        $cart = $state['cart'] ?? [
            'items' => [],
            'subtotal' => 0.0,
            'state' => 'EMPTY',
            'cart_state' => 'EMPTY',
        ];
        if (isset($cart['state']) && !isset($cart['cart_state'])) {
            $cart['cart_state'] = $cart['state'];
        }
        return $cart;
    }

    public function clearCart(int $tenantId, string $conversationId): void
    {
        $this->clearActiveCart($tenantId, $conversationId);
    }

    /**
     * Vaciar el carrito de la sesión conversacional activa sin tocar órdenes en BD (AG-44)
     */
    public function clearActiveCart(int $tenantId, string $conversationId): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['cart'] = [
            'items' => [],
            'subtotal' => 0.0,
            'state' => 'EMPTY',
            'cart_state' => 'EMPTY',
        ];
        $this->saveState($tenantId, $conversationId, $state);
    }

    /**
     * Manejo de Correcciones Semánticas
     */
    public function recordCorrection(int $tenantId, string $conversationId, array $rejectedConcepts, ?string $replacementTarget = null): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['correction_context'] = [
            'rejected_concepts' => $rejectedConcepts,
            'replacement_target' => $replacementTarget,
            'recorded_turn' => $state['turn_count'] ?? 1,
            'timestamp' => time(),
        ];
        if (!empty($replacementTarget)) {
            $this->setPreviousVisualTarget($tenantId, $conversationId, $replacementTarget);
        }
        $this->saveState($tenantId, $conversationId, $state);
    }

    public function getCorrectionContext(int $tenantId, string $conversationId): ?array
    {
        $state = $this->getState($tenantId, $conversationId);
        return $state['correction_context'] ?? null;
    }

    public function clearCorrectionContext(int $tenantId, string $conversationId): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['correction_context'] = null;
        $this->saveState($tenantId, $conversationId, $state);
    }

    /**
     * AG-40: Obtener el objetivo visual previo ÚNICAMENTE si cumple TTL (<= 2 turnos y <= 10 min)
     * y no ha sido marcado como INVALIDATED (AG-41: Context Non-Resurrection)
     */
    public function getValidVisualTarget(int $tenantId, string $conversationId): ?string
    {
        $state = $this->getState($tenantId, $conversationId);
        $target = $state['previous_visual_target'] ?? null;
        $status = $state['previous_visual_target_status'] ?? 'NONE';

        if (empty($target) || $status !== 'ACTIVE') {
            return null;
        }

        $now = time();
        $turnDist = ($state['turn_count'] ?? 0) - ($state['previous_visual_target_turn'] ?? 0);
        $timeAge = $now - ($state['previous_visual_target_at'] ?? 0);

        if ($turnDist > self::VISUAL_TARGET_MAX_TURNS || $timeAge > self::VISUAL_TARGET_TTL_SECONDS) {
            $state['previous_visual_target_status'] = 'EXPIRED';
            $this->saveState($tenantId, $conversationId, $state);
            return null;
        }

        return $target;
    }

    public function getPreviousVisualTarget(int $tenantId, string $conversationId): ?string
    {
        return $this->getValidVisualTarget($tenantId, $conversationId);
    }

    public function setPreviousVisualTarget(int $tenantId, string $conversationId, string $target): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['previous_visual_target'] = $target;
        $state['previous_visual_target_turn'] = $state['turn_count'] ?? 1;
        $state['previous_visual_target_at'] = time();
        $state['previous_visual_target_status'] = 'ACTIVE';
        $this->saveState($tenantId, $conversationId, $state);
    }

    /**
     * AG-41: Context Non-Resurrection. Invalida formalmente el objetivo visual
     * para que no pueda resucitar en turnos posteriores.
     */
    public function invalidateVisualTarget(int $tenantId, string $conversationId, string $reason = 'INVALIDATED'): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['previous_visual_target'] = null;
        $state['previous_visual_target_status'] = 'INVALIDATED';
        $state['previous_visual_target_invalidated_reason'] = $reason;
        $state['previous_visual_target_invalidated_at'] = time();
        $this->saveState($tenantId, $conversationId, $state);
    }

    /**
     * P3.14-02 & P3.14-04: Gestión formal de Technical Clarification State con candidate_scope.
     * Almacena contexto, entidades y alcance de candidatos cuando la resolución es AMBIGUOUS o UNCONFIRMED_COMPATIBILITY.
     */
    public function setPendingTechnicalClarification(int $tenantId, string $conversationId, array $clarificationData): array
    {
        $state = $this->getState($tenantId, $conversationId);
        $now = time();
        $sourceTurn = $state['turn_count'] ?? 1;

        $clarificationState = [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'intent' => $clarificationData['intent'] ?? ($clarificationData['intent_family'] ?? 'TECHNICAL_INQUIRY'),
            'intent_family' => $clarificationData['intent_family'] ?? ($clarificationData['intent'] ?? 'TECHNICAL_INQUIRY'),
            'entity_type' => $clarificationData['entity_type'] ?? 'PRODUCT',
            'status' => $clarificationData['status'] ?? 'AMBIGUOUS',
            'entities' => $clarificationData['entities'] ?? [],
            'missing_attributes' => $clarificationData['missing_attributes'] ?? [],
            'candidate_scope' => $clarificationData['candidate_scope'] ?? ($clarificationData['candidates'] ?? []),
            'candidates' => $clarificationData['candidates'] ?? [],
            'ambiguity_reason' => $clarificationData['ambiguity_reason'] ?? null,
            'dominant_clarification_attribute' => $clarificationData['dominant_clarification_attribute'] ?? $this->determineDominantAttribute($clarificationData),
            'disambiguation_prompt' => $clarificationData['disambiguation_prompt'] ?? null,
            'source_turn' => $sourceTurn,
            'turn' => $sourceTurn,
            'created_at' => $now,
            'expires_at' => $now + self::VISUAL_TARGET_TTL_SECONDS,
        ];

        $state['pending_technical_clarification'] = $clarificationState;
        $this->saveState($tenantId, $conversationId, $state);

        return $clarificationState;
    }

    /**
     * P3.14-04: Determina el atributo dominante de aclaración según la regla de prioridad:
     * 1. Atributo que reduzca más el espacio de candidatos
     * 2. Atributo obligatorio para resolver
     * 3. Atributo preferencial
     */
    public function determineDominantAttribute(array $data): string
    {
        $reason = strtoupper((string)($data['ambiguity_reason'] ?? ''));
        if (str_contains($reason, 'SIDE') || str_contains($reason, 'POSITION') || str_contains($reason, 'PIECE')) {
            return 'piece';
        }
        if (str_contains($reason, 'YEAR') || str_contains($reason, 'ANIO')) {
            return 'year';
        }
        if (str_contains($reason, 'MODEL')) {
            return 'model';
        }
        if (str_contains($reason, 'COLOR')) {
            return 'color';
        }
        return 'piece';
    }

    /**
     * P3.14-03 & P3.14-06: Obtiene la clarificación técnica pendiente si no ha expirado.
     * Regla de Expiración Estricta: turns_since_source > 2 OR now > expires_at.
     * Regla de Límite de Contexto (P3.14-CTX-01): Valida compatibilidad con el intent actual.
     */
    public function getValidTechnicalClarification(
        int $tenantId,
        string $conversationId,
        ?string $currentIntent = null,
        ?string $currentDomain = null
    ): ?array {
        $state = $this->getState($tenantId, $conversationId);
        $clarif = $state['pending_technical_clarification'] ?? null;
        if (empty($clarif)) {
            return null;
        }

        // P3.14-07: Verificación estricta de aislamiento multi-tenant
        if ((int)($clarif['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }

        $now = time();
        $sourceTurn = $clarif['source_turn'] ?? ($clarif['turn'] ?? 0);
        $turnDist = ($state['turn_count'] ?? 0) - $sourceTurn;
        $expiresAt = $clarif['expires_at'] ?? (($clarif['created_at'] ?? 0) + self::VISUAL_TARGET_TTL_SECONDS);

        // P3.14-06: El primero que ocurra invalida el estado (OR lógico estricto)
        if ($turnDist > self::VISUAL_TARGET_MAX_TURNS || $now > $expiresAt) {
            $this->clearPendingTechnicalClarification($tenantId, $conversationId);
            return null;
        }

        // P3.14-03 (P3.14-CTX-01): Límite de contexto por intención
        if ($currentIntent !== null) {
            $incompatibleIntents = [
                'LOCATION_INQUIRY',
                'HOURS_INQUIRY',
                'PAYMENT_METHODS_INQUIRY',
                'GREETING',
                'CART_RESET_INTENT',
            ];
            if (in_array($currentIntent, $incompatibleIntents, true)) {
                return null;
            }
        }

        // P3.14-03: Cambio de dominio semántico (ej. preguntar por aceite cuando se cotizaban frenos)
        if ($currentDomain !== null && !empty($clarif['entity_type'])) {
            if (strtoupper($currentDomain) !== strtoupper($clarif['entity_type'])) {
                // Incompatible: no heredar candidate_scope ni atributos de posición
                return null;
            }
        }

        return $clarif;
    }

    /**
     * P3.14-05: Contradiction Replacement.
     * Sustituye atributos modificados por el cliente en el turno actual o resetea dependencias ante cambio de entidad raíz.
     */
    public function applyContradictionReplacement(array $existingEntities, array $newEntities): array
    {
        $merged = $existingEntities;

        $existingBrand = $existingEntities['brand'] ?? null;
        $newBrand = $newEntities['brand'] ?? null;
        $existingModel = $existingEntities['model'] ?? null;
        $newModel = $newEntities['model'] ?? null;

        // 1. Detección de reemplazo de Entidad Raíz (Marca o Modelo cambió)
        $brandChanged = !empty($newBrand) && !empty($existingBrand) && strcasecmp($newBrand, $existingBrand) !== 0;
        $modelChanged = !empty($newModel) && !empty($existingModel) && strcasecmp($newModel, $existingModel) !== 0;

        if ($brandChanged || $modelChanged) {
            // Cambio de entidad raíz: se limpian todos los atributos dependientes (año, lado, color, custom specs)
            return [
                'brand' => $newBrand ?? $existingBrand,
                'model' => $newModel,
                'year' => $newEntities['year'] ?? null,
                'side' => $newEntities['side'] ?? null,
                'presentation' => $newEntities['presentation'] ?? null,
                'color' => $newEntities['color'] ?? null,
                'custom_specs' => $newEntities['custom_specs'] ?? [],
                'contradiction_type' => 'ROOT_ENTITY_REPLACEMENT',
            ];
        }

        // 2. Reemplazo atómico de atributo (mismo modelo, año o lado corregido)
        if (!empty($newEntities['year'])) {
            $merged['year'] = $newEntities['year'];
            $merged['contradiction_type'] = 'ATTRIBUTE_REPLACEMENT';
        }
        if (!empty($newEntities['side'])) {
            $merged['side'] = $newEntities['side'];
        }
        if (!empty($newEntities['presentation'])) {
            $merged['presentation'] = $newEntities['presentation'];
        }
        if (!empty($newEntities['color'])) {
            $merged['color'] = $newEntities['color'];
        }
        if (!empty($newEntities['custom_specs'])) {
            $merged['custom_specs'] = array_merge($merged['custom_specs'] ?? [], $newEntities['custom_specs']);
        }

        return $merged;
    }

    /**
     * AG-46-F: Limpia la clarificación técnica activa tras resolverse o invalidarse.
     */
    public function clearPendingTechnicalClarification(int $tenantId, string $conversationId): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['pending_technical_clarification'] = null;
        $this->saveState($tenantId, $conversationId, $state);
    }

    /**
     * AG-46-F: Almacena el último producto técnico resuelto para auditoría y linaje.
     */
    public function setLastResolvedTechnicalProduct(int $tenantId, string $conversationId, array $productData): void
    {
        $state = $this->getState($tenantId, $conversationId);
        $state['last_resolved_technical_product'] = $productData;
        $this->saveState($tenantId, $conversationId, $state);
    }

    public function getLastResolvedTechnicalProduct(int $tenantId, string $conversationId): ?array
    {
        $state = $this->getState($tenantId, $conversationId);
        return $state['last_resolved_technical_product'] ?? null;
    }
}


