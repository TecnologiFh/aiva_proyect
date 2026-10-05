<?php

namespace App\Modules\Payments\Repositories;

use App\Modules\Payments\Models\PaymentGatewaySetting;

class PaymentGatewaySettingRepository
{
    public function getSetting(int $tenantId, string $gateway): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::where('tenant_id', $tenantId)
            ->where('gateway', $gateway)
            ->where('is_active', true)
            ->first();
    }

    public function updateOrCreateSetting(int $tenantId, string $gateway, array $data): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'gateway' => $gateway],
            $data
        );
    }
}
