<?php

namespace App\Modules\Payments\Providers;

use App\Core\Contracts\Payments\PaymentGatewayInterface;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTOs\PaymentIntentDTO;
use App\Modules\Payments\DTOs\PaymentVerificationDTO;
use App\Modules\Payments\DTOs\WebhookProcessingDTO;
use Illuminate\Support\Str;

class YapePlinGateway implements PaymentGatewayInterface
{
    public function createPaymentIntent(Order $order, string $paymentMethod): PaymentIntentDTO
    {
        $refCode = 'YP-' . rand(100000, 999999);

        return new PaymentIntentDTO(
            gateway: 'YAPE_PLIN',
            paymentMethod: $paymentMethod,
            amount: (float) $order->total_amount,
            currency: 'PEN',
            gatewayTransactionId: $refCode,
            gatewayReference: "YP-REF-{$order->order_number}",
            merchantReference: $order->order_number,
            qrCodeUrl: "https://aiva.pe/assets/qr/yape_plin_static.png",
            paymentLink: "https://pay.aiva.pe/yape/{$order->uuid}",
            expiresAt: now()->addMinutes(30)->toIso8601String(),
            metadata: [
                'gateway' => 'yape_plin',
                'merchant_phone' => '999888777',
                'reference_code' => $refCode,
            ]
        );
    }

    public function verifyPayment(string $transactionId): PaymentVerificationDTO
    {
        return new PaymentVerificationDTO(
            isApproved: true,
            status: 'APPROVED',
            gatewayTransactionId: $transactionId,
            authorizationCode: 'YAPE-AUTH-' . rand(1000, 9999),
            paidAt: now()->toIso8601String(),
            rawResponse: ['status' => 'approved', 'reference' => $transactionId]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookProcessingDTO
    {
        $eventId = $payload['event_id'] ?? ('yp_evt_' . Str::random(10));
        $txId = $payload['transaction_id'] ?? ($payload['reference_code'] ?? 'YP-UNKNOWN');

        return new WebhookProcessingDTO(
            isValid: true,
            eventId: $eventId,
            gateway: 'YAPE_PLIN',
            orderUuid: $payload['order_uuid'] ?? null,
            paymentUuid: $payload['payment_uuid'] ?? null,
            gatewayTransactionId: $txId,
            status: 'APPROVED',
            amount: (float) ($payload['amount'] ?? 0.00)
        );
    }

    public function getGatewayName(): string
    {
        return 'YAPE_PLIN';
    }
}
