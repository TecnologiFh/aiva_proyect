<?php

namespace App\Modules\Business\Application\BusinessHours\SetBusinessHours;

class SetBusinessHoursInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly array $hours = []
    ) {}
}
