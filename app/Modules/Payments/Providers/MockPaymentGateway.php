<?php

namespace App\Modules\Payments\Providers;

use App\Core\Contracts\Payments\PaymentGatewayInterface;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTOs\PaymentIntentDTO;
use App\Modules\Payments\DTOs\PaymentVerificationDTO;
use App\Modules\Payments\DTOs\WebhookProcessingDTO;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentIntent(Order $order, string $paymentMethod): PaymentIntentDTO
    {
        $mockRef = 'MOCK-TX-' . Str::upper(Str::random(8));

        return new PaymentIntentDTO(
            gateway: 'MOCK',
            paymentMethod: $paymentMethod,
            amount: (float) $order->total_amount,
            currency: 'PEN',
            gatewayTransactionId: $mockRef,
            gatewayReference: "REF-{$order->order_number}",
            merchantReference: $order->order_number,
            qrCodeUrl: "https://pay.aiva.pe/qr/mock/{$mockRef}",
            paymentLink: "https://pay.aiva.pe/p/mock/{$mockRef}",
            expiresAt: now()->addMinutes(30)->toIso8601String(),
            metadata: [
                'gateway' => 'mock',
                'environment' => 'sandbox',
                'order_number' => $order->order_number,
            ]
        );
    }

    public function verifyPayment(string $transactionId): PaymentVerificationDTO
    {
        return new PaymentVerificationDTO(
            isApproved: true,
            status: 'APPROVED',
            gatewayTransactionId: $transactionId,
            authorizationCode: 'AUTH-' . rand(100000, 999999),
            paidAt: now()->toIso8601String(),
            rawResponse: ['status' => 'success', 'code' => 200]
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookProcessingDTO
    {
        $eventId = $payload['event_id'] ?? ('evt_' . Str::random(10));
        $orderUuid = $payload['order_uuid'] ?? null;
        $paymentUuid = $payload['payment_uuid'] ?? null;
        $txId = $payload['transaction_id'] ?? ('tx_' . Str::random(8));

        return new WebhookProcessingDTO(
            isValid: true,
            eventId: $eventId,
            gateway: 'MOCK',
            orderUuid: $orderUuid,
            paymentUuid: $paymentUuid,
            gatewayTransactionId: $txId,
            status: 'APPROVED',
            amount: (float) ($payload['amount'] ?? 0.00)
        );
    }

    public function getGatewayName(): string
    {
        return 'MOCK';
    }
}
