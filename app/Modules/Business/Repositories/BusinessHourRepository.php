<?php

namespace App\Modules\Business\Repositories;

use App\Modules\Business\Models\BusinessHour;
use Illuminate\Support\Collection;

class BusinessHourRepository
{
    public function getByTenantId(int $tenantId): Collection
    {
        return BusinessHour::where('tenant_id', $tenantId)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('shift_number', 'asc')
            ->get();
    }

    public function syncHours(int $tenantId, array $hours): void
    {
        BusinessHour::where('tenant_id', $tenantId)->delete();

        foreach ($hours as $hourData) {
            BusinessHour::create([
                'tenant_id' => $tenantId,
                'day_of_week' => $hourData['day_of_week'],
                'shift_number' => $hourData['shift_number'] ?? 1,
                'open_time' => $hourData['open_time'] ?? null,
                'close_time' => $hourData['close_time'] ?? null,
                'is_closed' => $hourData['is_closed'] ?? false,
            ]);
        }
    }
}
