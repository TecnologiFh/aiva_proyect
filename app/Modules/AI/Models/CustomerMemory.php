<?php

namespace App\Modules\AI\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerMemory extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'customer_memories';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'customer_phone',
        'memory_key',
        'memory_value',
        'importance_score',
        'expires_at',
    ];

    protected $casts = [
        'importance_score' => 'integer',
        'expires_at' => 'datetime',
    ];
}
