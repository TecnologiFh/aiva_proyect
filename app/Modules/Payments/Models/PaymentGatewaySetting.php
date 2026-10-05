<?php

namespace App\Modules\Payments\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    use BelongsToTenant;

    protected $table = 'payment_gateway_settings';

    protected $fillable = [
        'tenant_id',
        'gateway',
        'public_key',
        'secret_key',
        'merchant_id',
        'environment',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
