<?php

namespace App\Modules\CRM\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'customer_id',
        'label',
        'address',
        'reference',
        'latitude',
        'longitude',
        'delivery_zone',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_default' => 'boolean',
    ];
}
