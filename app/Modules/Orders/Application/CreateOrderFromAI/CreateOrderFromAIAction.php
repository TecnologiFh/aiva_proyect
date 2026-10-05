<?php

namespace App\Modules\Orders\Application\CreateOrderFromAI;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\OrderType;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Events\OrderCreated;
use App\Modules\Business\Repositories\ProductRepository;
use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Orders\Repositories\OrderItemRepository;
use App\Modules\Orders\Repositories\OrderRepository;
use App\Modules\Orders\Services\OrderCalculator;
use App\Modules\Orders\Services\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;

class CreateOrderFromAIAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderCalculator $orderCalculator,
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly OrderRepository $orderRepository,
        private readonly OrderItemRepository $itemRepository,
        private readonly SendMessageAction $sendMessageAction,
        private readonly EventBusInterface $eventBus
    ) {}

    public function execute(CreateOrderFromAIInput $input): CreateOrderFromAIResult
    {
        $rawItems = [];

        // 1. Mapear entidades extraídas por la IA hacia el catálogo comercial del tenant (Sprint 3)
        foreach ($input->entities as $entity) {
            $searchTerm = $entity['product'] ?? ($entity['name'] ?? '');
            $quantity = (int) ($entity['quantity'] ?? 1);

            $paginator = $this->productRepository->paginate($input->tenantId, search: $searchTerm, limit: 1);
            $matchedProduct = $paginator->items()[0] ?? null;

            if ($matchedProduct) {
                $rawItems[] = [
                    'product_id' => $matchedProduct->id,
                    'product_uuid' => $matchedProduct->uuid,
                    'product_name' => $matchedProduct->name,
                    'sku' => $matchedProduct->sku,
                    'base_price' => (float) $matchedProduct->base_price,
                    'quantity' => $quantity,
                    'selected_options' => [], // Opciones procesadas dinámicamente si aplica
                ];
            } else {
                // Si el producto no coincide exactamente en el catálogo, registrar como ítem manual
                $rawItems[] = [
                    'product_name' => $searchTerm,
                    'base_price' => (float) ($entity['price'] ?? 0.00),
                    'quantity' => $quantity,
                    'selected_options' => [],
                ];
            }
        }

        // 2. Calcular montos finales y generar snapshots con OrderCalculator
        $calculated = $this->orderCalculator->calculate(
            tenantId: $input->tenantId,
            items: $rawItems,
            orderType: $input->orderType
        );

        // 3. Generar número correlativo por tenant
        $orderNumber = $this->numberGenerator->generate($input->tenantId);

        // 4. Persistir orden e ítems con OrderSnapshot JSON inmutable
        $order = DB::transaction(function () use ($input, $orderNumber, $calculated) {
            $ord = $this->orderRepository->create([
                'tenant_id' => $input->tenantId,
                'conversation_id' => $input->conversationId,
                'order_number' => $orderNumber,
                'customer_name' => $input->customerName,
                'customer_phone' => $input->customerPhone,
                'delivery_address' => $input->deliveryAddress,
                'order_type' => $input->orderType,
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'payment_status' => PaymentStatus::PENDING->value,
                'payment_method' => PaymentMethod::CASH->value,
                'subtotal' => $calculated['subtotal'],
                'delivery_fee' => $calculated['delivery_fee'],
                'discount_amount' => $calculated['discount_amount'],
                'total_amount' => $calculated['total_amount'],
            ]);

            foreach ($calculated['items'] as $itemData) {
                $this->itemRepository->create([
                    'tenant_id' => $input->tenantId,
                    'order_id' => $ord->id,
                    'product_id' => $itemData['product_id'],
                    'product_name' => $itemData['product_name'],
                    'unit_price' => $itemData['unit_price'],
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $itemData['subtotal'],
                    'options_snapshot' => $itemData['options_snapshot'],
                ]);
            }

            return $ord;
        });

        // 5. Construir Resumen Comercial para el cliente
        $summary = "📋 *Resumen de tu Pedido {$order->order_number}:*\n";
        foreach ($calculated['items'] as $it) {
            $summary .= "• {$it['quantity']}x {$it['product_name']} (S/ " . number_format($it['subtotal'], 2) . ")\n";
        }
        if ($calculated['delivery_fee'] > 0) {
            $summary .= "🛵 Delivery: S/ " . number_format($calculated['delivery_fee'], 2) . "\n";
        }
        $summary .= "💰 *Total a pagar: S/ " . number_format($order->total_amount, 2) . "*\n\n";
        $summary .= "¿Confirmas tu pedido para enviarlo a preparación?";

        // 6. Enviar confirmación automática vía WhatsApp si aplica
        if ($input->sendWhatsAppSummary && $input->customerPhone) {
            $this->sendMessageAction->execute(new SendMessageInput(
                tenantId: $input->tenantId,
                recipientPhone: $input->customerPhone,
                content: $summary,
                senderType: 'AI',
                conversationId: $input->conversationId
            ));
        }

        // 7. Disparar Evento de Dominio
        $this->eventBus->dispatch(new OrderCreated($order));

        return new CreateOrderFromAIResult($order->load('items'), $summary);
    }
}
