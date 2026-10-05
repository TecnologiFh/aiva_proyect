<?php

namespace App\Modules\Messaging\Services;

class ResponseArbitrationService
{
    private ConversationStateManager $stateManager;
    private CaptionPolicy $captionPolicy;

    public function __construct(
        ?ConversationStateManager $stateManager = null,
        ?CaptionPolicy $captionPolicy = null
    ) {
        $this->stateManager = $stateManager ?? new ConversationStateManager();
        $this->captionPolicy = $captionPolicy ?? new CaptionPolicy();
    }

    /**
     * Arbitrar sobre el conjunto de intenciones candidatas y producir un ExecutionPlan canónico único
     */
    public function arbitrate(
        int $tenantId,
        string $conversationId,
        array $candidateSet,
        string $messageText
    ): array {
        $correction = $candidateSet['correction'] ?? null;
        $pendingConfirmation = $candidateSet['pending_confirmation'] ?? null;
        $cartResetCandidate = $candidateSet['cart_reset_candidate'] ?? null;
        $orderStatusCandidate = $candidateSet['order_status_candidate'] ?? null;
        $cartStatusCandidate = $candidateSet['cart_status_candidate'] ?? null;
        $mediaCandidate = $candidateSet['media_candidate'] ?? null;
        $orderCandidate = $candidateSet['order_candidate'] ?? null;
        $priceCandidate = $candidateSet['price_candidate'] ?? null;
        $categoryCandidate = $candidateSet['category_candidate'] ?? null;
        $checkoutCandidate = $candidateSet['checkout_candidate'] ?? null;
        $totalCandidate = $candidateSet['total_candidate'] ?? null;

        // P0: CORRECCIÓN CONVERSACIONAL (Máxima Prioridad)
        if ($correction && !empty($correction['is_correction'])) {
            return $this->arbitrateCorrection($tenantId, $conversationId, $correction, $candidateSet);
        }

        // P0.5: REINICIO DE CARRITO / CANCELACIÓN CONVERSACIONAL
        if ($cartResetCandidate && !empty($cartResetCandidate['is_cart_reset'])) {
            return [
                'primary_action' => 'CART_RESET',
                'secondary_actions' => [],
                'media_action_lock' => false,
                'terminal' => false,
                'execution_mode' => 'CART_RESET_FLOW',
            ];
        }

        // P0.8: CONSULTA DE ESTADO DE PEDIDO (Tracking / Historial)
        if ($orderStatusCandidate && !empty($orderStatusCandidate['is_order_status'])) {
            return [
                'primary_action' => 'ORDER_STATUS_INQUIRY',
                'order_reference' => $orderStatusCandidate['order_reference'] ?? null,
                'secondary_actions' => [],
                'media_action_lock' => false,
                'terminal' => false,
                'execution_mode' => 'ORDER_STATUS_FLOW',
            ];
        }

        // P1: CONFIRMACIÓN DE ACCIÓN PENDIENTE (Cumplimiento de Promesa)
        if ($pendingConfirmation && !empty($pendingConfirmation['confirmed'])) {
            return $this->arbitratePendingConfirmation($tenantId, $conversationId, $pendingConfirmation, $candidateSet);
        }

        // P2/P3: SOLICITUD DE MEDIO / CARTA VISUAL (Explícita o Anafórica)
        if ($mediaCandidate && !empty($mediaCandidate['is_media'])) {
            return [
                'primary_action' => 'MEDIA_DISPATCH',
                'target' => $mediaCandidate['target'] ?? null,
                'secondary_actions' => $this->collectSecondaryActions($candidateSet, 'MEDIA_DISPATCH'),
                'media_action_lock' => true,
                'terminal' => true,
                'execution_mode' => 'TERMINAL_WITH_MEDIA',
                'order_candidate' => $orderCandidate,
                'price_candidate' => $priceCandidate,
            ];
        }

        // P4.1: DISPARADOR EXPLÍCITO DE CHECKOUT
        if ($checkoutCandidate && !empty($checkoutCandidate['is_checkout'])) {
            $this->stateManager->setCartState($tenantId, $conversationId, 'CHECKOUT_REQUESTED');
            return [
                'primary_action' => 'CHECKOUT',
                'secondary_actions' => [],
                'media_action_lock' => false,
                'terminal' => false,
                'execution_mode' => 'CHECKOUT_FLOW',
            ];
        }

        // P4.15: CONSULTA DE TOTAL / SUBTOTAL O DETALLE DEL CARRITO ACTIVO
        if ($totalCandidate && !empty($totalCandidate['is_total'])) {
            return [
                'primary_action' => 'CALCULATE_TOTAL',
                'secondary_actions' => [],
                'media_action_lock' => false,
                'open_cart_immunity' => true,
                'terminal' => false,
                'execution_mode' => 'TOTAL_CALCULATION',
            ];
        }

        if ($cartStatusCandidate && !empty($cartStatusCandidate['is_cart_status'])) {
            return [
                'primary_action' => 'CART_STATUS_INQUIRY',
                'secondary_actions' => [],
                'media_action_lock' => false,
                'open_cart_immunity' => true,
                'terminal' => false,
                'execution_mode' => 'CART_STATUS_FLOW',
            ];
        }

        // P4.2: INTENCIÓN DE COMPRA / CARRITO CONTINUO (ITEM_ADDED ≠ CHECKOUT)
        if ($orderCandidate && !empty($orderCandidate['is_order'])) {
            return [
                'primary_action' => 'ADD_TO_CART',
                'order' => $orderCandidate,
                'secondary_actions' => $this->collectSecondaryActions($candidateSet, 'ADD_TO_CART'),
                'media_action_lock' => false,
                'open_cart_immunity' => true,
                'terminal' => false,
                'execution_mode' => 'CART_ADDITION',
            ];
        }

        // P4.3: CONSULTA DE PRECIO
        if ($priceCandidate && !empty($priceCandidate['is_price'])) {
            return [
                'primary_action' => 'PRICE_INQUIRY',
                'price_data' => $priceCandidate,
                'secondary_actions' => [],
                'media_action_lock' => false,
                'terminal' => false,
                'execution_mode' => 'PRICE_ANSWER',
            ];
        }

        // P4.4: CONSULTA DE CATEGORÍA (Nomenclatura Unificada CATEGORY_QUERY - AG-43)
        if ($categoryCandidate && !empty($categoryCandidate['category'])) {
            return [
                'primary_action' => 'CATEGORY_QUERY',
                'category' => $categoryCandidate['category'],
                'secondary_actions' => [],
                'media_action_lock' => false,
                'terminal' => false,
                'execution_mode' => 'CATEGORY_OVERVIEW',
            ];
        }

        // P5: FALLBACK CONTROLADO
        return [
            'primary_action' => 'GENERAL_QUERY',
            'secondary_actions' => [],
            'media_action_lock' => false,
            'terminal' => false,
            'execution_mode' => 'SUBORDINATED_LLM',
        ];
    }

    private function arbitrateCorrection(int $tenantId, string $conversationId, array $correction, array $candidateSet): array
    {
        $rejected = $correction['rejected_concepts'] ?? [];
        $replacement = $correction['replacement_target'] ?? null;

        // Registrar la exclusión
        $this->stateManager->recordCorrection($tenantId, $conversationId, $rejected, $replacement);

        // Si hay un concepto de reemplazo positivo y el contexto previo era visual, saltar directamente a MEDIA_DISPATCH
        if (!empty($replacement)) {
            return [
                'primary_action' => 'MEDIA_DISPATCH',
                'target' => $replacement,
                'correction_applied' => true,
                'rejected_concepts' => $rejected,
                'replacement_target' => $replacement,
                'secondary_actions' => [],
                'media_action_lock' => true,
                'terminal' => true,
                'execution_mode' => 'CORRECTION_WITH_MEDIA_REPLACEMENT',
            ];
        }

        // Si no hay reemplazo explícito (ej. "No dije parrillas")
        $previousTarget = $this->stateManager->getPreviousVisualTarget($tenantId, $conversationId);
        $replyText = !empty($previousTarget)
            ? "Disculpa 😅, entendí mal. Me refería a nuestras {$previousTarget}. ¿Deseas ver esa carta o agregar una al pedido?"
            : "Disculpa 😅, entendí mal. ¿Qué categoría o carta te gustaría revisar?";

        return [
            'primary_action' => 'CONVERSATIONAL_CORRECTION',
            'rejected_concepts' => $rejected,
            'repair_reply' => $replyText,
            'secondary_actions' => [],
            'catalog_dump_blocked' => true,
            'terminal' => true,
            'execution_mode' => 'CONVERSATIONAL_REPAIR',
        ];
    }

    private function arbitratePendingConfirmation(int $tenantId, string $conversationId, array $pending, array $candidateSet): array
    {
        $action = $pending['action'];
        $claimed = $this->stateManager->claimPendingActionForExecution($tenantId, $conversationId);

        $secondary = [];
        // Si el usuario dijo "Sí, y cuánto cuesta X", preservar la consulta secundaria
        if (!empty($candidateSet['price_candidate']['is_price'])) {
            $secondary[] = [
                'type' => 'PRICE_INQUIRY',
                'data' => $candidateSet['price_candidate'],
            ];
        }
        if (!empty($candidateSet['order_candidate']['is_order'])) {
            $secondary[] = [
                'type' => 'ADD_TO_CART',
                'data' => $candidateSet['order_candidate'],
            ];
        }

        return [
            'primary_action' => 'MEDIA_DISPATCH',
            'target' => $action['target'],
            'confirmed_pending_action' => $action,
            'pending_action_id' => $action['id'] ?? null,
            'is_claimed_execution' => $claimed !== null,
            'secondary_actions' => $secondary,
            'media_action_lock' => true,
            'terminal' => true,
            'execution_mode' => 'CONFIRMED_MEDIA_DISPATCH',
        ];
    }

    private function collectSecondaryActions(array $candidateSet, string $primaryAction): array
    {
        $secondary = [];
        if ($primaryAction !== 'PRICE_INQUIRY' && !empty($candidateSet['price_candidate']['is_price'])) {
            $secondary[] = ['type' => 'PRICE_INQUIRY', 'data' => $candidateSet['price_candidate']];
        }
        if ($primaryAction !== 'ADD_TO_CART' && !empty($candidateSet['order_candidate']['is_order'])) {
            $secondary[] = ['type' => 'ADD_TO_CART', 'data' => $candidateSet['order_candidate']];
        }
        return $secondary;
    }
}
