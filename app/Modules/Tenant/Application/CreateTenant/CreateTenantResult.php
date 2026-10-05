<?php

namespace App\Modules\Tenant\Application\CreateTenant;

use App\Modules\Tenant\Models\Tenant;

class CreateTenantResult
{
    public function __construct(
        public readonly Tenant $tenant
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->tenant->uuid,
            'name' => $this->tenant->name,
            'slug' => $this->tenant->slug,
            'business_type' => $this->tenant->business_type,
            'country' => $this->tenant->country,
            'currency' => $this->tenant->currency,
            'timezone' => $this->tenant->timezone,
            'status' => $this->tenant->status->value,
            'created_at' => $this->tenant->created_at?->toIso8601String(),
        ];
    }
}
