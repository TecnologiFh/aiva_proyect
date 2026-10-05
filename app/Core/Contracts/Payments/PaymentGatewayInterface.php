<?php

namespace App\Core\Contracts\Payments;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTOs\PaymentIntentDTO;
use App\Modules\Payments\DTOs\PaymentVerificationDTO;
use App\Modules\Payments\DTOs\WebhookProcessingDTO;

interface PaymentGatewayInterface
{
    /**
     * Genera una intención de pago o código QR para la orden.
     */
    public function createPaymentIntent(Order $order, string $paymentMethod): PaymentIntentDTO;

    /**
     * Verifica el estado de una transacción con la pasarela.
     */
    public function verifyPayment(string $transactionId): PaymentVerificationDTO;

    /**
     * Procesa y valida la firma/payload de un webhook de pasarela.
     */
    public function handleWebhook(array $payload, array $headers): WebhookProcessingDTO;

    /**
     * Retorna el identificador del gateway (ej. MOCK, YAPE_PLIN, STRIPE).
     */
    public function getGatewayName(): string;
}
