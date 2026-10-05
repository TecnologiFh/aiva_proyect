<?php

namespace App\Modules\Messaging\Models;

use App\Core\Enums\ConversationMode;
use App\Core\Enums\ConversationStatus;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'conversations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'customer_phone',
        'customer_name',
        'status',
        'mode',
        'last_message_at',
        'welcome_sent_at',
    ];

    protected $casts = [
        'status' => ConversationStatus::class,
        'mode' => ConversationMode::class,
        'last_message_at' => 'datetime',
        'welcome_sent_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
