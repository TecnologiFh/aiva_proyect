<?php

namespace App\Modules\Orders\Application\UpdateOrderStatus;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\OrderStatus;
use App\Core\Events\OrderCancelled;
use App\Core\Events\OrderStatusChanged;
use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Orders\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateOrderStatusAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SendMessageAction $sendMessageAction,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(UpdateOrderStatusInput $input): UpdateOrderStatusResult
    {
        $order = $this->orderRepository->findByUuid($input->orderUuid);
        if (!$order) {
            throw new ModelNotFoundException('Pedido no encontrado.');
        }

        $oldStatus = $order->status->value;
        $order->update(['status' => $input->newStatus]);

        if ($input->notifyWhatsApp && $order->customer_phone) {
            $statusText = match ($input->newStatus) {
                OrderStatus::PREPARING->value => "🍳 Tu pedido {$order->order_number} está en preparación.",
                OrderStatus::READY->value => "📦 Tu pedido {$order->order_number} está listo.",
                OrderStatus::DELIVERED->value => "🛵 Tu pedido {$order->order_number} ha sido entregado. ¡Que lo disfrutes!",
                OrderStatus::CANCELLED->value => "❌ Tu pedido {$order->order_number} ha sido cancelado.",
                default => "Estado del pedido {$order->order_number} actualizado a {$input->newStatus}.",
            };

            $this->sendMessageAction->execute(new SendMessageInput(
                tenantId: $order->tenant_id,
                recipientPhone: $order->customer_phone,
                content: $statusText,
                senderType: 'AI',
                conversationId: $order->conversation_id
            ));
        }

        if ($input->newStatus === OrderStatus::CANCELLED->value) {
            $this->eventBus->dispatch(new OrderCancelled($order));
        } else {
            $this->eventBus->dispatch(new OrderStatusChanged($order, $oldStatus, $input->newStatus));
        }

        return new UpdateOrderStatusResult($order);
    }
}
