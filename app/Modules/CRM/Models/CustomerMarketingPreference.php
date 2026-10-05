<?php

namespace App\Modules\CRM\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerMarketingPreference extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_marketing_preferences';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'accepts_promotions',
        'preferred_channel',
        'last_campaign_id',
        'last_coupon_code',
    ];

    protected $casts = [
        'accepts_promotions' => 'boolean',
    ];
}
