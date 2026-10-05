<?php

namespace App\Modules\AI\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class AIRequest extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'ai_requests';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'provider',
        'model',
        'prompt_version',
        'intent_detected',
        'confidence',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'latency_ms',
        'status',
    ];

    protected $casts = [
        'confidence' => 'decimal:3',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'estimated_cost' => 'decimal:6',
        'latency_ms' => 'integer',
    ];
}
