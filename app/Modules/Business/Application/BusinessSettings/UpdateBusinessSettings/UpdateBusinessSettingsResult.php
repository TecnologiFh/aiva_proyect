<?php

namespace App\Modules\Business\Application\BusinessSettings\UpdateBusinessSettings;

use App\Modules\Business\Models\BusinessSetting;

class UpdateBusinessSettingsResult
{
    public function __construct(
        public readonly BusinessSetting $settings
    ) {}

    public function toArray(): array
    {
        return [
            'business_name' => $this->settings->business_name,
            'business_type' => $this->settings->business_type->value,
            'phone' => $this->settings->phone,
            'whatsapp_phone' => $this->settings->whatsapp_phone,
            'address' => $this->settings->address,
            'timezone' => $this->settings->timezone,
            'currency' => $this->settings->currency->value,
            'delivery_enabled' => $this->settings->delivery_enabled,
            'delivery_fee' => (float) $this->settings->delivery_fee,
            'min_order_amount' => (float) $this->settings->min_order_amount,
            'terms_and_conditions' => $this->settings->terms_and_conditions,
            'preferences' => $this->settings->preferences,
            'updated_at' => $this->settings->updated_at?->toIso8601String(),
        ];
    }
}
