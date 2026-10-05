<?php

namespace App\Modules\Payments\Application\CreatePaymentIntent;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\PaymentTransactionStatus;
use App\Core\Events\PaymentIntentCreated;
use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Orders\Repositories\OrderRepository;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Repositories\PaymentRepository;
use App\Modules\Payments\Services\PaymentGatewayFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly SendMessageAction $sendMessageAction,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(CreatePaymentIntentInput $input): CreatePaymentIntentResult
    {
        $order = $this->orderRepository->findByUuid($input->orderUuid);
        if (!$order) {
            throw new ModelNotFoundException('Pedido no encontrado.');
        }

        $gateway = $this->gatewayFactory->make($input->gateway);
        $intentDTO = $gateway->createPaymentIntent($order, $input->paymentMethod ?? 'CASH');

        $payment = DB::transaction(function () use ($order, $intentDTO, $input) {
            $pay = $this->paymentRepository->create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'gateway' => $intentDTO->gateway,
                'payment_method' => $intentDTO->paymentMethod,
                'gateway_transaction_id' => $intentDTO->gatewayTransactionId,
                'gateway_reference' => $intentDTO->gatewayReference,
                'merchant_reference' => $intentDTO->merchantReference,
                'amount' => $intentDTO->amount,
                'currency' => $intentDTO->currency,
                'status' => PaymentTransactionStatus::PENDING->value,
                'qr_code_url' => $intentDTO->qrCodeUrl,
                'payment_link' => $intentDTO->paymentLink,
                'expires_at' => $intentDTO->expiresAt,
                'metadata' => $intentDTO->metadata,
            ]);

            // Registrar el intento inicial #1 en payment_attempts
            PaymentAttempt::create([
                'tenant_id' => $order->tenant_id,
                'payment_id' => $pay->id,
                'attempt_number' => 1,
                'status' => PaymentTransactionStatus::PENDING->value,
                'raw_response' => $intentDTO->metadata,
            ]);

            return $pay;
        });

        // Instrucciones Comerciales de Pago
        $instructions = "💳 *Forma de Pago - Pedido {$order->order_number}:*\n";
        $instructions .= "💰 *Monto a pagar:* S/ " . number_format($payment->amount, 2) . "\n";

        if ($payment->qr_code_url) {
            $instructions .= "📲 *Yape / Plin:* Escanea el código QR o transfiere al número de contacto de la empresa.\n";
        }
        if ($payment->payment_link) {
            $instructions .= "🔗 *Paga en línea con Tarjeta:* {$payment->payment_link}\n";
        }
        if ($payment->expires_at) {
            $instructions .= "⏱️ *Válido hasta:* " . $payment->expires_at->format('H:i') . " hrs\n";
        }

        // Notificar por WhatsApp si aplica
        if ($input->sendWhatsAppInstructions && $order->customer_phone) {
            $this->sendMessageAction->execute(new SendMessageInput(
                tenantId: $order->tenant_id,
                recipientPhone: $order->customer_phone,
                content: $instructions,
                senderType: 'AI',
                conversationId: $order->conversation_id
            ));
        }

        // Disparar Evento de Dominio
        $this->eventBus->dispatch(new PaymentIntentCreated($payment));

        return new CreatePaymentIntentResult($payment, $instructions);
    }
}
