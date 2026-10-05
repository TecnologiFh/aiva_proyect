<?php

namespace App\Modules\AI\Repositories;

use App\Modules\AI\Models\CustomerMemory;
use Illuminate\Support\Collection;

class CustomerMemoryRepository
{
    public function getCustomerMemories(int $tenantId, string $customerPhone): Collection
    {
        return CustomerMemory::where('tenant_id', $tenantId)
            ->where('customer_phone', $customerPhone)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('importance_score', 'desc')
            ->get();
    }

    public function setMemory(int $tenantId, string $customerPhone, string $key, string $value, int $importance = 1): CustomerMemory
    {
        return CustomerMemory::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_phone' => $customerPhone,
                'memory_key' => $key,
            ],
            [
                'memory_value' => $value,
                'importance_score' => $importance,
            ]
        );
    }
}
