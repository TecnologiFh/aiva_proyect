<?php

namespace App\Modules\CRM\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerTimeline extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_timeline';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'event_type',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
