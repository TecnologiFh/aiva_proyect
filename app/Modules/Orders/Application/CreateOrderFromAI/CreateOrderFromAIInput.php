<?php

namespace App\Modules\Orders\Application\CreateOrderFromAI;

class CreateOrderFromAIInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $customerPhone,
        public readonly string $customerName,
        public readonly array $entities,
        public readonly ?int $conversationId = null,
        public readonly ?string $deliveryAddress = null,
        public readonly string $orderType = 'DELIVERY',
        public readonly bool $sendWhatsAppSummary = true
    ) {}
}
