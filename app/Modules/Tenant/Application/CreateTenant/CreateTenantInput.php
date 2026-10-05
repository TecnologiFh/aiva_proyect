<?php

namespace App\Modules\Tenant\Application\CreateTenant;

class CreateTenantInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $businessType,
        public readonly string $country = 'PE',
        public readonly string $currency = 'PEN',
        public readonly string $timezone = 'America/Lima'
    ) {}
}
