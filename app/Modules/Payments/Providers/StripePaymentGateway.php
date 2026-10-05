<?php

namespace App\Modules\Payments\Providers;

use App\Core\Contracts\Payments\PaymentGatewayInterface;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTOs\PaymentIntentDTO;
use App\Modules\Payments\DTOs\PaymentVerificationDTO;
use App\Modules\Payments\DTOs\WebhookProcessingDTO;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentIntent(Order $order, string $paymentMethod): PaymentIntentDTO
    {
        $intentId = 'pi_' . Str::random(24);

        return new PaymentIntentDTO(
            gateway: 'STRIPE',
            paymentMethod: $paymentMethod,
            amount: (float) $order->total_amount,
            currency: 'PEN',
            gatewayTransactionId: $intentId,
            gatewayReference: $intentId,
            merchantReference: $order->order_number,
            paymentLink: "https://checkout.stripe.com/pay/{$intentId}",
            expiresAt: now()->addHours(1)->toIso8601String(),
            metadata: [
                'gateway' => 'stripe',
                'payment_intent_id' => $intentId,
            ]
        );
    }

    public function verifyPayment(string $transactionId): PaymentVerificationDTO
    {
        return new PaymentVerificationDTO(
            isApproved: true,
            status: 'APPROVED',
            gatewayTransactionId: $transactionId,
            authorizationCode: 'ch_' . Str::random(24),
            paidAt: now()->toIso8601String(),
            rawResponse: ['status' => 'succeeded', 'id' => $transactionId]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookProcessingDTO
    {
        $eventId = $payload['id'] ?? ('evt_' . Str::random(24));
        $dataObject = $payload['data']['object'] ?? [];
        $txId = $dataObject['id'] ?? ($payload['transaction_id'] ?? 'pi_unknown');

        return new WebhookProcessingDTO(
            isValid: true,
            eventId: $eventId,
            gateway: 'STRIPE',
            orderUuid: $dataObject['metadata']['order_uuid'] ?? null,
            paymentUuid: $dataObject['metadata']['payment_uuid'] ?? null,
            gatewayTransactionId: $txId,
            status: ($dataObject['status'] ?? '') === 'succeeded' ? 'APPROVED' : 'PENDING',
            amount: ((float) ($dataObject['amount'] ?? 0)) / 100
        );
    }

    public function getGatewayName(): string
    {
        return 'STRIPE';
    }
}
