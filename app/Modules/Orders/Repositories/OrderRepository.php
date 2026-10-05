<?php

namespace App\Modules\Orders\Repositories;

use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findById(int $id): ?Order
    {
        return Order::with(['items'])->find($id);
    }

    public function findByUuid(string $uuid): ?Order
    {
        return Order::with(['items'])->whereUuid($uuid)->first();
    }

    public function updateStatus(int $id, string $status): Order
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => $status]);
        return $order;
    }

    public function paginate(int $tenantId, ?string $status = null, ?string $search = null, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = Order::with(['items'])->where('tenant_id', $tenantId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(perPage: $limit, page: $page);
    }
}
