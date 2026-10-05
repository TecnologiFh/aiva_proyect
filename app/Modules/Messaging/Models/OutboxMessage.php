<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $table = 'outbox_messages';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'sequence_number',
        'recipient_phone',
        'message_type',
        'payload_json',
        'status',
        'attempts',
        'lease_expires_at',
        'next_retry_at',
        'last_attempt_at',
        'whatsapp_message_id',
        'correlation_token',
        'last_http_status',
        'last_error',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'sequence_number' => 'integer',
        'attempts' => 'integer',
        'lease_expires_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    // Status Constants
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_DISPATCHING = 'DISPATCHING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_RETRYING = 'RETRYING';
    public const STATUS_DEAD_LETTER = 'DEAD_LETTER';
    public const STATUS_AMBIGUOUS = 'DISPATCH_AMBIGUOUS_RECONCILIATION_REQUIRED';
    public const STATUS_FORENSIC_UNRESOLVED = 'FORENSIC_UNRESOLVED';

    public const MAX_DISPATCH_ATTEMPTS = 5;
}
