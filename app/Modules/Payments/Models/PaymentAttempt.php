<?php

namespace App\Modules\Payments\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'payment_attempts';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'payment_id',
        'attempt_number',
        'status',
        'error_code',
        'error_message',
        'raw_response',
        'created_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'raw_response' => 'array',
        'created_at' => 'datetime',
    ];
}
