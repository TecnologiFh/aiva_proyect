<?php

namespace App\Modules\Messaging\Models;

use App\Core\Enums\MessageStatus;
use App\Core\Enums\MessageType;
use App\Core\Enums\SenderType;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'messages';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'sender_type',
        'message_type',
        'content',
        'media_url',
        'mime_type',
        'file_size',
        'external_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'sender_type' => SenderType::class,
        'message_type' => MessageType::class,
        'status' => MessageStatus::class,
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
