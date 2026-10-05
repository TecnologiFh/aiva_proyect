<?php

namespace App\Modules\CRM\Repositories;

use App\Modules\CRM\Models\CustomerTag;
use Illuminate\Support\Collection;

class CustomerTagRepository
{
    public function findOrCreateTag(int $tenantId, string $name, string $tagType = 'USER', string $color = '#6c757d'): CustomerTag
    {
        return CustomerTag::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $name],
            ['tag_type' => $tagType, 'color' => $color]
        );
    }

    public function getTagsByTenant(int $tenantId): Collection
    {
        return CustomerTag::where('tenant_id', $tenantId)->get();
    }
}
