<?php

namespace App\Modules\Analytics\Services;

use App\Modules\AI\Models\AIRequest;
use App\Modules\Messaging\Models\Message;
use App\Modules\Orders\Models\Order;

class FunnelAnalyticsService
{
    public function getFunnelMetrics(int $tenantId): array
    {
        $messagesReceived = Message::where('tenant_id', $tenantId)->where('sender_type', 'CUSTOMER')->count();
        $purchaseIntents = AIRequest::where('tenant_id', $tenantId)->where('intent', 'PURCHASE_INTENT')->count();
        $ordersCreated = Order::where('tenant_id', $tenantId)->count();
        $ordersCompleted = Order::where('tenant_id', $tenantId)->whereIn('status', ['DELIVERED', 'CONFIRMED'])->count();

        $conversionRate = $messagesReceived > 0 ? round(($ordersCompleted / $messagesReceived) * 100, 1) : 0.0;

        return [
            'messages_received' => $messagesReceived,
            'purchase_intents' => $purchaseIntents,
            'orders_created' => $ordersCreated,
            'orders_completed' => $ordersCompleted,
            'conversion_rate_percentage' => $conversionRate,
        ];
    }
}
