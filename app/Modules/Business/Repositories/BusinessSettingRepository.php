<?php

namespace App\Modules\Business\Repositories;

use App\Modules\Business\Models\BusinessSetting;

class BusinessSettingRepository
{
    public function getByTenantId(int $tenantId): ?BusinessSetting
    {
        return BusinessSetting::where('tenant_id', $tenantId)->first();
    }

    public function updateOrCreate(int $tenantId, array $data): BusinessSetting
    {
        return BusinessSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            $data
        );
    }
}
