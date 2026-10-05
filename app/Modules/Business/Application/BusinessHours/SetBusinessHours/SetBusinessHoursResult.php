<?php

namespace App\Modules\Business\Application\BusinessHours\SetBusinessHours;

use Illuminate\Support\Collection;

class SetBusinessHoursResult
{
    public function __construct(
        public readonly Collection $hours
    ) {}

    public function toArray(): array
    {
        return $this->hours->map(fn($h) => [
            'day_of_week' => $h->day_of_week,
            'shift_number' => $h->shift_number,
            'open_time' => $h->open_time,
            'close_time' => $h->close_time,
            'is_closed' => (bool) $h->is_closed,
        ])->toArray();
    }
}
