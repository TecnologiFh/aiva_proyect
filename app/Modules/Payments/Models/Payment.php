<?php

namespace App\Modules\Payments\Models;

use App\Core\Enums\Currency;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentTransactionStatus;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'order_id',
        'gateway',
        'payment_method',
        'gateway_transaction_id',
        'gateway_reference',
        'merchant_reference',
        'authorization_code',
        'amount',
        'currency',
        'status',
        'qr_code_url',
        'payment_link',
        'expires_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'currency' => Currency::class,
        'status' => PaymentTransactionStatus::class,
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'payment_id');
    }
}
