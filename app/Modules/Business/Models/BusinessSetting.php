<?php

namespace App\Modules\Business\Models;

use App\Core\Enums\BusinessType;
use App\Core\Enums\Currency;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'business_settings';

    protected $fillable = [
        'tenant_id',
        'business_name',
        'business_type',
        'phone',
        'whatsapp_phone',
        'address',
        'timezone',
        'currency',
        'delivery_enabled',
        'delivery_fee',
        'min_order_amount',
        'terms_and_conditions',
        'preferences',
    ];

    protected $casts = [
        'business_type' => BusinessType::class,
        'currency' => Currency::class,
        'delivery_enabled' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'preferences' => 'array',
    ];
}
