<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $table = 'order_events';

    public $timestamps = false; // Solo usa created_at

    protected $fillable = [
        'tenant_id',
        'order_id',
        'event_type',
        'previous_state',
        'new_state',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
