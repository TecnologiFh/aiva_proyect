<?php

namespace App\Core\Events;

use App\Modules\Business\Models\BusinessSetting;

class BusinessSettingsUpdated
{
    public function __construct(
        public readonly BusinessSetting $settings
    ) {}
}
