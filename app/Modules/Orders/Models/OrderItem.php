<?php

namespace App\Modules\Orders\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'order_items';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
        'options_snapshot',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'options_snapshot' => 'array',
    ];
}
