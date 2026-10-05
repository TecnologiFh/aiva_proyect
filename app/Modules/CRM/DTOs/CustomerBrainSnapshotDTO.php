<?php

namespace App\Modules\CRM\DTOs;

class CustomerBrainSnapshotDTO
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $customerUuid,
        public readonly string $name,
        public readonly string $stage,
        public readonly int $score,
        public readonly float $averageTicket,
        public readonly float $totalSpent,
        public readonly int $totalOrdersCount,
        public readonly ?string $favoriteCategory = null,
        public readonly ?string $preferredPayment = null,
        public readonly ?string $aiSummary = null,
        public readonly array $memories = [],
        public readonly array $tags = [],
        public readonly array $defaultAddress = []
    ) {}

    public function toArray(): array
    {
        return [
            'customer_uuid' => $this->customerUuid,
            'name' => $this->name,
            'stage' => $this->stage,
            'score' => $this->score,
            'average_ticket' => $this->averageTicket,
            'total_spent' => $this->totalSpent,
            'total_orders_count' => $this->totalOrdersCount,
            'favorite_category' => $this->favoriteCategory,
            'preferred_payment' => $this->preferredPayment,
            'ai_summary' => $this->aiSummary,
            'memories' => $this->memories,
            'tags' => $this->tags,
            'default_address' => $this->defaultAddress,
        ];
    }
}
