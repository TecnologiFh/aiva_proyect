<?php

namespace App\Modules\Business\Application\BusinessSettings\UpdateBusinessSettings;

class UpdateBusinessSettingsInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $businessName,
        public readonly string $businessType = 'RESTAURANT',
        public readonly ?string $phone = null,
        public readonly ?string $whatsappPhone = null,
        public readonly ?string $address = null,
        public readonly string $timezone = 'America/Lima',
        public readonly string $currency = 'PEN',
        public readonly bool $deliveryEnabled = true,
        public readonly float $deliveryFee = 0.00,
        public readonly float $minOrderAmount = 0.00,
        public readonly ?string $termsAndConditions = null,
        public readonly ?array $preferences = null
    ) {}
}
