<?php

namespace App\Modules\Business\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    use BelongsToTenant;

    protected $table = 'business_hours';

    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'shift_number',
        'open_time',
        'close_time',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'shift_number' => 'integer',
        'is_closed' => 'boolean',
    ];
}
