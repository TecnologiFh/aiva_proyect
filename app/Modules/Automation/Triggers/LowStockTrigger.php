<?php

namespace App\Modules\Automation\Triggers;

use App\Core\Events\LowStockDetected;

class LowStockTrigger implements AutomationTriggerInterface
{
    public function matches(string $eventName): bool
    {
        return $eventName === LowStockDetected::class;
    }

    public function extractPayload(object $event): array
    {
        /** @var LowStockDetected $event */
        $stock = $event->stock;

        return [
            'tenant_id' => $stock->tenant_id,
            'stock' => [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'current_stock' => (float) $stock->current_stock,
                'minimum_stock' => (float) $stock->minimum_stock,
            ],
            'product' => [
                'name' => $stock->product?->name ?? 'Producto',
            ]
        ];
    }

    public function getTriggerName(): string
    {
        return 'LOW_STOCK_DETECTED';
    }
}
