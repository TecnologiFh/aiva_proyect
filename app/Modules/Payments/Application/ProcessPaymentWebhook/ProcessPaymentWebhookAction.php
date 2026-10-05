<?php

namespace App\Modules\Payments\Application\ProcessPaymentWebhook;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\PaymentTransactionStatus;
use App\Core\Events\PaymentApproved;
use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Orders\Repositories\OrderRepository;
use App\Modules\Payments\Models\PaymentWebhook;
use App\Modules\Payments\Repositories\PaymentRepository;
use App\Modules\Payments\Services\PaymentGatewayFactory;

class ProcessPaymentWebhookAction
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly SendMessageAction $sendMessageAction,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(ProcessPaymentWebhookInput $input): ProcessPaymentWebhookResult
    {
        $gateway = $this->gatewayFactory->make($input->gateway);
        $webhookDTO = $gateway->handleWebhook($input->payload, $input->headers);

        if (!$webhookDTO->isValid) {
            return new ProcessPaymentWebhookResult(processed: false, status: 'INVALID_SIGNATURE');
        }

        // 1. Idempotencia y Auditoría Antifraude
        $exists = PaymentWebhook::where('event_id', $webhookDTO->eventId)->exists();
        if ($exists) {
            return new ProcessPaymentWebhookResult(processed: true, status: 'DUPLICATE_EVENT_SKIPPED');
        }

        PaymentWebhook::create([
            'event_id' => $webhookDTO->eventId,
            'gateway' => $input->gateway,
            'signature' => $input->signature,
            'headers' => $input->headers,
            'payload' => $input->payload,
            'processed_at' => now(),
        ]);

        // 2. Buscar Pago asociado
        $payment = null;
        if ($webhookDTO->paymentUuid) {
            $payment = $this->paymentRepository->findByUuid($webhookDTO->paymentUuid);
        } elseif ($webhookDTO->gatewayTransactionId) {
            $payment = $this->paymentRepository->findByTransactionReference($input->gateway, $webhookDTO->gatewayTransactionId);
        }

        if ($payment && $webhookDTO->status === 'APPROVED') {
            // 3. Actualizar Estado del Pago a APPROVED
            $this->paymentRepository->updateStatus($payment->id, PaymentTransactionStatus::APPROVED->value, now()->toDateTimeString());

            // 4. Actualizar Estado Comercial de la Orden (PAID -> AWAITING_ACCEPTANCE)
            if ($payment->order_id) {
                $order = $this->orderRepository->findById($payment->order_id);
                if ($order) {
                    $order->update([
                        'payment_status' => PaymentStatus::PAID->value,
                        'status' => OrderStatus::AWAITING_ACCEPTANCE->value,
                    ]);

                    // 5. Notificar al cliente vía WhatsApp
                    if ($order->customer_phone) {
                        $msg = "💳 *¡Pago Recibido de S/ " . number_format($payment->amount, 2) . "!*\n";
                        $msg .= "Tu pedido {$order->order_number} ha sido pagado con éxito y se encuentra esperando la confirmación final de nuestro local.";

                        $this->sendMessageAction->execute(new SendMessageInput(
                            tenantId: $order->tenant_id,
                            recipientPhone: $order->customer_phone,
                            content: $msg,
                            senderType: 'AI',
                            conversationId: $order->conversation_id
                        ));
                    }
                }
            }

            // 6. Disparar Evento de Dominio
            $this->eventBus->dispatch(new PaymentApproved($payment));
        }

        return new ProcessPaymentWebhookResult(processed: true, status: 'APPROVED');
    }
}
