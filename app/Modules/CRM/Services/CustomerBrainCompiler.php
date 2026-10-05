<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\DTOs\CustomerBrainSnapshotDTO;
use App\Modules\CRM\Models\Customer;
use Illuminate\Support\Facades\Redis;

class CustomerBrainCompiler
{
    public function compile(Customer $customer): CustomerBrainSnapshotDTO
    {
        $customer->loadMissing(['profile', 'addresses', 'tags', 'memories']);

        $profile = $customer->profile;
        $defaultAddress = $customer->addresses->where('is_default', true)->first();

        $memoriesArray = $customer->memories->map(fn($m) => [
            'key' => $m->key,
            'value' => $m->value,
        ])->values()->toArray();

        $tagsArray = $customer->tags->pluck('name')->toArray();

        $defaultAddressArray = $defaultAddress ? [
            'address' => $defaultAddress->address,
            'reference' => $defaultAddress->reference,
            'latitude' => $defaultAddress->latitude,
            'longitude' => $defaultAddress->longitude,
        ] : [];

        $dto = new CustomerBrainSnapshotDTO(
            customerId: $customer->id,
            customerUuid: $customer->uuid,
            name: $customer->name,
            stage: $customer->customer_stage->value,
            score: (int) ($profile?->customer_score ?? 50),
            averageTicket: (float) ($profile?->average_ticket ?? 0.00),
            totalSpent: (float) ($profile?->total_spent ?? 0.00),
            totalOrdersCount: (int) ($profile?->total_orders_count ?? 0),
            favoriteCategory: $profile?->favorite_category,
            preferredPayment: $profile?->preferred_payment,
            aiSummary: $profile?->ai_summary ?? "Cliente {$customer->name} en etapa " . $customer->customer_stage->value,
            memories: $memoriesArray,
            tags: $tagsArray,
            defaultAddress: $defaultAddressArray
        );

        // Guardar snapshot ultra-rápido en Redis para el Motor de IA (0ms latencia BD)
        $redisKey = "tenant:{$customer->tenant_id}:customer:{$customer->id}:brain";
        try {
            Redis::set($redisKey, json_encode($dto->toArray()));
        } catch (\Throwable $e) {
            // Decaimiento silencioso si Redis no está disponible
        }

        return $dto;
    }
}
