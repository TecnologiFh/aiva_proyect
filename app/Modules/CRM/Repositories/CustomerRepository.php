<?php

namespace App\Modules\CRM\Repositories;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\CustomerIdentity;
use App\Modules\CRM\Models\CustomerProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    public function findOrCreateByIdentity(int $tenantId, string $channel, string $identifier, ?string $name = null): Customer
    {
        $identity = CustomerIdentity::where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('identifier', $identifier)
            ->first();

        if ($identity) {
            return Customer::with(['profile', 'addresses', 'tags'])->find($identity->customer_id);
        }

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'name' => $name ?? 'Cliente ' . ucfirst(strtolower($channel)),
        ]);

        CustomerIdentity::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'channel' => $channel,
            'identifier' => $identifier,
        ]);

        CustomerProfile::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
        ]);

        return Customer::with(['profile', 'addresses', 'tags'])->find($customer->id);
    }

    public function findByUuid(string $uuid): ?Customer
    {
        return Customer::with(['profile', 'addresses', 'tags', 'memories', 'timeline'])->whereUuid($uuid)->first();
    }

    public function paginate(int $tenantId, ?string $stage = null, ?string $search = null, int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = Customer::with(['profile', 'tags'])->where('tenant_id', $tenantId);

        if ($stage) {
            $query->where('customer_stage', $stage);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(perPage: $limit, page: $page);
    }
}
