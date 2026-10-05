<?php

namespace App\Modules\Payments\Application\ProcessPaymentWebhook;

class ProcessPaymentWebhookResult
{
    public function __construct(
        public readonly bool $processed,
        public readonly string $status = 'APPROVED'
    ) {}

    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'status' => $this->status,
        ];
    }
}
