<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $table = 'delivery_zones';

    protected $fillable = [
        'tenant_id',
        'zone_name',
        'delivery_fee',
        'estimated_time_mins',
        'min_order_amount',
        'is_active',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
