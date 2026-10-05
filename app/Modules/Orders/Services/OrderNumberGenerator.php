<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    public function generate(int $tenantId): string
    {
        $year = date('Y');

        return DB::transaction(function () use ($tenantId, $year) {
            $lastOrder = Order::where('tenant_id', $tenantId)
                ->where('order_number', 'like', "ORD-{$year}-%")
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $sequence = 1;
            if ($lastOrder && preg_match('/ORD-\d{4}-(\d+)/', $lastOrder->order_number, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('ORD-%s-%04d', $year, $sequence);
        });
    }
}
