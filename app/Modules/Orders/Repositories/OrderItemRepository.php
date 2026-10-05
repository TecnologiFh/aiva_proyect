<?php

namespace App\Modules\Orders\Repositories;

use App\Modules\Orders\Models\OrderItem;

class OrderItemRepository
{
    public function create(array $data): OrderItem
    {
        return OrderItem::create($data);
    }
}
