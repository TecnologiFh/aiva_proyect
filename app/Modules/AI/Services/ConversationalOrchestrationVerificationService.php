<?php

namespace App\Modules\AI\Services;

use App\Modules\Messaging\Controllers\TenantBrainController;
use App\Modules\Messaging\Services\ConversationStateManager;
use App\Modules\Messaging\Services\IntentResolverService;
use App\Modules\Messaging\Services\ResponseArbitrationService;
use App\Shared\Middleware\GatewayAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationalOrchestrationVerificationService
{
    /**
     * Ejecuta la suite de verificación conversacional:
     * 1. Benchmark Real T1-T6 (WhatsApp Wallpa Brasa)
     * 2. Batería A-H de Arbitraje y Resiliencia
     */
    public function runVerification(): array
    {
        $startTime = microtime(true);

        $t1t6Results = $this->runT1T6Benchmark();
        $batteryResults = $this->runBatteryAH();

        $allT1T6Passed = collect($t1t6Results)->every(fn($step) => $step['status'] === 'PASS');
        $allBatteryPassed = collect($batteryResults)->every(fn($test) => $test['status'] === 'PASS');

        $overallStatus = ($allT1T6Passed && $allBatteryPassed) ? 'PASS' : 'FAIL';

        return [
            'suite' => 'Conversational Orchestration, State & Response Arbitration (V3 Freeze)',
            'overall_status' => $overallStatus,
            'benchmark_t1_t6' => [
                'status' => $allT1T6Passed ? 'PASS' : 'FAIL',
                'turns' => $t1t6Results,
            ],
            'battery_a_h' => [
                'status' => $allBatteryPassed ? 'PASS' : 'FAIL',
                'tests' => $batteryResults,
            ],
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    /**
     * Benchmark Real T1-T6: Reproduce exactamente el flujo conversacional del caso Wallpa Brasa
     */
    private function runT1T6Benchmark(): array
    {
        $phone = '51999111' . rand(100, 999);
        $turns = [];

        // T1: Carta General
        $resT1 = $this->dispatchInbound($phone, 'Hola, pásame la carta');
        $attT1 = $resT1['attachments'][0] ?? [];
        $t1Pass = ($resT1['status'] === 'SUCCESS')
            && str_contains($attT1['file_name'] ?? '', 'carta1')
            && str_contains($resT1['reply_text'] ?? '', 'Carta General')
            && !str_contains($resT1['reply_text'] ?? '', 'Parrilla Familiar') // No dump de catálogo
            && !str_contains($resT1['reply_text'] ?? '', '¡Claro! Aquí tienes nuestra ¡Claro!'); // Sin doble caption
        $turns['T1'] = [
            'turn' => 'T1',
            'input' => 'Hola, pásame la carta',
            'expected' => 'Imagen Carta 1 + Caption General Único + STOP',
            'actual' => [
                'attachment' => $attT1['file_name'] ?? 'none',
                'caption' => $resT1['reply_text'] ?? '',
                'intent' => $resT1['intent'] ?? '',
            ],
            'status' => $t1Pass ? 'PASS' : 'FAIL',
        ];

        // T2: Wallpa Criollo (ITEM_ADDED != CHECKOUT)
        $resT2 = $this->dispatchInbound($phone, 'Quiero el Wallpa Criollo');
        $t2Pass = ($resT2['status'] === 'SUCCESS')
            && ($resT2['intent'] ?? '') === 'ADD_TO_CART'
            && str_contains($resT2['reply_text'] ?? '', 'Wallpa Criollo')
            && str_contains($resT2['reply_text'] ?? '', '25.00')
            && str_contains($resT2['reply_text'] ?? '', '¿Deseas agregar algo más')
            && !str_contains($resT2['reply_text'] ?? '', 'Yape') // NO checkout prematuro
            && !str_contains($resT2['reply_text'] ?? '', 'distrito');
        $turns['T2'] = [
            'turn' => 'T2',
            'input' => 'Quiero el Wallpa Criollo',
            'expected' => 'Producto agregado a carrito OPEN + "¿Deseas agregar algo más?" (Sin Checkout)',
            'actual' => [
                'reply_text' => $resT2['reply_text'] ?? '',
                'intent' => $resT2['intent'] ?? '',
            ],
            'status' => $t2Pass ? 'PASS' : 'FAIL',
        ];

        // T3: Pastas + promesa
        $resT3 = $this->dispatchInbound($phone, '¿Tendrás pastas?');
        $t3Pass = ($resT3['status'] === 'SUCCESS')
            && in_array($resT3['intent'] ?? '', ['CATEGORY_QUERY', 'CATEGORY_INQUIRY'])
            && str_contains(mb_strtolower($resT3['reply_text'] ?? ''), 'fetuccini')
            && str_contains(mb_strtolower($resT3['reply_text'] ?? ''), 'carta');
        $turns['T3'] = [
            'turn' => 'T3',
            'input' => '¿Tendrás pastas?',
            'expected' => 'Reconoce categoría Pastas + Registra pending_action MEDIA_DISPATCH(PASTAS)',
            'actual' => [
                'reply_text' => $resT3['reply_text'] ?? '',
                'intent' => $resT3['intent'] ?? '',
            ],
            'status' => $t3Pass ? 'PASS' : 'FAIL',
        ];

        // T4: "Sí porfa" -> Carta Pastas
        $resT4 = $this->dispatchInbound($phone, 'Sí porfa');
        $attT4 = $resT4['attachments'][0] ?? [];
        $t4Pass = ($resT4['status'] === 'SUCCESS')
            && str_contains($attT4['file_name'] ?? '', 'carta4')
            && str_contains(mb_strtolower($attT4['caption'] ?? ''), 'pastas')
            && !str_contains($attT4['caption'] ?? '', '¡Claro! Aquí tienes nuestra ¡Claro!'); // Sin doble caption
        $turns['T4'] = [
            'turn' => 'T4',
            'input' => 'Sí porfa',
            'expected' => 'Imagen Carta 4 + Caption Pastas + STOP (Cero dump de texto)',
            'actual' => [
                'attachment' => $attT4['file_name'] ?? 'none',
                'caption' => $attT4['caption'] ?? '',
                'intent' => $resT4['intent'] ?? '',
            ],
            'status' => $t4Pass ? 'PASS' : 'FAIL',
        ];

        // T5: "carta visual donde están las pastas"
        $resT5 = $this->dispatchInbound($phone, 'carta visual donde están las pastas');
        $attT5 = $resT5['attachments'][0] ?? [];
        $t5Pass = ($resT5['status'] === 'SUCCESS')
            && str_contains($attT5['file_name'] ?? '', 'carta4')
            && str_contains(mb_strtolower($attT5['caption'] ?? ''), 'pastas')
            && !str_contains($attT5['caption'] ?? '', '¡Claro! Aquí tienes nuestra ¡Claro!');
        $turns['T5'] = [
            'turn' => 'T5',
            'input' => 'carta visual donde están las pastas',
            'expected' => 'Carta 4 + Caption contextual Pastas (Cero duplicación de prefijo)',
            'actual' => [
                'attachment' => $attT5['file_name'] ?? 'none',
                'caption' => $attT5['caption'] ?? '',
            ],
            'status' => $t5Pass ? 'PASS' : 'FAIL',
        ];

        // T6: "No dije parrillas"
        $resT6 = $this->dispatchInbound($phone, 'No dije parrillas');
        $replyT6 = mb_strtolower($resT6['reply_text'] ?? '');
        $attT6 = $resT6['attachments'] ?? [];
        $t6Pass = ($resT6['status'] === 'SUCCESS')
            && ($resT6['intent'] ?? '') === 'CONVERSATIONAL_CORRECTION'
            && (empty($attT6) || !str_contains(mb_strtolower($attT6[0]['caption'] ?? ''), 'parrillas'))
            && !str_contains($replyT6, 'parrilla para dos')
            && !str_contains($replyT6, 'parrilla familiar')
            && !str_contains($replyT6, 'aquí tienes nuestras opciones de parrillas');
        $turns['T6'] = [
            'turn' => 'T6',
            'input' => 'No dije parrillas',
            'expected' => 'Reconoce corrección P0 + Descarta Parrillas + NO responde con Parrillas',
            'actual' => [
                'reply_text' => $resT6['reply_text'] ?? '',
                'intent' => $resT6['intent'] ?? '',
                'attachments_count' => count($attT6),
            ],
            'status' => $t6Pass ? 'PASS' : 'FAIL',
        ];

        return $turns;
    }

    /**
     * Batería A-H: Pruebas unitarias/sintéticas de resiliencia conversacional
     */
    private function runBatteryAH(): array
    {
        $tests = [];
        $stateManager = new ConversationStateManager();
        $resolver = new IntentResolverService();
        $arbitrator = new ResponseArbitrationService($stateManager);

        // Prueba A: Confirmación simple ("sí porfa" con promesa activa)
        $phoneA = '51999222111';
        $convIdA = 'test_conv_a_' . time();
        $stateManager->setPendingAction(1, $convIdA, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdA, 'CATEGORY_INQUIRY');
        $candsA = $resolver->resolveCandidateActions(1, $convIdA, 'sí porfa');
        $planA = $arbitrator->arbitrate(1, $convIdA, $candsA, 'sí porfa');
        $tests['Test_A_Simple_Confirmation'] = [
            'description' => 'Confirmación simple ejecuta la promesa pendiente de media con TERMINAL',
            'status' => ($planA['primary_action'] === 'MEDIA_DISPATCH' && $planA['target'] === 'PASTAS' && !empty($planA['terminal'])) ? 'PASS' : 'FAIL',
            'primary_action' => $planA['primary_action'],
            'target' => $planA['target'] ?? null,
        ];

        // Prueba B: Confirmación incompatible ("sí porfa, quiero pagar ya")
        $convIdB = 'test_conv_b_' . time();
        $stateManager->setPendingAction(1, $convIdB, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdB, 'CATEGORY_INQUIRY');
        $candsB = $resolver->resolveCandidateActions(1, $convIdB, 'sí porfa, quiero pagar');
        $planB = $arbitrator->arbitrate(1, $convIdB, $candsB, 'sí porfa, quiero pagar');
        $pendingAfterB = $stateManager->getValidPendingAction(1, $convIdB);
        $tests['Test_B_Incompatible_Confirmation'] = [
            'description' => 'Confirmación incompatible (checkout) invalida promesa y no despacha media',
            'status' => ($planB['primary_action'] === 'CHECKOUT' && $pendingAfterB === null) ? 'PASS' : 'FAIL',
            'primary_action' => $planB['primary_action'],
            'pending_after' => $pendingAfterB ? 'STILL_ACTIVE' : 'SUPERSEDED',
        ];

        // Prueba C: Confirmación compuesta ("sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?")
        $convIdC = 'test_conv_c_' . time();
        $stateManager->setPendingAction(1, $convIdC, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdC, 'CATEGORY_INQUIRY');
        $candsC = $resolver->resolveCandidateActions(1, $convIdC, 'sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?');
        $planC = $arbitrator->arbitrate(1, $convIdC, $candsC, 'sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?');
        $hasSecPrice = collect($planC['secondary_actions'] ?? [])->contains(fn($a) => $a['type'] === 'PRICE_INQUIRY');
        $tests['Test_C_Composite_Confirmation'] = [
            'description' => 'Confirmación compuesta produce Primary MEDIA_DISPATCH + Secondary PRICE_INQUIRY en único contrato',
            'status' => ($planC['primary_action'] === 'MEDIA_DISPATCH' && $hasSecPrice) ? 'PASS' : 'FAIL',
            'primary_action' => $planC['primary_action'],
            'secondary_actions_count' => count($planC['secondary_actions'] ?? []),
        ];

        // Prueba D: Corrección con reemplazo explícito ("no quiero parrillas, pásame las pastas")
        $convIdD = 'test_conv_d_' . time();
        $candsD = $resolver->resolveCandidateActions(1, $convIdD, 'no quiero parrillas, pásame las pastas');
        $planD = $arbitrator->arbitrate(1, $convIdD, $candsD, 'no quiero parrillas, pásame las pastas');
        $tests['Test_D_Replacement_Correction'] = [
            'description' => 'Corrección con reemplazo descarta concepto rechazado y despacha reemplazo',
            'status' => ($planD['primary_action'] === 'MEDIA_DISPATCH' && $planD['target'] === 'PASTAS') ? 'PASS' : 'FAIL',
            'primary_action' => $planD['primary_action'],
            'target' => $planD['target'] ?? null,
        ];

        // Prueba E: Corrección pura ("no dije parrillas")
        $convIdE = 'test_conv_e_' . time();
        $candsE = $resolver->resolveCandidateActions(1, $convIdE, 'no dije parrillas');
        $planE = $arbitrator->arbitrate(1, $convIdE, $candsE, 'no dije parrillas');
        $tests['Test_E_Pure_Rejection_Correction'] = [
            'description' => 'Corrección pura activa CONVERSATIONAL_CORRECTION y rechaza PARRILLAS',
            'status' => ($planE['primary_action'] === 'CONVERSATIONAL_CORRECTION' && in_array('PARRILLAS', $planE['rejected_concepts'] ?? [])) ? 'PASS' : 'FAIL',
            'primary_action' => $planE['primary_action'],
            'rejected_concepts' => $planE['rejected_concepts'] ?? [],
        ];

        // Prueba F: Promesa vencida (caducidad de 2 turnos)
        $convIdF = 'test_conv_f_' . time();
        $stateManager->setPendingAction(1, $convIdF, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdF, 'CATEGORY_INQUIRY', 2);
        // Avanzar 3 turnos
        $stateManager->incrementTurn(1, $convIdF);
        $stateManager->incrementTurn(1, $convIdF);
        $stateManager->incrementTurn(1, $convIdF);
        $expiredPending = $stateManager->getValidPendingAction(1, $convIdF);
        $tests['Test_F_Expired_Promise'] = [
            'description' => 'Promesa caduca después de turns_elapsed > expires_after_turns',
            'status' => ($expiredPending === null) ? 'PASS' : 'FAIL',
            'pending_status' => $expiredPending ? 'ACTIVE' : 'EXPIRED_NULL',
        ];

        // Prueba G: Continuidad de Carrito (ITEM_ADDED != CHECKOUT)
        $convIdG = 'test_conv_g_' . time();
        $stateManager->addItemToCart(1, $convIdG, 34, 'Wallpa Criollo', 25.00, 1);
        $cartG = $stateManager->getCart(1, $convIdG);
        $tests['Test_G_Cart_Continuity'] = [
            'description' => 'ITEM_ADDED mantiene cart_state = OPEN y acumula items sin forzar checkout',
            'status' => (($cartG['cart_state'] ?? '') === 'OPEN' && count($cartG['items'] ?? []) === 1 && (float)($cartG['subtotal'] ?? 0) === 25.0) ? 'PASS' : 'FAIL',
            'cart_state' => $cartG['cart_state'] ?? 'UNKNOWN',
            'items_count' => count($cartG['items'] ?? []),
            'subtotal' => $cartG['subtotal'] ?? 0,
        ];

        // Prueba H: Arbitraje de Acciones Candidatas Múltiples
        $convIdH = 'test_conv_h_' . time();
        $candsH = $resolver->resolveCandidateActions(1, $convIdH, 'sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?');
        $planH = $arbitrator->arbitrate(1, $convIdH, $candsH, 'sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?');
        $tests['Test_H_Candidate_Action_Set_Arbitration'] = [
            'description' => 'CandidateActionSet procesado por ResponseArbitration genera un único ganador canónico',
            'status' => (isset($planH['primary_action']) && !empty($planH['primary_action'])) ? 'PASS' : 'FAIL',
            'winning_action' => $planH['primary_action'],
        ];

        // Prueba I: Concurrencia e Idempotencia (Protección contra doble ejecución de workers concurrentes)
        $convIdI = 'test_conv_i_' . time();
        $stateManager->setPendingAction(1, $convIdI, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdI, 'CATEGORY_INQUIRY');
        // Worker 1 reclama la acción
        $claimWorker1 = $stateManager->claimPendingActionForExecution(1, $convIdI);
        // Worker 2 intenta reclamar la misma acción concurrentemente
        $claimWorker2 = $stateManager->claimPendingActionForExecution(1, $convIdI);
        // Worker 1 finaliza ejecución exitosa
        $stateManager->markPendingActionExecuted(1, $convIdI);
        $stateAfterI = $stateManager->getState(1, $convIdI);
        $tests['Test_I_Atomic_Concurrent_Claim_Protection'] = [
            'description' => 'Transición atómica PENDING -> EXECUTING -> EXECUTED bloquea duplicación por workers concurrentes',
            'status' => ($claimWorker1 !== null && $claimWorker2 === null && ($stateAfterI['pending_action']['status'] ?? '') === 'EXECUTED') ? 'PASS' : 'FAIL',
            'worker1_claimed' => $claimWorker1 !== null,
            'worker2_blocked' => $claimWorker2 === null,
            'final_status' => $stateAfterI['pending_action']['status'] ?? 'UNKNOWN',
        ];

        // Prueba J: Manejo de Fallo de Ejecución y Reintento
        $convIdJ = 'test_conv_j_' . time();
        $stateManager->setPendingAction(1, $convIdJ, 'MEDIA_DISPATCH', 'PASTAS', [], $convIdJ, 'CATEGORY_INQUIRY');
        $claimJ = $stateManager->claimPendingActionForExecution(1, $convIdJ);
        $stateManager->markPendingActionFailed(1, $convIdJ, 'WhatsApp API Network Timeout', true);
        $stateAfterJ = $stateManager->getState(1, $convIdJ);
        $actionJ = $stateAfterJ['pending_action'] ?? [];
        $tests['Test_J_Execution_Failure_And_Retry_Lifecycle'] = [
            'description' => 'Fallo de red en WhatsApp no marca falsamente EXECUTED; registra EXECUTION_FAILED con retryable=true',
            'status' => (($actionJ['status'] ?? '') === 'EXECUTION_FAILED' && !empty($actionJ['retryable']) && str_contains($actionJ['error_message'] ?? '', 'Timeout')) ? 'PASS' : 'FAIL',
            'failure_status' => $actionJ['status'] ?? 'UNKNOWN',
            'retryable' => $actionJ['retryable'] ?? false,
        ];

        // Prueba K: Separación CALCULATE_TOTAL vs CHECKOUT_REQUESTED
        $convIdK = 'test_conv_k_' . time();
        $stateManager->addItemToCart(1, $convIdK, 34, 'Wallpa Criollo', 25.00, 1);
        // Caso K.1: "¿Cuánto llevo hasta ahora?" -> CALCULATE_TOTAL (Carrito se mantiene OPEN)
        $candsK1 = $resolver->resolveCandidateActions(1, $convIdK, 'dame el total');
        $planK1 = $arbitrator->arbitrate(1, $convIdK, $candsK1, 'dame el total');
        $cartK1 = $stateManager->getCart(1, $convIdK);
        // Caso K.2: "Quiero pagar" -> CHECKOUT_REQUESTED
        $candsK2 = $resolver->resolveCandidateActions(1, $convIdK, 'quiero pagar');
        $planK2 = $arbitrator->arbitrate(1, $convIdK, $candsK2, 'quiero pagar');
        $cartK2 = $stateManager->getCart(1, $convIdK);
        $tests['Test_K_Calculate_Total_Vs_Checkout'] = [
            'description' => 'Distingue CALCULATE_TOTAL (mantiene cart OPEN) de CHECKOUT_REQUESTED (inicia cierre)',
            'status' => ($planK1['primary_action'] === 'CALCULATE_TOTAL' && ($cartK1['cart_state'] ?? '') === 'OPEN'
                && $planK2['primary_action'] === 'CHECKOUT' && ($cartK2['cart_state'] ?? '') === 'CHECKOUT_REQUESTED') ? 'PASS' : 'FAIL',
            'k1_action' => $planK1['primary_action'],
            'k1_cart_state' => $cartK1['cart_state'] ?? '',
            'k2_action' => $planK2['primary_action'],
            'k2_cart_state' => $cartK2['cart_state'] ?? '',
        ];

        // Prueba L: Reparación Honesta en Frío (Sin referente previo, NO inventa Pastas)
        $convIdL = 'test_conv_l_' . time();
        $candsL = $resolver->resolveCandidateActions(1, $convIdL, 'no dije parrillas');
        $planL = $arbitrator->arbitrate(1, $convIdL, $candsL, 'no dije parrillas');
        $repairTextL = $planL['repair_reply'] ?? '';
        $tests['Test_L_Honest_Repair_No_Prior_Referent'] = [
            'description' => 'Corrección sin contexto previo no inventa referentes (no menciona Pastas; pide aclaración cortés)',
            'status' => ($planL['primary_action'] === 'CONVERSATIONAL_CORRECTION' && !str_contains(mb_strtolower($repairTextL), 'pastas') && str_contains(\Illuminate\Support\Str::ascii(mb_strtolower($repairTextL)), 'que categoria o carta')) ? 'PASS' : 'FAIL',
            'reply' => $repairTextL,
        ];

        // Prueba M: Terminalidad Estricta AG-37.1 en Turno Compuesto (Outbound único)
        $phoneM = '51999888' . rand(100, 999);
        // T3: Establecer promesa
        $this->dispatchInbound($phoneM, '¿Tendrás pastas?');
        // T4 compuesto: "Sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?"
        $resM = $this->dispatchInbound($phoneM, 'Sí porfa, pero ¿cuánto cuesta el Wallpa Criollo?');
        $attM = $resM['attachments'] ?? [];
        $replyM = $resM['reply_text'] ?? '';
        $tests['Test_M_Composite_Turn_Single_Outbound_Terminality'] = [
            'description' => 'Turno compuesto genera UNA SOLA unidad de respuesta (imagen con caption de carta + precio S/ 25.00)',
            'status' => (count($attM) === 1 && str_contains($attM[0]['file_name'] ?? '', 'carta4')
                && str_contains($replyM, 'Pastas') && str_contains($replyM, '25.00')) ? 'PASS' : 'FAIL',
            'attachments_count' => count($attM),
            'caption' => $replyM,
        ];

        return $tests;
    }

    /**
     * Auxiliar para disparar mensajes inbound a través del pipeline de WhatsApp
     */
    private function dispatchInbound(string $phone, string $message): array
    {
        $middleware = new GatewayAuthMiddleware();
        $secret = env('AIVA_GATEWAY_SECRET', 'aiva_qr_gateway_master_secret_2026');

        $req = Request::create('/api/v1/messaging/qr-inbound', 'POST', [
            'gateway_session_id' => 'wallpa-brasa',
            'phone' => $phone,
            'message' => $message,
        ]);
        $req->headers->set('Authorization', 'Bearer ' . $secret);
        $req->headers->set('X-Simulation', '1');

        $res = $middleware->handle($req, function($r) {
            $controller = app(TenantBrainController::class);
            return $controller->processInboundMessage($r);
        });

        return json_decode($res->getContent(), true) ?? [];
    }
}
