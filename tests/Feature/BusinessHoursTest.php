<?php

namespace Tests\Feature;

use App\Modules\Business\Models\BusinessHour;

use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    public function test_business_hour_shifts_model(): void
    {
        $hourShift1 = new BusinessHour([
            'day_of_week' => 1,
            'shift_number' => 1,
            'open_time' => '09:00',
            'close_time' => '14:00',
            'is_closed' => false,
        ]);

        $hourShift2 = new BusinessHour([
            'day_of_week' => 1,
            'shift_number' => 2,
            'open_time' => '17:00',
            'close_time' => '23:00',
            'is_closed' => false,
        ]);

        $this->assertEquals(1, $hourShift1->day_of_week);
        $this->assertEquals(1, $hourShift1->shift_number);
        $this->assertEquals(2, $hourShift2->shift_number);
        $this->assertFalse($hourShift1->is_closed);
    }
}
