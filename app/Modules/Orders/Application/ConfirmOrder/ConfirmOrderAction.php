<?php

namespace App\Modules\Orders\Application\ConfirmOrder;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\OrderStatus;
use App\Core\Events\OrderConfirmed;
use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Orders\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConfirmOrderAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SendMessageAction $sendMessageAction,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(ConfirmOrderInput $input): ConfirmOrderResult
    {
        $order = $this->orderRepository->findByUuid($input->orderUuid);
        if (!$order) {
            throw new ModelNotFoundException('Pedido no encontrado.');
        }

        $order->update([
            'status' => OrderStatus::CONFIRMED->value,
            'payment_method' => $input->paymentMethod ?? $order->payment_method->value,
        ]);

        if ($input->notifyWhatsApp && $order->customer_phone) {
            $msg = "✅ *¡Pedido {$order->order_number} Confirmado!*\nTu pedido ha ingresado a nuestra cocina/preparación. ¡Te avisaremos apenas esté listo! 🎉";
            $this->sendMessageAction->execute(new SendMessageInput(
                tenantId: $order->tenant_id,
                recipientPhone: $order->customer_phone,
                content: $msg,
                senderType: 'AI',
                conversationId: $order->conversation_id
            ));
        }

        $this->eventBus->dispatch(new OrderConfirmed($order));

        return new ConfirmOrderResult($order);
    }
}
