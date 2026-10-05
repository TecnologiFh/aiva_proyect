<?php

namespace App\Modules\CRM\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_profiles';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'total_orders_count',
        'total_spent',
        'average_ticket',
        'clv',
        'cancellation_rate',
        'customer_score',
        'orders_30d',
        'orders_90d',
        'favorite_category',
        'preferred_payment',
        'preferred_delivery',
        'ai_summary',
        'last_order_at',
        'last_interaction_at',
    ];

    protected $casts = [
        'total_orders_count' => 'integer',
        'total_spent' => 'float',
        'average_ticket' => 'float',
        'clv' => 'float',
        'cancellation_rate' => 'float',
        'customer_score' => 'integer',
        'orders_30d' => 'integer',
        'orders_90d' => 'integer',
        'last_order_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];
}
