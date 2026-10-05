<?php

namespace App\Core\Events;

use App\Modules\Tenant\Models\Tenant;

class TenantCreated
{
    public function __construct(
        public readonly Tenant $tenant
    ) {}
}
