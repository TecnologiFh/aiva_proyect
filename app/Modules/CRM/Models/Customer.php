<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'phone',
        'email',
        'preferred_channel',
        'total_orders_count',
        'total_spent_amount',
        'favorite_product',
        'tag',
        'notes',
        'last_order_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
